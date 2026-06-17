<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Hosting plans the operator sells; clients subscribe to one at signup. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('price_cents')->default(0);
            $table->unsignedInteger('sites_limit')->default(1);
            $table->unsignedInteger('db_limit')->default(1);
            $table->unsignedInteger('email_limit')->default(1);
            $table->unsignedInteger('disk_mb')->default(1024);
            $table->boolean('is_public')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('role')->constrained()->nullOnDelete();
            $table->timestamp('subscribed_at')->nullable()->after('plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn('subscribed_at');
        });
        Schema::dropIfExists('plans');
    }
};
