<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-site document root ("home"). NULL means the convention
 * /var/www/sites/{domain}/public; a value points nginx at a custom path
 * (e.g. a framework's public/ subdir or an adopted app's home).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('docroot')->nullable()->after('runtime');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('docroot');
        });
    }
};
