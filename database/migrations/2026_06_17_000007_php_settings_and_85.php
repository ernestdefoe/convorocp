<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Per-site PHP/INI settings + adds PHP 8.5 to the runtime catalog. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->json('php_settings')->nullable()->after('php_version');
        });

        if (Schema::hasTable('php_runtimes') && ! DB::table('php_runtimes')->where('version', '8.5')->exists()) {
            DB::table('php_runtimes')->insert([
                'version' => '8.5',
                'status' => is_dir('/etc/php/8.5/fpm') ? 'installed' : 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('php_settings');
        });
    }
};
