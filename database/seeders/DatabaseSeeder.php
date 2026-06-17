<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(['email' => 'operator@convorocp.test'], [
            'name' => 'Ernest (Operator)',
            'role' => 'operator',
            'password' => bcrypt('password'),
        ]);

        $client = User::updateOrCreate(['email' => 'client@convorocp.test'], [
            'name' => 'Daniel Okafor',
            'role' => 'client',
            'password' => bcrypt('password'),
        ]);

        foreach ([
            ['domain' => 'danielokafor.com', 'runtime' => 'php', 'php_version' => '8.5', 'ssl_status' => 'active'],
            ['domain' => 'shop.danielokafor.com', 'runtime' => 'node', 'php_version' => null, 'ssl_status' => 'active'],
            ['domain' => 'blog.danielokafor.com', 'runtime' => 'php', 'php_version' => '8.3', 'ssl_status' => 'active'],
        ] as $site) {
            \App\Models\Site::updateOrCreate(['domain' => $site['domain']], $site + [
                'user_id' => $client->id,
                'status' => 'active',
            ]);
        }

        foreach ([
            ['name' => 'danielokafor', 'engine' => 'mariadb', 'db_user' => 'danielokafor_user'],
            ['name' => 'shop_prod', 'engine' => 'pgsql', 'db_user' => 'shop_prod_user'],
        ] as $db) {
            \App\Models\Database::updateOrCreate(['name' => $db['name']], $db + ['user_id' => $client->id]);
        }

        foreach ([
            ['type' => 'A', 'name' => '@', 'value' => '86.48.28.240', 'ttl' => 300],
            ['type' => 'CNAME', 'name' => 'www', 'value' => 'danielokafor.com.', 'ttl' => 3600],
            ['type' => 'MX', 'name' => '@', 'value' => '10 mail.danielokafor.com.', 'ttl' => 3600],
            ['type' => 'TXT', 'name' => '@', 'value' => 'v=spf1 mx ~all', 'ttl' => 3600],
        ] as $rec) {
            \App\Models\DnsRecord::updateOrCreate(
                ['domain' => 'danielokafor.com', 'type' => $rec['type'], 'name' => $rec['name']],
                $rec + ['domain' => 'danielokafor.com', 'user_id' => $client->id],
            );
        }

        foreach ([
            ['name' => 'Nightly database backup', 'command' => 'php artisan backup:run', 'cron' => '0 3 * * *'],
            ['name' => 'Prune old logs', 'command' => 'php artisan log:prune', 'cron' => '0 4 * * 0'],
        ] as $task) {
            \App\Models\ScheduledTask::updateOrCreate(['name' => $task['name'], 'user_id' => $client->id], $task + ['user_id' => $client->id]);
        }

        foreach ([
            ['name' => 'queue-worker', 'command' => 'php artisan queue:work', 'status' => 'running'],
            ['name' => 'reverb', 'command' => 'php artisan reverb:start', 'status' => 'running'],
        ] as $d) {
            \App\Models\Daemon::updateOrCreate(['name' => $d['name'], 'user_id' => $client->id], $d + ['user_id' => $client->id, 'restart_policy' => 'always']);
        }
    }
}
