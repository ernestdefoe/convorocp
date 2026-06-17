<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Docker containers deployed from Docker Hub (optionally proxied to a domain). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('containers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->unique();
            $table->string('image');                      // e.g. nginxdemos/hello:latest
            $table->unsignedInteger('container_port')->default(80);
            $table->unsignedInteger('host_port')->unique();
            $table->string('domain')->nullable();
            $table->string('restart_policy')->default('unless-stopped');
            $table->string('status')->default('running'); // running | stopped
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('containers');
    }
};
