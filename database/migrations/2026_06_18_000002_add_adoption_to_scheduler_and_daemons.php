<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets ConvoroCP "adopt" pre-existing crons + daemons it didn't create
 * (mirrors adopted sites): the panel monitors + controls the real unit/file
 * without generating a duplicate that would clash with the running service.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_tasks', function (Blueprint $table) {
            $table->boolean('adopted')->default(false);
            $table->string('cron_file')->nullable(); // existing /etc/cron.d path when adopted
        });

        Schema::table('daemons', function (Blueprint $table) {
            $table->boolean('adopted')->default(false);
            $table->string('unit')->nullable(); // existing systemd unit when adopted
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_tasks', function (Blueprint $table) {
            $table->dropColumn(['adopted', 'cron_file']);
        });
        Schema::table('daemons', function (Blueprint $table) {
            $table->dropColumn(['adopted', 'unit']);
        });
    }
};
