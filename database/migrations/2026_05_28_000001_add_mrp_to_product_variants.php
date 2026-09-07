<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Variant-level MRP. Mirrors `products.mrp` so a variant can carry its
 * own Maximum Retail Price (and per-store MRP overrides have a base to
 * inherit from). Nullable — falls through to the product's MRP when null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('mrp', 15, 4)->nullable()->after('selling_price');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('mrp');
        });
    }
};
