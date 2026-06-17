<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Desired state for provisioned databases (any engine) and DNS zone records. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('databases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->unique();
            $table->string('engine');                 // mariadb | mysql | pgsql | sqlite
            $table->string('db_user')->nullable();
            $table->timestamps();
        });

        Schema::create('dns_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->string('type');                   // A | AAAA | CNAME | MX | TXT
            $table->string('name')->default('@');
            $table->text('value');
            $table->unsignedInteger('ttl')->default(3600);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dns_records');
        Schema::dropIfExists('databases');
    }
};
