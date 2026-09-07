<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a refund exist with NO original sale — a cashier scans a barcode
 * with no invoice in hand, so there's no `sales.id` / `sale_items.id` to
 * hang the return off. `sale_returns.sale_id` and
 * `sale_return_items.sale_item_id` become nullable; blind lines carry
 * their own product/variant/name/sku/barcode snapshot instead of
 * pointing at an original line (`unit_price_snapshot`/`tax_amount` on
 * `sale_return_items` already exist and apply to both kinds of line).
 *
 * @see \App\Actions\Sales\RecordBlindReturn
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_returns', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
        });
        Schema::table('sale_returns', function (Blueprint $table) {
            $table->foreignId('sale_id')->nullable()->change();
            $table->boolean('is_blind')->default(false)->after('sale_id');
        });
        Schema::table('sale_returns', function (Blueprint $table) {
            $table->foreign('sale_id')->references('id')->on('sales')->restrictOnDelete();
        });

        Schema::table('sale_return_items', function (Blueprint $table) {
            $table->dropForeign(['sale_item_id']);
        });
        Schema::table('sale_return_items', function (Blueprint $table) {
            $table->foreignId('sale_item_id')->nullable()->change();
            $table->unsignedBigInteger('product_id')->nullable()->after('sale_item_id');
            $table->unsignedBigInteger('variant_id')->nullable()->after('product_id');
            $table->string('sku_snapshot', 191)->nullable()->after('variant_id');
            $table->string('barcode_snapshot', 191)->nullable()->after('sku_snapshot');
            $table->string('name_snapshot', 191)->nullable()->after('barcode_snapshot');
        });
        Schema::table('sale_return_items', function (Blueprint $table) {
            $table->foreign('sale_item_id')->references('id')->on('sale_items')->restrictOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sale_return_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['variant_id']);
            $table->dropForeign(['sale_item_id']);
        });
        Schema::table('sale_return_items', function (Blueprint $table) {
            $table->dropColumn(['product_id', 'variant_id', 'sku_snapshot', 'barcode_snapshot', 'name_snapshot']);
            $table->foreignId('sale_item_id')->nullable(false)->change();
        });
        Schema::table('sale_return_items', function (Blueprint $table) {
            $table->foreign('sale_item_id')->references('id')->on('sale_items')->restrictOnDelete();
        });

        Schema::table('sale_returns', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
        });
        Schema::table('sale_returns', function (Blueprint $table) {
            $table->dropColumn('is_blind');
            $table->foreignId('sale_id')->nullable(false)->change();
        });
        Schema::table('sale_returns', function (Blueprint $table) {
            $table->foreign('sale_id')->references('id')->on('sales')->restrictOnDelete();
        });
    }
};
