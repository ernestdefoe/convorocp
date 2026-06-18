<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;

/**
 * Maps an allowlisted operation to the work the root agent performs on the node.
 *
 * In DRY-RUN mode every op just describes what it would do. In live mode, ops
 * with a real handler are applied (as root, by the agent worker — never the web
 * tier); ops without one are soft-skipped so the queue stays clean during the
 * incremental rollout of handlers.
 */
class AgentHandlers
{
    /** op name => handler method */
    private const HANDLERS = [
        'site.create' => 'siteCreate',
        'site.adopt' => 'siteAdopt',
        'site.detach' => 'siteDetach',
        'site.set_php_version' => 'siteSetPhpVersion',
        'site.set_php_settings' => 'siteSetPhpSettings',
        'site.deploy' => 'siteDeploy',
        'site.delete' => 'siteDelete',
        'cert.issue' => 'certIssue',
        'php.install' => 'phpInstall',
        'php.uninstall' => 'phpUninstall',
        'db.create' => 'dbCreate',
        'db.user.create' => 'dbUserCreate',
        'db.drop' => 'dbDrop',
        'container.run' => 'containerRun',
        'container.start' => 'containerStart',
        'container.stop' => 'containerStop',
        'container.remove' => 'containerRemove',
        'backup.run' => 'backupRun',
        'backup.restore' => 'backupRestore',
        'mail.account_create' => 'mailAccountCreate',
        'mail.account_delete' => 'mailAccountDelete',
        'panel.update' => 'panelUpdate',
        'app.install' => 'appInstall',
        'service.control' => 'serviceControl',
        'firewall.allow' => 'firewallRule',
        'firewall.remove' => 'firewallRemove',
        'firewall.enable' => 'firewallEnable',
        'firewall.disable' => 'firewallDisable',
        'fail2ban.install' => 'fail2banInstall',
        'fail2ban.unban' => 'fail2banUnban',
        'fail2ban.ban' => 'fail2banBan',
        'cron.write' => 'cronWrite',
        'cron.delete' => 'cronDelete',
        'cron.run_now' => 'cronRunNow',
        'daemon.create' => 'daemonCreate',
        'daemon.start' => 'daemonStart',
        'daemon.stop' => 'daemonStop',
        'daemon.restart' => 'daemonRestart',
        'daemon.delete' => 'daemonDelete',
        'docker.install' => 'dockerInstall',
        'nginx.write' => 'nginxWrite',
        'php.ini.write' => 'phpIniWrite',
    ];

    /** PHP versions actually installed on this node (detected from /etc/php). */
    private static function installedVersions(): array
    {
        $out = [];
        foreach (glob('/etc/php/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $v = basename($dir);
            if (preg_match('/^\d+\.\d+$/', $v) && is_dir("{$dir}/fpm")) {
                $out[] = $v;
            }
        }
        rsort($out);

        return $out ?: ['8.3'];
    }

    /** @return array<string,mixed> the operation result */
    public static function apply(string $op, array $args, bool $dryRun = true): array
    {
        if (! in_array($op, Agent::ALLOWED, true)) {
            throw new \InvalidArgumentException("Refusing unknown operation [{$op}].");
        }

        if ($dryRun) {
            return ['dry_run' => true, 'op' => $op, 'would' => self::describe($op, $args)];
        }

        $method = self::HANDLERS[$op] ?? null;
        if (! $method) {
            return ['skipped' => true, 'op' => $op, 'note' => 'live handler not implemented yet'];
        }

        return self::$method($args);
    }

    // ---- Real handlers --------------------------------------------------

    private static function siteCreate(array $args): array
    {
        $domain = (string) ($args['domain'] ?? '');
        if (! preg_match('/^[a-z0-9.-]+$/i', $domain) || str_contains($domain, '..')) {
            throw new \RuntimeException('Invalid domain.');
        }
        $runtime = $args['runtime'] ?? 'php';
        if (! in_array($runtime, ['php', 'static', 'node'], true)) {
            $runtime = 'php';
        }
        $php = in_array(($args['php'] ?? ''), self::installedVersions(), true) ? $args['php'] : self::installedVersions()[0];

        $base = "/var/www/sites/{$domain}";
        $root = "{$base}/public";
        if (! is_dir($root)) {
            mkdir($root, 0755, true);
        }

        if ($runtime === 'php') {
            file_put_contents("{$root}/index.php", "<?php echo 'ConvoroCP — {$domain} is live on PHP '.PHP_VERSION;\n");
        } else {
            file_put_contents("{$root}/index.html", "<!doctype html><meta charset=utf-8><h1>{$domain} is live</h1><p>Served by ConvoroCP.</p>\n");
        }
        self::run(['chown', '-R', 'www-data:www-data', $base]);

        $sock = null;
        if ($runtime === 'php') {
            $sock = "/run/php/cp-{$domain}.sock";
            file_put_contents("/etc/php/{$php}/fpm/pool.d/{$domain}.conf", self::fpmPool($domain, $sock, $args['settings'] ?? []));
            $r = self::run(['systemctl', 'reload', "php{$php}-fpm"]);
            if (! $r->successful()) {
                throw new \RuntimeException('php-fpm reload failed: '.trim($r->errorOutput()));
            }
        }

        $vhost = "/etc/nginx/sites-available/{$domain}";
        $link = "/etc/nginx/sites-enabled/{$domain}";
        file_put_contents($vhost, self::nginxConf($domain, $root, $runtime, $sock));
        if (! file_exists($link)) {
            symlink($vhost, $link);
        }

        $test = self::run(['nginx', '-t']);
        if (! $test->successful()) {
            @unlink($link);
            @unlink($vhost);
            throw new \RuntimeException('nginx config invalid, rolled back: '.trim($test->errorOutput()));
        }
        self::run(['systemctl', 'reload', 'nginx']);

        return ['applied' => true, 'domain' => $domain, 'root' => $root, 'runtime' => $runtime, 'php' => $runtime === 'php' ? $php : null];
    }

    private static function siteSetPhpVersion(array $args): array
    {
        $domain = self::domain($args);
        $php = in_array(($args['php'] ?? ''), self::installedVersions(), true) ? $args['php'] : self::installedVersions()[0];
        $sock = "/run/php/cp-{$domain}.sock";

        // The socket name is version-independent; move the pool to the chosen
        // version and drop it from the others so only one FPM owns the socket.
        foreach (self::installedVersions() as $v) {
            $pool = "/etc/php/{$v}/fpm/pool.d/{$domain}.conf";
            if ($v === $php) {
                file_put_contents($pool, self::fpmPool($domain, $sock));
            } elseif (file_exists($pool)) {
                @unlink($pool);
            }
            self::run(['systemctl', 'reload', "php{$v}-fpm"]);
        }

        return ['applied' => true, 'domain' => $domain, 'php' => $php];
    }

    private static function siteSetPhpSettings(array $args): array
    {
        $domain = self::domain($args);
        $php = in_array(($args['php'] ?? ''), self::installedVersions(), true) ? $args['php'] : self::installedVersions()[0];
        $sock = "/run/php/cp-{$domain}.sock";

        file_put_contents("/etc/php/{$php}/fpm/pool.d/{$domain}.conf", self::fpmPool($domain, $sock, $args['settings'] ?? []));
        self::run(['systemctl', 'reload', "php{$php}-fpm"]);

        return ['applied' => true, 'domain' => $domain, 'php' => $php];
    }

    private static function siteDeploy(array $args): array
    {
        $domain = self::domain($args);
        $repo = (string) ($args['repo'] ?? '');
        if (! preg_match('#^https://[a-z0-9./_\-]+$#i', $repo)) {
            throw new \RuntimeException('Only https:// git repositories are supported.');
        }
        $branch = preg_match('/^[a-z0-9._\-\/]+$/i', (string) ($args['branch'] ?? 'main')) ? $args['branch'] : 'main';
        $root = "/var/www/sites/{$domain}/public";

        if (is_dir("{$root}/.git")) {
            self::run(['git', '-C', $root, 'remote', 'set-url', 'origin', $repo]);
            self::run(['git', '-C', $root, 'fetch', '--depth', '1', 'origin', $branch], 180);
            $r = self::run(['git', '-C', $root, 'reset', '--hard', "origin/{$branch}"], 120);
        } else {
            self::run(['rm', '-rf', $root]);
            $r = self::run(['git', 'clone', '--depth', '1', '--branch', $branch, $repo, $root], 180);
        }
        if (! $r->successful()) {
            throw new \RuntimeException('git failed: '.trim($r->errorOutput()));
        }

        if (is_file("{$root}/composer.json")) {
            self::run(['composer', 'install', '--no-dev', '--no-interaction', '--optimize-autoloader', '-d', $root], 300,
                ['COMPOSER_HOME' => '/root/.composer', 'COMPOSER_ALLOW_SUPERUSER' => '1']);
        }

        self::run(['chown', '-R', 'www-data:www-data', "/var/www/sites/{$domain}"]);

        return ['applied' => true, 'domain' => $domain, 'repo' => $repo, 'branch' => $branch];
    }

    private static function siteAdopt(array $args): array
    {
        $domain = self::domain($args);
        $path = (string) ($args['path'] ?? '');
        // Only ever create a symlink to an existing app dir — never write a vhost,
        // pool, or files. Adopting must not touch the live site's serving config.
        if ($path === '' || ! is_dir($path) || str_contains($path, '..')) {
            throw new \RuntimeException('Adopt path must be an existing directory.');
        }
        $link = "/var/www/sites/{$domain}";
        if (! is_dir('/var/www/sites')) {
            mkdir('/var/www/sites', 0755, true);
        }
        if (file_exists($link) || is_link($link)) {
            throw new \RuntimeException('A site dir already exists for that domain.');
        }
        self::run(['ln', '-s', rtrim($path, '/'), $link]);

        return ['applied' => true, 'domain' => $domain, 'adopted' => true, 'path' => $path];
    }

    private static function siteDetach(array $args): array
    {
        $domain = self::domain($args);
        $link = "/var/www/sites/{$domain}";
        // Remove ONLY the symlink — never the target app, vhost, or pool.
        if (is_link($link)) {
            @unlink($link);
        }

        return ['applied' => true, 'domain' => $domain, 'detached' => true];
    }

    private static function siteDelete(array $args): array
    {
        $domain = self::domain($args);
        // Safety: an adopted (symlinked) site must be detached, never hard-deleted
        // — its vhost/pool/files belong to an externally-managed app.
        if (is_link("/var/www/sites/{$domain}")) {
            return self::siteDetach($args);
        }
        @unlink("/etc/nginx/sites-enabled/{$domain}");
        @unlink("/etc/nginx/sites-available/{$domain}");
        foreach (self::installedVersions() as $v) {
            if (file_exists("/etc/php/{$v}/fpm/pool.d/{$domain}.conf")) {
                @unlink("/etc/php/{$v}/fpm/pool.d/{$domain}.conf");
                self::run(['systemctl', 'reload', "php{$v}-fpm"]);
            }
        }
        self::run(['rm', '-rf', "/var/www/sites/{$domain}"]);
        self::run(['systemctl', 'reload', 'nginx']);

        return ['applied' => true, 'domain' => $domain, 'deleted' => true];
    }

    private static function certIssue(array $args): array
    {
        $domain = self::domain($args);
        $staging = (bool) config('convorocp.agent.cert_staging', true);
        $email = (string) (config('convorocp.agent.cert_email') ?: "admin@{$domain}");

        $cmd = ['certbot', '--nginx', '-d', $domain, '--non-interactive', '--agree-tos', '-m', $email, '--redirect', '--keep-until-expiring'];
        if ($staging) {
            $cmd[] = '--staging';
        }

        $r = self::run($cmd, 180);
        if (! $r->successful()) {
            throw new \RuntimeException('certbot failed: '.trim($r->errorOutput().' '.$r->output()));
        }
        self::run(['systemctl', 'reload', 'nginx']);

        return ['applied' => true, 'domain' => $domain, 'staging' => $staging];
    }

    private static function phpInstall(array $args): array
    {
        $v = self::phpVersion($args);
        $pkgs = ["php{$v}-fpm", "php{$v}-cli", "php{$v}-mbstring", "php{$v}-xml", "php{$v}-curl",
            "php{$v}-zip", "php{$v}-sqlite3", "php{$v}-bcmath", "php{$v}-intl", "php{$v}-gd"];

        $r = self::run(array_merge(['apt-get', 'install', '-y', '-q'], $pkgs), 600, ['DEBIAN_FRONTEND' => 'noninteractive']);
        if (! $r->successful()) {
            self::markRuntime($v, 'available');
            throw new \RuntimeException("apt install php{$v} failed: ".trim($r->errorOutput()));
        }
        self::run(['systemctl', 'enable', '--now', "php{$v}-fpm"]);
        self::markRuntime($v, 'installed');

        return ['applied' => true, 'version' => $v, 'installed' => true];
    }

    private static function phpUninstall(array $args): array
    {
        $v = self::phpVersion($args);
        self::run(['apt-get', 'purge', '-y', '-q', "php{$v}-fpm", "php{$v}-common"], 300, ['DEBIAN_FRONTEND' => 'noninteractive']);
        self::run(['apt-get', 'autoremove', '-y', '-q'], 300, ['DEBIAN_FRONTEND' => 'noninteractive']);
        self::markRuntime($v, 'available');

        return ['applied' => true, 'version' => $v, 'removed' => true];
    }

    private static function phpVersion(array $args): string
    {
        $v = (string) ($args['version'] ?? '');
        if (! preg_match('/^\d+\.\d+$/', $v)) {
            throw new \RuntimeException('Invalid PHP version.');
        }

        return $v;
    }

    private static function markRuntime(string $v, string $status): void
    {
        if (class_exists(\App\Models\PhpRuntime::class)) {
            \App\Models\PhpRuntime::where('version', $v)->update(['status' => $status]);
        }
    }

    // ---- Databases ------------------------------------------------------

    private static function dbCreate(array $args): array
    {
        $name = self::ident($args['name'] ?? '');
        $engine = self::dbEngine($args);

        if ($engine === 'pgsql') {
            $exists = self::run(['sudo', '-u', 'postgres', 'psql', '-tAc', "SELECT 1 FROM pg_database WHERE datname='{$name}'"]);
            if (trim($exists->output()) !== '1') {
                $r = self::run(['sudo', '-u', 'postgres', 'createdb', $name]);
                if (! $r->successful()) {
                    throw new \RuntimeException('createdb failed: '.trim($r->errorOutput()));
                }
            }
        } elseif ($engine === 'sqlite') {
            $dir = '/var/lib/convorocp/sqlite';
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            touch("{$dir}/{$name}.sqlite");
            self::run(['chown', 'www-data:www-data', "{$dir}/{$name}.sqlite"]);
        } else {
            $r = self::run(['mysql', '-e', "CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"]);
            if (! $r->successful()) {
                throw new \RuntimeException('mysql create failed: '.trim($r->errorOutput()));
            }
        }

        return ['applied' => true, 'name' => $name, 'engine' => $engine];
    }

    private static function dbUserCreate(array $args): array
    {
        $engine = self::dbEngine($args);
        $user = self::ident($args['user'] ?? '');
        $db = self::ident($args['grant'] ?? '');
        $pass = (string) ($args['password'] ?? '');
        if (! preg_match('/^[A-Za-z0-9]+$/', $pass)) {
            throw new \RuntimeException('Invalid db password.');
        }

        if ($engine === 'pgsql') {
            self::run(['sudo', '-u', 'postgres', 'psql', '-c', "CREATE ROLE \"{$user}\" LOGIN PASSWORD '{$pass}'"]);
            self::run(['sudo', '-u', 'postgres', 'psql', '-c', "GRANT ALL PRIVILEGES ON DATABASE \"{$db}\" TO \"{$user}\""]);
        } elseif ($engine !== 'sqlite') {
            $r = self::run(['mysql', '-e', "CREATE USER IF NOT EXISTS '{$user}'@'localhost' IDENTIFIED BY '{$pass}'; GRANT ALL PRIVILEGES ON `{$db}`.* TO '{$user}'@'localhost'; FLUSH PRIVILEGES;"]);
            if (! $r->successful()) {
                throw new \RuntimeException('mysql user create failed: '.trim($r->errorOutput()));
            }
        }

        return ['applied' => true, 'user' => $user, 'engine' => $engine];
    }

    private static function dbDrop(array $args): array
    {
        $name = self::ident($args['name'] ?? '');
        $engine = self::dbEngine($args);
        $user = isset($args['user']) && $args['user'] !== '' ? self::ident($args['user']) : null;

        if ($engine === 'pgsql') {
            self::run(['sudo', '-u', 'postgres', 'dropdb', '--if-exists', $name]);
            if ($user) {
                self::run(['sudo', '-u', 'postgres', 'psql', '-c', "DROP ROLE IF EXISTS \"{$user}\""]);
            }
        } elseif ($engine === 'sqlite') {
            @unlink("/var/lib/convorocp/sqlite/{$name}.sqlite");
        } else {
            $sql = "DROP DATABASE IF EXISTS `{$name}`;";
            if ($user) {
                $sql .= " DROP USER IF EXISTS '{$user}'@'localhost';";
            }
            self::run(['mysql', '-e', $sql]);
        }

        return ['applied' => true, 'name' => $name, 'engine' => $engine, 'dropped' => true];
    }

    // ---- Firewall (ufw) -------------------------------------------------

    private static function firewallRule(array $args): array
    {
        [$port, $proto, $action] = self::fwArgs($args);
        $r = self::run(['ufw', $action, "{$port}/{$proto}"]);
        if (! $r->successful()) {
            throw new \RuntimeException('ufw failed: '.trim($r->errorOutput()));
        }

        return ['applied' => true, 'rule' => "{$action} {$port}/{$proto}"];
    }

    private static function firewallRemove(array $args): array
    {
        [$port, $proto, $action] = self::fwArgs($args);
        self::run(['ufw', '--force', 'delete', $action, "{$port}/{$proto}"]);

        return ['applied' => true, 'removed' => "{$action} {$port}/{$proto}"];
    }

    private static function firewallEnable(array $args): array
    {
        // Anti-lockout: always permit SSH + web + the panel port before enabling.
        foreach (['22/tcp', '80/tcp', '443/tcp', '8000/tcp'] as $p) {
            self::run(['ufw', 'allow', $p]);
        }
        $r = self::run(['ufw', '--force', 'enable']);
        if (! $r->successful()) {
            throw new \RuntimeException('ufw enable failed: '.trim($r->errorOutput()));
        }

        return ['applied' => true, 'enabled' => true];
    }

    private static function firewallDisable(array $args): array
    {
        self::run(['ufw', '--force', 'disable']);

        return ['applied' => true, 'enabled' => false];
    }

    /** @return array{0:int,1:string,2:string} port, proto, action */
    private static function fwArgs(array $args): array
    {
        $port = (int) ($args['port'] ?? 0);
        if ($port < 1 || $port > 65535) {
            throw new \RuntimeException('Invalid port.');
        }
        $proto = in_array(($args['proto'] ?? ''), ['tcp', 'udp'], true) ? $args['proto'] : 'tcp';
        $action = in_array(($args['action'] ?? ''), ['allow', 'deny'], true) ? $args['action'] : 'allow';

        return [$port, $proto, $action];
    }

    // ---- fail2ban -------------------------------------------------------

    private static function fail2banInstall(array $args): array
    {
        self::run(['bash', '-c', 'DEBIAN_FRONTEND=noninteractive apt-get install -y -q fail2ban'], 600);

        // Protect SSH; never ban loopback. Operators can tune later.
        file_put_contents('/etc/fail2ban/jail.local', <<<'CONF'
            [DEFAULT]
            bantime = 1h
            findtime = 10m
            maxretry = 5
            ignoreip = 127.0.0.1/8 ::1

            [sshd]
            enabled = true
            CONF);

        // Read-only status access for the web tier (www-data). Scoped to the
        // `status` subcommand so the panel can list jails/bans but not ban/stop.
        $sudoers = "/etc/sudoers.d/convorocp-fail2ban";
        file_put_contents($sudoers, "www-data ALL=(root) NOPASSWD: /usr/bin/fail2ban-client status, /usr/bin/fail2ban-client status *\n");
        self::run(['chmod', '440', $sudoers]);
        if (! self::run(['visudo', '-cf', $sudoers])->successful()) {
            @unlink($sudoers); // never leave a broken sudoers file in place
        }

        self::run(['systemctl', 'enable', 'fail2ban']);
        self::run(['systemctl', 'restart', 'fail2ban']);

        if (class_exists(\App\Support\Setting::class)) {
            \App\Support\Setting::set('fail2ban.installed', true);
        }

        return ['applied' => true, 'installed' => true];
    }

    private static function fail2banUnban(array $args): array
    {
        [$jail, $ip] = self::f2bArgs($args);
        self::run(['fail2ban-client', 'set', $jail, 'unbanip', $ip]);

        return ['applied' => true, 'unbanned' => $ip];
    }

    private static function fail2banBan(array $args): array
    {
        [$jail, $ip] = self::f2bArgs($args);
        self::run(['fail2ban-client', 'set', $jail, 'banip', $ip]);

        return ['applied' => true, 'banned' => $ip];
    }

    /** @return array{0:string,1:string} jail, ip */
    private static function f2bArgs(array $args): array
    {
        $jail = (string) ($args['jail'] ?? '');
        $ip = (string) ($args['ip'] ?? '');
        if (! preg_match('/^[\w.-]{1,40}$/', $jail)) {
            throw new \RuntimeException('Invalid jail.');
        }
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new \RuntimeException('Invalid IP.');
        }

        return [$jail, $ip];
    }

    // ---- Mail (Postfix + Dovecot) --------------------------------------

    private static function mailAccountCreate(array $args): array
    {
        $email = strtolower(trim((string) ($args['email'] ?? '')));
        $password = (string) ($args['password'] ?? '');
        $id = (int) ($args['account_id'] ?? 0);
        if (! preg_match('/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/', $email)) {
            throw new \RuntimeException('Invalid email address.');
        }
        if ($password === '' || strlen($password) > 200 || str_contains($password, ':') || str_contains($password, "\n")) {
            throw new \RuntimeException('Invalid mailbox password.');
        }
        [, $domain] = explode('@', $email, 2);

        self::ensureMailDomain($domain);
        self::writeDovecotUser($email, $password);
        self::run(['systemctl', 'reload', 'dovecot']);

        if ($id && class_exists(\App\Models\MailAccount::class)) {
            \App\Models\MailAccount::where('id', $id)->update(['status' => 'active']);
        }

        return ['applied' => true, 'email' => $email];
    }

    private static function mailAccountDelete(array $args): array
    {
        $email = strtolower(trim((string) ($args['email'] ?? '')));
        if (! str_contains($email, '@')) {
            throw new \RuntimeException('Invalid email address.');
        }
        [$local, $domain] = explode('@', $email, 2);

        self::removeDovecotUser($email);
        self::run(['systemctl', 'reload', 'dovecot']);

        if (preg_match('/^[a-z0-9._%+-]+$/', $local) && preg_match('/^[a-z0-9.-]+$/', $domain) && ! str_contains($domain, '..')) {
            self::run(['rm', '-rf', "/var/vmail/{$domain}/{$local}"]);
        }

        return ['applied' => true, 'email' => $email];
    }

    private static function ensureMailDomain(string $domain): void
    {
        $current = trim(self::run(['postconf', '-h', 'virtual_mailbox_domains'])->output());
        $list = array_values(array_filter(array_map('trim', preg_split('/[,\s]+/', $current))));
        if (! in_array($domain, $list, true)) {
            $list[] = $domain;
            self::run(['postconf', '-e', 'virtual_mailbox_domains = '.implode(', ', $list)]);
            self::run(['systemctl', 'reload', 'postfix']);
        }
    }

    private static function writeDovecotUser(string $email, string $password): void
    {
        $file = '/etc/dovecot/users';
        $lines = is_file($file) ? (file($file, FILE_IGNORE_NEW_LINES) ?: []) : [];
        $lines = array_filter($lines, fn ($l) => $l !== '' && ! str_starts_with($l, $email.':'));
        $lines[] = $email.':{PLAIN}'.$password;
        file_put_contents($file, implode("\n", $lines)."\n");
        self::run(['chown', 'root:dovecot', $file]);
        self::run(['chmod', '640', $file]);
    }

    private static function removeDovecotUser(string $email): void
    {
        $file = '/etc/dovecot/users';
        if (! is_file($file)) {
            return;
        }
        $lines = array_filter(file($file, FILE_IGNORE_NEW_LINES) ?: [], fn ($l) => $l !== '' && ! str_starts_with($l, $email.':'));
        file_put_contents($file, $lines ? implode("\n", $lines)."\n" : '');
        self::run(['chown', 'root:dovecot', $file]);
        self::run(['chmod', '640', $file]);
    }

    // ---- Self-update ----------------------------------------------------

    private static function panelUpdate(array $args): array
    {
        $repo = (string) ($args['repo'] ?? '');
        $tag = (string) ($args['tag'] ?? '');
        $token = $args['token'] ?? null;
        if (! preg_match('#^[\w.-]+/[\w.-]+$#', $repo)) {
            throw new \RuntimeException('Invalid repo.');
        }
        if (! preg_match('/^[\w.-]+$/', $tag)) {
            throw new \RuntimeException('Invalid tag.');
        }

        $dest = base_path();
        $tmp = '/tmp/convorocp-update-'.preg_replace('/[^\w.-]/', '', $tag);
        self::run(['rm', '-rf', $tmp]);
        mkdir($tmp, 0755, true);

        // Download the tag tarball from GitHub (token for private repos).
        $hdr = ['-H', 'User-Agent: ConvoroCP', '-H', 'Accept: application/vnd.github+json'];
        if ($token) {
            $hdr[] = '-H';
            $hdr[] = 'Authorization: Bearer '.$token;
        }
        $url = "https://api.github.com/repos/{$repo}/tarball/{$tag}";
        $dl = self::run(array_merge(['curl', '-fsSL'], $hdr, ['-o', "{$tmp}/src.tar.gz", $url]), 300);
        if (! $dl->successful()) {
            throw new \RuntimeException('Download failed: '.trim($dl->errorOutput()));
        }

        $ex = self::run(['tar', '-xzf', "{$tmp}/src.tar.gz", '-C', $tmp], 120);
        if (! $ex->successful()) {
            throw new \RuntimeException('Extract failed: '.trim($ex->errorOutput()));
        }
        $dirs = glob($tmp.'/*', GLOB_ONLYDIR);
        $src = $dirs[0] ?? null;
        if (! $src) {
            throw new \RuntimeException('Archive empty.');
        }

        // Sync new code in, preserving runtime state + currently built assets.
        $excludes = ['.env', 'storage', 'database', 'bootstrap/cache', 'public/build', '.git', 'node_modules', 'vendor'];
        $rsync = ['rsync', '-a', '--delete'];
        foreach ($excludes as $e) {
            $rsync[] = '--exclude';
            $rsync[] = $e;
        }
        $rsync[] = rtrim($src, '/').'/';
        $rsync[] = rtrim($dest, '/').'/';
        $rs = self::run($rsync, 300);
        if (! $rs->successful()) {
            throw new \RuntimeException('Sync failed: '.trim($rs->errorOutput()));
        }

        // Backend dependencies + migrations + cache.
        self::run(['bash', '-c', "cd {$dest} && COMPOSER_ALLOW_SUPERUSER=1 php8.4 /usr/local/bin/composer install --no-dev --optimize-autoloader 2>&1"], 600);
        self::run(['php8.4', "{$dest}/artisan", 'migrate', '--force'], 300);
        self::run(['php8.4', "{$dest}/artisan", 'optimize:clear'], 120);
        self::run(['chown', '-R', 'www-data:www-data', "{$dest}/bootstrap/cache", "{$dest}/storage", "{$dest}/database"]);
        self::run(['systemctl', 'reload', 'php8.4-fpm']);
        self::run(['rm', '-rf', $tmp]);

        if (class_exists(\App\Support\Setting::class)) {
            \App\Support\Setting::set('panel.updated_at', now()->toIso8601String());
            \App\Support\Setting::set('panel.updated_to', $tag);
        }

        // Restart the agent AFTER this op finishes so we don't kill ourselves
        // mid-handler (the worker has the old code loaded in memory).
        self::run(['bash', '-c', 'nohup sh -c "sleep 3; systemctl restart convorocp-agent" >/dev/null 2>&1 &']);

        return ['applied' => true, 'tag' => $tag];
    }

    // ---- One-click apps -------------------------------------------------

    private static function appInstall(array $args): array
    {
        $id = (int) ($args['install_id'] ?? 0);
        $app = (string) ($args['app'] ?? '');
        $domain = (string) ($args['domain'] ?? '');
        if (! preg_match('/^[a-z0-9.-]+$/i', $domain) || str_contains($domain, '..')) {
            throw new \RuntimeException('Invalid domain.');
        }
        $dir = "/var/www/sites/{$domain}";
        if (! is_dir($dir)) {
            throw new \RuntimeException('Site is not provisioned.');
        }

        // Sites serve from {dir}/public. Root-docroot apps (WordPress, phpMyAdmin,
        // static) install there; Convoro is a Laravel app whose own public/ becomes
        // the docroot, so it installs at the site root.
        $pub = "{$dir}/public";
        if (! is_dir($pub)) {
            mkdir($pub, 0755, true);
        }

        try {
            $info = match ($app) {
                'static' => self::appStatic($pub, $domain),
                'wordpress' => self::appWordpress($pub, $domain),
                'phpmyadmin' => self::appPhpMyAdmin($pub),
                'convoro' => self::appConvoro($dir, $domain),
                'flarum' => self::appFlarum($dir, $domain),
                default => throw new \RuntimeException('Unknown app.'),
            };
            self::run(['chown', '-R', 'www-data:www-data', $dir]);
            self::markInstall($id, 'done', $info);
        } catch (\Throwable $e) {
            self::markInstall($id, 'failed', substr($e->getMessage(), 0, 180));
            throw $e;
        }

        return ['applied' => true, 'app' => $app, 'domain' => $domain];
    }

    private static function markInstall(int $id, string $status, ?string $info): void
    {
        if ($id && class_exists(\App\Models\AppInstall::class)) {
            \App\Models\AppInstall::where('id', $id)->update(['status' => $status, 'info' => $info]);
        }
    }

    /** @return array{0:string,1:string,2:string} db, user, password */
    private static function createAppDb(string $base): array
    {
        $db = 'app_'.substr(preg_replace('/[^a-z0-9]/', '', md5($base.uniqid('', true))), 0, 12);
        $pass = bin2hex(random_bytes(9));
        $r = self::run(['mysql', '-e',
            "CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; ".
            "CREATE USER IF NOT EXISTS '{$db}'@'localhost' IDENTIFIED BY '{$pass}'; ".
            "GRANT ALL PRIVILEGES ON `{$db}`.* TO '{$db}'@'localhost'; FLUSH PRIVILEGES;",
        ]);
        if (! $r->successful()) {
            throw new \RuntimeException('Database setup failed: '.trim($r->errorOutput()));
        }

        return [$db, $db, $pass];
    }

    private static function appStatic(string $dir, string $domain): string
    {
        $html = "<!doctype html><html lang=\"en\"><head><meta charset=\"utf-8\">".
            "<meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><title>{$domain}</title>".
            "<style>body{font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;background:#0f0f17;color:#e7e7ef}".
            ".c{text-align:center}h1{font-weight:600}</style></head><body><div class=\"c\"><h1>{$domain}</h1>".
            "<p>Your site is ready. Replace this page with your content.</p></div></body></html>";
        file_put_contents("{$dir}/index.html", $html);
        @unlink("{$dir}/index.php");

        return 'Static starter page installed';
    }

    private static function appWordpress(string $dir, string $domain): string
    {
        [$db, $user, $pass] = self::createAppDb($domain);
        $tmp = '/tmp/wp-'.bin2hex(random_bytes(4));
        mkdir($tmp, 0755, true);

        $dl = self::run(['curl', '-fsSL', 'https://wordpress.org/latest.tar.gz', '-o', "{$tmp}/wp.tgz"], 300);
        if (! $dl->successful()) {
            throw new \RuntimeException('WordPress download failed.');
        }
        self::run(['tar', '-xzf', "{$tmp}/wp.tgz", '-C', $tmp], 120);
        self::run(['bash', '-c', 'cp -a '.escapeshellarg("{$tmp}/wordpress").'/. '.escapeshellarg($dir).'/']);
        @unlink("{$dir}/index.html");

        $salts = trim(self::run(['curl', '-fsSL', 'https://api.wordpress.org/secret-key/1.1/salt/'])->output());
        if ($salts === '') {
            $salts = '';
            foreach (['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'] as $k) {
                $salts .= "define('{$k}', '".bin2hex(random_bytes(32))."');\n";
            }
        }
        $cfg = "<?php\n".
            "define('DB_NAME', '{$db}');\n".
            "define('DB_USER', '{$user}');\n".
            "define('DB_PASSWORD', '{$pass}');\n".
            "define('DB_HOST', 'localhost');\n".
            "define('DB_CHARSET', 'utf8mb4');\n".
            "define('DB_COLLATE', '');\n".
            $salts.
            "\$table_prefix = 'wp_';\n".
            "define('WP_DEBUG', false);\n".
            "if (! defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/'); }\n".
            "require_once ABSPATH . 'wp-settings.php';\n";
        file_put_contents("{$dir}/wp-config.php", $cfg);
        self::run(['rm', '-rf', $tmp]);

        return "WordPress installed · finish setup at http://{$domain}/";
    }

    private static function appPhpMyAdmin(string $dir): string
    {
        $ver = '5.2.1';
        $tmp = '/tmp/pma-'.bin2hex(random_bytes(4));
        mkdir($tmp, 0755, true);
        $url = "https://files.phpmyadmin.net/phpMyAdmin/{$ver}/phpMyAdmin-{$ver}-all-languages.tar.gz";
        $dl = self::run(['curl', '-fsSL', $url, '-o', "{$tmp}/pma.tgz"], 300);
        if (! $dl->successful()) {
            throw new \RuntimeException('phpMyAdmin download failed.');
        }
        self::run(['tar', '-xzf', "{$tmp}/pma.tgz", '-C', $tmp], 120);
        self::run(['bash', '-c', 'cp -a '.escapeshellarg("{$tmp}/phpMyAdmin-{$ver}-all-languages")."/. ".escapeshellarg($dir).'/']);
        @unlink("{$dir}/index.html");
        $blowfish = bin2hex(random_bytes(16));
        if (is_file("{$dir}/config.sample.inc.php")) {
            $cfg = (string) file_get_contents("{$dir}/config.sample.inc.php");
            $cfg = preg_replace("/\\\$cfg\['blowfish_secret'\] = '';/", "\$cfg['blowfish_secret'] = '{$blowfish}';", $cfg);
            file_put_contents("{$dir}/config.inc.php", $cfg);
        }
        self::run(['rm', '-rf', $tmp]);

        return "phpMyAdmin {$ver} installed";
    }

    private static function appConvoro(string $dir, string $domain): string
    {
        [$db, $user, $pass] = self::createAppDb($domain);
        $tmp = '/tmp/cv-'.bin2hex(random_bytes(4));
        $clone = self::run(['git', 'clone', '--depth', '1', 'https://github.com/ernestdefoe/convoro.git', $tmp], 300);
        if (! $clone->successful()) {
            throw new \RuntimeException('Convoro clone failed (is the repo public?).');
        }
        self::run(['bash', '-c', 'cp -a '.escapeshellarg($tmp).'/. '.escapeshellarg($dir).'/']);
        self::run(['bash', '-c', 'cd '.escapeshellarg($dir).' && COMPOSER_ALLOW_SUPERUSER=1 php8.4 /usr/local/bin/composer install --no-dev --no-interaction 2>&1'], 600);
        if (is_file("{$dir}/.env.example")) {
            copy("{$dir}/.env.example", "{$dir}/.env");
        }
        self::run(['bash', '-c', 'cd '.escapeshellarg($dir).' && php8.4 artisan key:generate --force 2>&1']);
        $env = "{$dir}/.env";
        if (is_file($env)) {
            $c = (string) file_get_contents($env);
            $c = preg_replace('/^DB_CONNECTION=.*/m', 'DB_CONNECTION=mysql', $c);
            $c = preg_replace('/^DB_DATABASE=.*/m', "DB_DATABASE={$db}", $c);
            $c = preg_replace('/^DB_USERNAME=.*/m', "DB_USERNAME={$user}", $c);
            $c = preg_replace('/^DB_PASSWORD=.*/m', "DB_PASSWORD={$pass}", $c);
            file_put_contents($env, $c);
        }
        self::run(['bash', '-c', 'cd '.escapeshellarg($dir).' && php8.4 artisan migrate --force 2>&1'], 300);
        self::run(['rm', '-rf', $tmp]);

        return "Convoro Forums installed · DB {$db}";
    }

    private static function appFlarum(string $dir, string $domain): string
    {
        [$db, $user, $pass] = self::createAppDb($domain);
        $tmp = '/tmp/fl-'.bin2hex(random_bytes(4));
        // create-project needs an empty target, so build in a temp dir then sync in.
        $cp = self::run(['bash', '-c', 'COMPOSER_ALLOW_SUPERUSER=1 php8.4 /usr/local/bin/composer create-project flarum/flarum '.escapeshellarg($tmp).' --no-interaction --no-dev 2>&1'], 900);
        if (! $cp->successful() || ! is_file("{$tmp}/flarum")) {
            self::run(['rm', '-rf', $tmp]);
            throw new \RuntimeException('Flarum download failed: '.trim(substr($cp->errorOutput().$cp->output(), -160)));
        }
        self::run(['bash', '-c', 'cp -a '.escapeshellarg($tmp).'/. '.escapeshellarg($dir).'/']);
        self::run(['rm', '-rf', $tmp]);

        $adminPass = bin2hex(random_bytes(6));
        $cfg = "/tmp/flarum-{$db}.yml";
        $yml = "debug: false\n".
            "baseUrl: http://{$domain}\n".
            "databaseConfiguration:\n".
            "  driver: mysql\n  host: localhost\n  database: {$db}\n  username: {$user}\n  password: \"{$pass}\"\n  prefix: ''\n".
            "adminUser:\n".
            "  username: admin\n  password: \"{$adminPass}\"\n  password_confirmation: \"{$adminPass}\"\n  email: admin@{$domain}\n".
            "settings:\n  forum_title: \"{$domain}\"\n";
        file_put_contents($cfg, $yml);

        $inst = self::run(['bash', '-c', 'cd '.escapeshellarg($dir).' && php8.4 flarum install -f '.escapeshellarg($cfg).' 2>&1'], 300);
        @unlink($cfg);
        if (! $inst->successful()) {
            throw new \RuntimeException('Flarum install failed: '.trim(substr($inst->errorOutput().$inst->output(), -160)));
        }

        return "Flarum installed · admin / {$adminPass} · DB {$db}";
    }

    // ---- Services -------------------------------------------------------

    /** Services the panel may control. Excludes convorocp-agent (self) for safety. */
    public static function serviceControllable(string $s): bool
    {
        return in_array($s, ['nginx', 'mariadb', 'mysql', 'postgresql', 'docker', 'fail2ban'], true)
            || (bool) preg_match('/^php\d+\.\d+-fpm$/', $s);
    }

    private static function serviceControl(array $args): array
    {
        $svc = (string) ($args['service'] ?? '');
        $action = in_array(($args['action'] ?? ''), ['restart', 'start', 'stop', 'reload'], true) ? $args['action'] : 'restart';
        if (! self::serviceControllable($svc)) {
            throw new \RuntimeException("Service [{$svc}] is not controllable.");
        }
        $r = self::run(['systemctl', $action, $svc]);
        if (! $r->successful()) {
            throw new \RuntimeException("systemctl {$action} {$svc} failed: ".trim($r->errorOutput()));
        }

        return ['applied' => true, 'service' => $svc, 'action' => $action];
    }

    // ---- Backups --------------------------------------------------------

    private static function backupRun(array $args): array
    {
        $id = (int) ($args['backup_id'] ?? 0);
        $kind = ($args['kind'] ?? '') === 'database' ? 'database' : 'site';
        $target = (string) ($args['target'] ?? '');
        if ($kind === 'site') {
            if (! preg_match('/^[a-z0-9.-]+$/i', $target) || str_contains($target, '..')) {
                throw new \RuntimeException('Invalid backup target.');
            }
        } else {
            $target = self::ident($target);
        }

        $dir = '/var/backups/convorocp';
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $ts = date('Ymd-His');

        try {
            if ($kind === 'site') {
                $file = "{$dir}/site-{$target}-{$ts}.tar.gz";
                $r = self::run(['tar', '-czf', $file, '-C', '/var/www/sites', $target], 600);
                if (! $r->successful()) {
                    throw new \RuntimeException(trim($r->errorOutput()));
                }
            } else {
                $engine = self::dbEngine($args);
                $name = self::ident($target);
                $file = "{$dir}/db-{$name}-{$ts}.sql.gz";
                $f = escapeshellarg($file);
                $cmd = $engine === 'pgsql'
                    ? 'sudo -u postgres pg_dump '.escapeshellarg($name)." | gzip > {$f}"
                    : 'mysqldump --single-transaction '.escapeshellarg($name)." | gzip > {$f}";
                $r = self::run(['bash', '-c', $cmd], 600);
                if (! $r->successful()) {
                    throw new \RuntimeException(trim($r->errorOutput()));
                }
            }
            self::run(['chown', '-R', 'www-data:www-data', $dir]);
            self::markBackup($id, 'done', basename($file), @filesize($file) ?: 0);

            // Mirror offsite if the operator has configured S3 (best-effort).
            if (class_exists(\App\Support\Offsite::class) && \App\Support\Offsite::configured()) {
                try {
                    if (\App\Support\Offsite::put($file) && $id) {
                        \App\Models\Backup::where('id', $id)->update(['offsite' => true]);
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        } catch (\Throwable $e) {
            self::markBackup($id, 'failed', null, 0);
            throw $e;
        }

        return ['applied' => true, 'kind' => $kind, 'target' => $target];
    }

    private static function backupRestore(array $args): array
    {
        $kind = ($args['kind'] ?? '') === 'database' ? 'database' : 'site';
        $target = (string) ($args['target'] ?? '');
        $file = '/var/backups/convorocp/'.basename((string) ($args['filename'] ?? ''));
        if (! is_file($file)) {
            throw new \RuntimeException('Backup file not found.');
        }

        if ($kind === 'site') {
            if (! preg_match('/^[a-z0-9.-]+$/i', $target) || str_contains($target, '..')) {
                throw new \RuntimeException('Invalid restore target.');
            }
            $r = self::run(['tar', '-xzf', $file, '-C', '/var/www/sites'], 600);
            if (! $r->successful()) {
                throw new \RuntimeException(trim($r->errorOutput()));
            }
            self::run(['chown', '-R', 'www-data:www-data', "/var/www/sites/{$target}"]);
        } else {
            $engine = self::dbEngine($args);
            $name = self::ident($target);
            $f = escapeshellarg($file);
            $cmd = $engine === 'pgsql'
                ? "gunzip -c {$f} | sudo -u postgres psql ".escapeshellarg($name)
                : "gunzip -c {$f} | mysql ".escapeshellarg($name);
            $r = self::run(['bash', '-c', $cmd], 600);
            if (! $r->successful()) {
                throw new \RuntimeException(trim($r->errorOutput()));
            }
        }

        return ['applied' => true, 'restored' => $kind, 'target' => $target];
    }

    private static function markBackup(int $id, string $status, ?string $filename, int $size): void
    {
        if ($id && class_exists(\App\Models\Backup::class)) {
            \App\Models\Backup::where('id', $id)->update(['status' => $status, 'filename' => $filename, 'size' => $size]);
        }
    }

    private static function dbEngine(array $args): string
    {
        $e = $args['engine'] ?? 'mariadb';

        return in_array($e, ['mariadb', 'mysql', 'pgsql', 'sqlite'], true) ? $e : 'mariadb';
    }

    private static function ident(string $v): string
    {
        if (! preg_match('/^[a-z0-9_]{1,64}$/i', $v)) {
            throw new \RuntimeException('Invalid database identifier.');
        }

        return $v;
    }

    // ---- Docker containers ----------------------------------------------

    private static function containerRun(array $args): array
    {
        $name = self::ident($args['name'] ?? '');
        $image = (string) ($args['image'] ?? '');
        if (! preg_match('#^[a-z0-9][a-z0-9._/-]*(:[a-z0-9._-]+)?$#i', $image)) {
            throw new \RuntimeException('Invalid image reference.');
        }
        $cport = max(1, (int) ($args['container_port'] ?? 80));
        $hport = max(1, (int) ($args['host_port'] ?? 0));
        $restart = in_array(($args['restart'] ?? ''), ['no', 'always', 'on-failure', 'unless-stopped'], true) ? $args['restart'] : 'unless-stopped';
        $cn = "cp-{$name}";

        self::run(['docker', 'rm', '-f', $cn]); // replace if it exists
        $r = self::run(['docker', 'run', '-d', '--name', $cn, '--restart', $restart,
            '-p', "127.0.0.1:{$hport}:{$cport}", $image], 300);
        if (! $r->successful()) {
            throw new \RuntimeException('docker run failed: '.trim($r->errorOutput()));
        }

        $domain = (string) ($args['domain'] ?? '');
        if ($domain !== '') {
            self::domain(['domain' => $domain]);
            self::writeProxyVhost($domain, $hport);
        }

        return ['applied' => true, 'name' => $name, 'image' => $image, 'host_port' => $hport];
    }

    private static function containerStart(array $args): array
    {
        self::run(['docker', 'start', 'cp-'.self::ident($args['name'] ?? '')]);

        return ['applied' => true];
    }

    private static function containerStop(array $args): array
    {
        self::run(['docker', 'stop', 'cp-'.self::ident($args['name'] ?? '')]);

        return ['applied' => true];
    }

    private static function containerRemove(array $args): array
    {
        $name = self::ident($args['name'] ?? '');
        self::run(['docker', 'rm', '-f', "cp-{$name}"]);
        $domain = (string) ($args['domain'] ?? '');
        if ($domain !== '' && preg_match('/^[a-z0-9.-]+$/i', $domain)) {
            @unlink("/etc/nginx/sites-enabled/{$domain}");
            @unlink("/etc/nginx/sites-available/{$domain}");
            self::run(['systemctl', 'reload', 'nginx']);
        }

        return ['applied' => true, 'removed' => true];
    }

    private static function writeProxyVhost(string $domain, int $hport): void
    {
        $conf = "server {\n    listen 80;\n    server_name {$domain} www.{$domain};\n"
            ."    location / {\n        proxy_pass http://127.0.0.1:{$hport};\n"
            ."        proxy_set_header Host \$host;\n        proxy_set_header X-Real-IP \$remote_addr;\n"
            ."        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;\n        proxy_set_header X-Forwarded-Proto \$scheme;\n    }\n}\n";
        $vhost = "/etc/nginx/sites-available/{$domain}";
        file_put_contents($vhost, $conf);
        if (! file_exists("/etc/nginx/sites-enabled/{$domain}")) {
            symlink($vhost, "/etc/nginx/sites-enabled/{$domain}");
        }
        $test = self::run(['nginx', '-t']);
        if (! $test->successful()) {
            @unlink("/etc/nginx/sites-enabled/{$domain}");
            @unlink($vhost);
            throw new \RuntimeException('proxy vhost invalid: '.trim($test->errorOutput()));
        }
        self::run(['systemctl', 'reload', 'nginx']);
    }

    // ---- Cron / scheduler ----------------------------------------------

    private static function cronWrite(array $args): array
    {
        $id = (int) ($args['id'] ?? 0);
        $task = \App\Models\ScheduledTask::find($id);
        if (! $task) {
            return ['skipped' => true, 'note' => 'task not found'];
        }
        $path = "/etc/cron.d/cp-{$id}";

        if (! $task->enabled) {
            @unlink($path);

            return ['applied' => true, 'disabled' => true];
        }

        $cron = trim((string) $task->cron);
        if (! preg_match('/^[\d\*\/,\-\s]+$/', $cron) || count(preg_split('/\s+/', $cron)) !== 5) {
            throw new \RuntimeException('Invalid cron expression.');
        }
        // % is a line separator in crontab — escape so commands survive.
        $command = str_replace(['%', "\n", "\r"], ['\\%', ' ', ' '], (string) $task->command);

        $body = "# ConvoroCP cron #{$id}: {$task->name}\n"
            ."PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin\n"
            ."{$cron} root {$command}\n";
        file_put_contents($path, $body);
        @chmod($path, 0644);

        return ['applied' => true, 'path' => $path];
    }

    private static function cronDelete(array $args): array
    {
        @unlink('/etc/cron.d/cp-'.(int) ($args['id'] ?? 0));

        return ['applied' => true, 'removed' => true];
    }

    private static function cronRunNow(array $args): array
    {
        $id = (int) ($args['id'] ?? 0);
        $task = \App\Models\ScheduledTask::find($id);
        $command = (string) ($task->command ?? $args['command'] ?? '');
        if ($command === '') {
            throw new \RuntimeException('No command to run.');
        }
        $r = self::run(['/bin/bash', '-lc', $command], 120);
        if ($task) {
            $task->update(['last_status' => $r->successful() ? 'success' : 'failed']);
        }

        return [
            'applied' => true,
            'exit' => $r->exitCode(),
            'output' => \Illuminate\Support\Str::limit(trim($r->output()."\n".$r->errorOutput()), 500),
        ];
    }

    // ---- Daemons (systemd units) ---------------------------------------

    private static function daemonCreate(array $args): array
    {
        $id = (int) ($args['id'] ?? 0);
        $d = \App\Models\Daemon::find($id);
        if (! $d) {
            return ['skipped' => true, 'note' => 'daemon not found'];
        }
        $policy = match ($d->restart_policy) {
            'always' => 'always',
            'on-failure' => 'on-failure',
            default => 'no',
        };

        @mkdir('/opt/convorocp/daemons', 0755, true);
        $script = "/opt/convorocp/daemons/cp-{$id}.sh";
        file_put_contents($script, "#!/bin/bash\n".$d->command."\n");
        @chmod($script, 0755);

        $unit = "[Unit]\nDescription=ConvoroCP daemon #{$id}: {$d->name}\nAfter=network.target\n\n"
            ."[Service]\nType=simple\nUser=www-data\nGroup=www-data\nWorkingDirectory=/var/www\n"
            ."ExecStart=/bin/bash {$script}\nRestart={$policy}\nRestartSec=3\n\n"
            ."[Install]\nWantedBy=multi-user.target\n";
        file_put_contents("/etc/systemd/system/cp-daemon-{$id}.service", $unit);

        self::run(['systemctl', 'daemon-reload']);
        $r = self::run(['systemctl', 'enable', '--now', "cp-daemon-{$id}.service"]);
        if (! $r->successful()) {
            throw new \RuntimeException('daemon failed to start: '.trim($r->errorOutput()));
        }

        return ['applied' => true, 'unit' => "cp-daemon-{$id}.service"];
    }

    private static function daemonStart(array $args): array
    {
        self::run(['systemctl', 'start', 'cp-daemon-'.(int) ($args['id'] ?? 0).'.service']);

        return ['applied' => true];
    }

    private static function daemonStop(array $args): array
    {
        self::run(['systemctl', 'stop', 'cp-daemon-'.(int) ($args['id'] ?? 0).'.service']);

        return ['applied' => true];
    }

    private static function daemonRestart(array $args): array
    {
        self::run(['systemctl', 'restart', 'cp-daemon-'.(int) ($args['id'] ?? 0).'.service']);

        return ['applied' => true];
    }

    private static function daemonDelete(array $args): array
    {
        $id = (int) ($args['id'] ?? 0);
        self::run(['systemctl', 'disable', '--now', "cp-daemon-{$id}.service"]);
        @unlink("/etc/systemd/system/cp-daemon-{$id}.service");
        @unlink("/opt/convorocp/daemons/cp-{$id}.sh");
        self::run(['systemctl', 'daemon-reload']);

        return ['applied' => true, 'removed' => true];
    }

    // ---- Docker engine --------------------------------------------------

    private static function dockerInstall(array $args): array
    {
        $present = trim((string) self::run(['bash', '-lc', 'command -v docker'])->output()) !== '';
        if ($present) {
            self::run(['systemctl', 'enable', '--now', 'docker']);

            return ['applied' => true, 'already' => true];
        }

        self::run(['apt-get', 'update', '-q'], 300, ['DEBIAN_FRONTEND' => 'noninteractive']);
        $r = self::run(['apt-get', 'install', '-y', '-q', 'docker.io'], 600, ['DEBIAN_FRONTEND' => 'noninteractive']);
        if (! $r->successful()) {
            throw new \RuntimeException('docker install failed: '.trim($r->errorOutput()));
        }
        self::run(['systemctl', 'enable', '--now', 'docker']);

        return ['applied' => true, 'version' => trim((string) self::run(['bash', '-lc', 'docker --version'])->output())];
    }

    // ---- Raw config editors (nginx vhost / php.ini) --------------------

    private static function nginxWrite(array $args): array
    {
        $domain = self::domain($args);
        $content = (string) ($args['content'] ?? '');
        if (trim($content) === '') {
            throw new \RuntimeException('Refusing to write an empty nginx config.');
        }
        $path = "/etc/nginx/sites-available/{$domain}";
        $backup = $path.'.cpbak';
        if (is_file($path)) {
            copy($path, $backup);
        }
        file_put_contents($path, rtrim($content, "\n")."\n");
        if (! file_exists("/etc/nginx/sites-enabled/{$domain}")) {
            @symlink($path, "/etc/nginx/sites-enabled/{$domain}");
        }

        $test = self::run(['nginx', '-t']);
        if (! $test->successful()) {
            if (is_file($backup)) {
                copy($backup, $path);
            } else {
                @unlink($path);
                @unlink("/etc/nginx/sites-enabled/{$domain}");
            }

            throw new \RuntimeException('nginx config invalid — reverted: '.trim($test->errorOutput()));
        }
        self::run(['systemctl', 'reload', 'nginx']);

        return ['applied' => true, 'path' => $path];
    }

    private static function phpIniWrite(array $args): array
    {
        $v = (string) ($args['version'] ?? '');
        if (! in_array($v, self::installedVersions(), true)) {
            throw new \RuntimeException('Unknown PHP version.');
        }
        $content = (string) ($args['content'] ?? '');
        if (trim($content) === '') {
            throw new \RuntimeException('Refusing to write an empty php.ini.');
        }
        $path = "/etc/php/{$v}/fpm/php.ini";
        $backup = $path.'.cpbak';
        if (is_file($path)) {
            copy($path, $backup);
        }
        file_put_contents($path, $content);

        $r = self::run(['systemctl', 'reload', "php{$v}-fpm"]);
        if (! $r->successful()) {
            if (is_file($backup)) {
                copy($backup, $path);
                self::run(['systemctl', 'reload', "php{$v}-fpm"]);
            }

            throw new \RuntimeException("php{$v}-fpm reload failed — reverted: ".trim($r->errorOutput()));
        }

        return ['applied' => true, 'path' => $path];
    }

    // ---- Templates / helpers --------------------------------------------

    private static function domain(array $args): string
    {
        $domain = (string) ($args['domain'] ?? '');
        if (! preg_match('/^[a-z0-9.-]+$/i', $domain) || str_contains($domain, '..')) {
            throw new \RuntimeException('Invalid domain.');
        }

        return $domain;
    }

    private static function fpmPool(string $domain, string $sock, array $settings = []): string
    {
        $s = array_merge([
            'memory_limit' => '256M', 'upload_max_filesize' => '64M', 'post_max_size' => '64M',
            'max_execution_time' => 30, 'display_errors' => false, 'disable_functions' => [],
        ], $settings);

        $lines = [
            "[{$domain}]", 'user = www-data', 'group = www-data',
            "listen = {$sock}", 'listen.owner = www-data', 'listen.group = www-data',
            'pm = ondemand', 'pm.max_children = 5', 'pm.process_idle_timeout = 10s', 'pm.max_requests = 500',
            'php_admin_value[memory_limit] = '.self::iniSize($s['memory_limit']),
            'php_value[upload_max_filesize] = '.self::iniSize($s['upload_max_filesize']),
            'php_value[post_max_size] = '.self::iniSize($s['post_max_size']),
            'php_value[max_execution_time] = '.max(0, (int) $s['max_execution_time']),
            'php_admin_flag[display_errors] = '.($s['display_errors'] ? 'on' : 'off'),
        ];
        $fns = array_values(array_filter((array) ($s['disable_functions'] ?? []), fn ($f) => preg_match('/^[a-z_]+$/i', (string) $f)));
        if ($fns) {
            $lines[] = 'php_admin_value[disable_functions] = '.implode(',', $fns);
        }

        return implode("\n", $lines)."\n";
    }

    private static function iniSize($v): string
    {
        return preg_match('/^\d+[KMG]?$/i', (string) $v) ? (string) $v : '128M';
    }

    private static function nginxConf(string $domain, string $root, string $runtime, ?string $sock): string
    {
        $phpLoc = '';
        if ($runtime === 'php') {
            $phpLoc = "\n    location ~ \\.php\$ {\n        include snippets/fastcgi-php.conf;\n        fastcgi_pass unix:{$sock};\n    }";
        }
        $tryFiles = $runtime === 'php' ? "try_files \$uri \$uri/ /index.php?\$query_string;" : "try_files \$uri \$uri/ =404;";

        return "server {\n    listen 80;\n    server_name {$domain} www.{$domain};\n    root {$root};\n"
            ."    index index.php index.html;\n    location / { {$tryFiles} }{$phpLoc}\n    location ~ /\\.ht { deny all; }\n}\n";
    }

    private static function run(array $cmd, int $timeout = 60, array $env = [])
    {
        $p = Process::timeout($timeout);
        if ($env) {
            $p = $p->env($env);
        }

        return $p->run($cmd);
    }

    private static function describe(string $op, array $args): string
    {
        $d = $args['domain'] ?? $args['name'] ?? ($args['id'] ?? '');

        return match (true) {
            $op === 'site.create' => "render nginx vhost + PHP-FPM pool for {$d}, reload",
            $op === 'site.adopt' => "adopt existing app at ".($args['path'] ?? '')." as {$d} (symlink only)",
            $op === 'site.detach' => "detach {$d} from the panel (remove symlink only)",
            $op === 'site.delete' => "remove vhost + pool + webroot for {$d}",
            $op === 'site.set_php_version' => "point {$d} FPM pool at PHP ".($args['php'] ?? '?'),
            $op === 'site.set_php_settings' => "apply PHP/INI settings to {$d}",
            $op === 'site.deploy' => "git deploy {$d} from ".($args['repo'] ?? 'repo'),
            str_starts_with($op, 'cert.') => 'ACME '.substr($op, 5)." certificate for {$d}",
            str_starts_with($op, 'db.') => "{$op} on the ".($args['engine'] ?? '?')." engine ({$d})",
            str_starts_with($op, 'dns.') => "write + reload the zone for {$d}",
            str_starts_with($op, 'cron.') => "write systemd timer for task {$d}",
            str_starts_with($op, 'daemon.') => "write/control systemd unit for daemon {$d}",
            str_starts_with($op, 'container.') => substr($op, 10)." docker container ".($args['name'] ?? ''),
            $op === 'backup.run' => "back up ".($args['kind'] ?? '')." ".($args['target'] ?? ''),
            $op === 'backup.restore' => "restore ".($args['kind'] ?? '')." ".($args['target'] ?? ''),
            $op === 'mail.account_create' => "create mailbox ".($args['email'] ?? ''),
            $op === 'mail.account_delete' => "delete mailbox ".($args['email'] ?? ''),
            $op === 'panel.update' => "update ConvoroCP to ".($args['tag'] ?? ''),
            $op === 'service.control' => ($args['action'] ?? 'restart').' '.($args['service'] ?? ''),
            str_starts_with($op, 'firewall.') => 'ufw '.substr($op, 9).' '.($args['port'] ?? ''),
            $op === 'fail2ban.install' => 'install + configure fail2ban',
            str_starts_with($op, 'fail2ban.') => 'fail2ban '.substr($op, 9).' '.($args['ip'] ?? ''),
            $op === 'php.install' => 'install PHP '.($args['version'] ?? '?').' (apt)',
            $op === 'php.uninstall' => 'remove PHP '.($args['version'] ?? '?'),
            $op === 'docker.install' => 'install the Docker engine (apt)',
            $op === 'nginx.write' => "write + test + reload nginx vhost for {$d}",
            $op === 'php.ini.write' => 'write php.ini for PHP '.($args['version'] ?? '?').' + reload',
            default => "apply {$op}",
        };
    }
}
