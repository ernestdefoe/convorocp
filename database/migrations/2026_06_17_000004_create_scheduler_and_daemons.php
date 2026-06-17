<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Desired state for scheduled tasks (→ systemd timers) and supervised daemons. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('command');
            $table->string('cron')->default('0 3 * * *');
            $table->boolean('enabled')->default(true);
            $table->string('last_status')->nullable();   // ok | failed
            $table->timestamps();
        });

        Schema::create('daemons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('command');
            $table->string('status')->default('stopped'); // running | stopped
            $table->boolean('autostart')->default(true);
            $table->string('restart_policy')->default('always');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daemons');
        Schema::dropIfExists('scheduled_tasks');
    }
};
