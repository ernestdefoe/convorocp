<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Role-scopes every account. ConvoroCP is multi-tenant: `operator` accounts run
 * the hosting business (customers, plans, nodes, billing); `client` accounts are
 * the customers who only see their own services. Drives which surface a user
 * lands on and gates every capability.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('client')->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
