<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Firewall (ufw) rules as desired state + a tiny key/value settings store. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firewall_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('port');
            $table->string('proto')->default('tcp');   // tcp | udp
            $table->string('action')->default('allow'); // allow | deny
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Safe defaults so enabling the firewall never locks SSH/web out.
        foreach ([[22, 'SSH'], [80, 'HTTP'], [443, 'HTTPS']] as [$port, $note]) {
            DB::table('firewall_rules')->insert([
                'port' => $port, 'proto' => 'tcp', 'action' => 'allow', 'note' => $note,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('firewall_rules');
        Schema::dropIfExists('settings');
    }
};
