<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Markup workflow — slice 1.
 *
 * Adds:
 *   - `products.markup_percent` (DECIMAL 7,4 nullable) — owner-defined
 *     markup over cost. Null means "no auto-update; owner manages
 *     selling price manually."
 *   - `company.auto_apply_markup_on_receive` (boolean default false) —
 *     opt-in for owners who want the receive-purchase action to bump
 *     `products.selling_price` whenever a product is received at a new
 *     cost AND has a `markup_percent` set.
 *
 * Selling price is still owner-managed by default. The toggle lets the
 * common "cost moved → bump price by the same markup %" case happen in
 * one place (purchase receive) instead of forcing a product-page round
 * trip after every restock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('markup_percent', 7, 4)->nullable()->after('mrp');
        });

        Schema::table('company', function (Blueprint $table) {
            $table->boolean('auto_apply_markup_on_receive')->default(false)->after('composition_rate_percent');
        });
    }

    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->dropColumn('auto_apply_markup_on_receive');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('markup_percent');
        });
    }
};
