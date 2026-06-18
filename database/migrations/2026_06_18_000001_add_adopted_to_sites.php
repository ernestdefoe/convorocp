<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            // An adopted site points at an existing app/vhost ConvoroCP did NOT
            // provision. The panel manages it for safe ops only and never
            // provisions/rewrites/deletes its files or vhost.
            $table->boolean('adopted')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('adopted');
        });
    }
};
