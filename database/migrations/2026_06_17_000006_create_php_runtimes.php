<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PHP runtimes the operator can offer. The catalog is the set ondrej/php ships;
 * each row tracks whether it's installed on the node. Seeded here and auto-marked
 * "installed" for versions already present in /etc/php (so the box reflects reality
 * after a plain migrate).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('php_runtimes', function (Blueprint $table) {
            $table->id();
            $table->string('version')->unique();
            $table->string('status')->default('available'); // available | installing | installed | removing
            $table->timestamps();
        });

        foreach (['8.4', '8.3', '8.2', '8.1'] as $v) {
            DB::table('php_runtimes')->insert([
                'version' => $v,
                'status' => is_dir("/etc/php/{$v}/fpm") ? 'installed' : 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('php_runtimes');
    }
};
