<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `sales.status` was varchar(16) but the `partially_refunded` enum
 * literal is 18 chars and truncates on update — same family of bug
 * we hit on shifts.status / cash_drawer_entries.type. Bump to varchar(32)
 * so every Sale::STATUS_* member fits with headroom. `sale_returns.status`
 * is widened in the same migration so the two tables stay in lockstep.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('status', 32)->default('draft')->change();
        });

        Schema::table('sale_returns', function (Blueprint $table) {
            $table->string('status', 32)->default('draft')->change();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('status', 16)->default('draft')->change();
        });

        Schema::table('sale_returns', function (Blueprint $table) {
            $table->string('status', 16)->default('draft')->change();
        });
    }
};
