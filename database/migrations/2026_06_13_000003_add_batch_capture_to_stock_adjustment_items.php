<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds new-batch capture to `stock_adjustment_items`. Before this, a
 * line could only reference an *existing* batch via `batch_id`. For an
 * inflow ("In") adjustment that brings fresh stock, the user needs to
 * create a brand-new batch inline — exactly like the purchase-receive
 * flow, which captures `batch_number` + `manufacture_date` + `expiry_date`
 * on the line and materialises the `product_batches` row at post time.
 *
 * These columns hold the *new* batch's details on a draft line. At post
 * ({@see \App\Actions\Inventory\PostStockAdjustment}) the row is found-or-
 * created and `batch_id` is back-filled, so a posted line always carries
 * a concrete `batch_id`. They stay null for existing-batch picks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_adjustment_items', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_adjustment_items', 'batch_number')) {
                $table->string('batch_number', 64)->nullable()->after('batch_id');
            }
            if (! Schema::hasColumn('stock_adjustment_items', 'manufacture_date')) {
                $table->date('manufacture_date')->nullable()->after('batch_number');
            }
            if (! Schema::hasColumn('stock_adjustment_items', 'expiry_date')) {
                $table->date('expiry_date')->nullable()->after('manufacture_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_adjustment_items', function (Blueprint $table) {
            foreach (['batch_number', 'manufacture_date', 'expiry_date'] as $col) {
                if (Schema::hasColumn('stock_adjustment_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
