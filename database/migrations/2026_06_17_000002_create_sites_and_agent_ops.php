<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `sites` is desired state — what the control plane wants the box to look like.
 * `agent_operations` is the queue the privileged agent drains to converge the
 * machine to that state (see docs/agent-protocol.md). The web tier never touches
 * the OS; it records intent here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->string('runtime')->default('php');        // php | node | static
            $table->string('php_version')->nullable();        // e.g. 8.3
            $table->string('status')->default('active');      // active | deploying | disabled
            $table->string('ssl_status')->default('none');    // active | pending | none
            $table->string('repo')->nullable();
            $table->string('branch')->default('main');
            $table->boolean('auto_deploy')->default(true);
            $table->timestamps();
        });

        Schema::create('agent_operations', function (Blueprint $table) {
            $table->id();
            $table->string('op');                             // allowlisted op name
            $table->json('args');
            $table->string('status')->default('pending');     // pending | running | done | error
            $table->json('result')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_operations');
        Schema::dropIfExists('sites');
    }
};
