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
    ];

    /** PHP versions actually installed on this node. */
    private const PHP_VERSIONS = ['8.3', '8.4'];

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
        $php = in_array(($args['php'] ?? ''), self::PHP_VERSIONS, true) ? $args['php'] : self::PHP_VERSIONS[0];

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
            file_put_contents("/etc/php/{$php}/fpm/pool.d/{$domain}.conf", self::fpmPool($domain, $sock));
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

    // ---- Templates / helpers --------------------------------------------

    private static function fpmPool(string $domain, string $sock): string
    {
        return "[{$domain}]\nuser = www-data\ngroup = www-data\n"
            ."listen = {$sock}\nlisten.owner = www-data\nlisten.group = www-data\n"
            ."pm = ondemand\npm.max_children = 5\npm.process_idle_timeout = 10s\npm.max_requests = 500\n";
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

    private static function run(array $cmd)
    {
        return Process::timeout(60)->run($cmd);
    }

    private static function describe(string $op, array $args): string
    {
        $d = $args['domain'] ?? $args['name'] ?? ($args['id'] ?? '');

        return match (true) {
            $op === 'site.create' => "render nginx vhost + PHP-FPM pool for {$d}, reload",
            $op === 'site.delete' => "remove vhost + pool + webroot for {$d}",
            $op === 'site.set_php_version' => "point {$d} FPM pool at PHP ".($args['php'] ?? '?'),
            str_starts_with($op, 'cert.') => 'ACME '.substr($op, 5)." certificate for {$d}",
            str_starts_with($op, 'db.') => "{$op} on the ".($args['engine'] ?? '?')." engine ({$d})",
            str_starts_with($op, 'dns.') => "write + reload the zone for {$d}",
            str_starts_with($op, 'cron.') => "write systemd timer for task {$d}",
            str_starts_with($op, 'daemon.') => "write/control systemd unit for daemon {$d}",
            default => "apply {$op}",
        };
    }
}
