<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A points-redemption is deliberately its OWN header field, not folded
 * into `discount_total` — a regular discount reduces the taxable base
 * (see PriceCart), which would wrongly shrink tax owed on a purchase
 * partly paid with points. `points_redeemed_value` is subtracted from
 * `grand_total` directly, after tax is already computed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedInteger('points_redeemed')->nullable()->after('discount_total');
            $table->decimal('points_redeemed_value', 15, 4)->nullable()->after('points_redeemed');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['points_redeemed', 'points_redeemed_value']);
        });
    }
};
