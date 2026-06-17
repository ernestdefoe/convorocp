<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Site (tar.gz) and database (sql.gz) backups stored on the node. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind');               // site | database
            $table->string('target');             // domain or db name
            $table->string('engine')->nullable(); // for database backups
            $table->string('filename')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('status')->default('pending'); // pending | done | failed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
