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
        $runtime = in_array(($args['runtime'] ?? 'php'), ['php', 'static', 'node'], true) ? $args['runtime'] : 'php';
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

    private static function siteDelete(array $args): array
    {
        $domain = self::domain($args);
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
            $op === 'site.delete' => "remove vhost + pool + webroot for {$d}",
            $op === 'site.set_php_version' => "point {$d} FPM pool at PHP ".($args['php'] ?? '?'),
            $op === 'site.set_php_settings' => "apply PHP/INI settings to {$d}",
            $op === 'site.deploy' => "git deploy {$d} from ".($args['repo'] ?? 'repo'),
            str_starts_with($op, 'cert.') => 'ACME '.substr($op, 5)." certificate for {$d}",
            str_starts_with($op, 'db.') => "{$op} on the ".($args['engine'] ?? '?')." engine ({$d})",
            str_starts_with($op, 'dns.') => "write + reload the zone for {$d}",
            str_starts_with($op, 'cron.') => "write systemd timer for task {$d}",
            str_starts_with($op, 'daemon.') => "write/control systemd unit for daemon {$d}",
            $op === 'php.install' => 'install PHP '.($args['version'] ?? '?').' (apt)',
            $op === 'php.uninstall' => 'remove PHP '.($args['version'] ?? '?'),
            default => "apply {$op}",
        };
    }
}
