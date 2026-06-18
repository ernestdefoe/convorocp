<?php

namespace App\Support;

use App\Models\Container;
use App\Models\Database;
use App\Models\MailAccount;
use App\Models\PhpRuntime;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Process;

/**
 * A full picture of the managed node — identity, live metrics, resource counts
 * and service health — surfaced on the operator Overview.
 */
class NodeInfo
{
    public static function detail(): array
    {
        $os = '';
        foreach (explode("\n", (string) @file_get_contents('/etc/os-release')) as $line) {
            if (preg_match('/^PRETTY_NAME="?([^"]+)"?/', $line, $m)) {
                $os = $m[1];
            }
        }

        return [
            'name' => gethostname() ?: 'node-01',
            'os' => $os ?: PHP_OS,
            'kernel' => php_uname('r'),
            'php' => PHP_VERSION,
            'metrics' => ServerMetrics::snapshot(),
            'counts' => [
                'sites' => Site::count(),
                'databases' => Database::count(),
                'containers' => class_exists(Container::class) ? Container::count() : 0,
                'mailboxes' => class_exists(MailAccount::class) ? MailAccount::count() : 0,
                'customers' => User::where('role', 'client')->count(),
            ],
            'services' => self::services(),
        ];
    }

    private static function services(): array
    {
        $list = [['nginx', 'Nginx'], ['convorocp-agent', 'Agent']];
        foreach (PhpRuntime::installed() as $v) {
            $list[] = ["php{$v}-fpm", "PHP {$v}"];
        }
        foreach ([
            'mariadb' => 'MariaDB', 'postgresql' => 'PostgreSQL', 'docker' => 'Docker',
            'fail2ban' => 'fail2ban', 'dovecot' => 'Dovecot', 'postfix' => 'Postfix',
            'convorocp-terminal' => 'Terminal',
        ] as $name => $label) {
            $list[] = [$name, $label];
        }

        return collect($list)->map(fn ($s) => [
            'name' => $s[0],
            'label' => $s[1],
            'status' => trim(Process::timeout(5)->run(['systemctl', 'is-active', $s[0]])->output()) ?: 'unknown',
        ])->values()->all();
    }
}
