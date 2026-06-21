<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('kind');              // site | database
            $table->string('target');            // a domain/db name, or '*' for all of that kind
            $table->string('engine')->nullable();
            $table->string('frequency')->default('daily'); // daily | weekly
            $table->unsignedInteger('retention')->default(3);
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });

        Schema::table('backups', function (Blueprint $table) {
            $table->boolean('offsite')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_schedules');
        Schema::table('backups', function (Blueprint $table) {
            $table->dropColumn('offsite');
        });
    }
};
