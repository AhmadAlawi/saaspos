<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A third pricing tier below `mrp` (struck-through reference) and
 * `selling_price` (regular price): an optional, currently-active
 * promotional price. When set, it's what's actually charged at
 * checkout — `selling_price` itself then becomes the reference that
 * gets struck through, same pattern `mrp` already uses one level up.
 *
 * Exists specifically for items priced below their recorded cost as a
 * deliberate promo/loss-leader — `selling_price` still has to satisfy
 * the cost <= selling_price import/edit guard, so the real (lower)
 * charge price lives here instead of forcing that guard to bend.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('sale_price', 15, 4)->nullable()->after('selling_price');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('sale_price', 15, 4)->nullable()->after('selling_price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('sale_price');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('sale_price');
        });
    }
};
