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

        User::updateOrCreate(['email' => 'client@convorocp.test'], [
            'name' => 'Daniel Okafor',
            'role' => 'client',
            'password' => bcrypt('password'),
        ]);
    }
}
