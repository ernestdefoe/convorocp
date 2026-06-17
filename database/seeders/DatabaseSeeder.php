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
    }
}
