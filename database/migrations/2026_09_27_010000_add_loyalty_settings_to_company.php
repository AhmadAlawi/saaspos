<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company-wide loyalty program configuration. Lives here (not on
 * `stores`) because `customers.loyalty_points` is already modeled as
 * one cross-store balance per customer — a per-store rate would be
 * inconsistent with that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->boolean('loyalty_enabled')->default(false)->after('block_expired_batch_sale');
            // Points earned per 1 unit of a completed sale's subtotal
            // (post-discount, pre-tax) — e.g. 1.0000 = "1 point per JOD".
            $table->decimal('loyalty_earn_rate', 8, 4)->default(1)->after('loyalty_enabled');
            // Points required to redeem 1 unit of discount at checkout —
            // e.g. 100.0000 = "100 points = 1 JOD off".
            $table->decimal('loyalty_redeem_rate', 8, 4)->default(100)->after('loyalty_earn_rate');
        });
    }

    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->dropColumn(['loyalty_enabled', 'loyalty_earn_rate', 'loyalty_redeem_rate']);
        });
    }
};
