<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-line receipt fields (Settings → Receipt → "What to show"):
 *   - SKU printed under each item name (operational — returns / reorders)
 *   - HSN code printed under each item name (fiscal — GST tax invoices)
 *   - HSN-wise tax summary block near the totals (compliant GST invoice)
 *
 * SKU is already snapshotted on `sale_items` (`sku_snapshot`); HSN is not,
 * so we add `hsn_snapshot` and populate it in CompleteSale — a receipt must
 * reproduce faithfully even if the product's HSN is later edited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->boolean('receipt_show_sku')->default(false)->after('receipt_show_qr');
            $table->boolean('receipt_show_hsn')->default(false)->after('receipt_show_sku');
            $table->boolean('receipt_show_hsn_summary')->default(false)->after('receipt_show_hsn');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->string('hsn_snapshot', 32)->nullable()->after('barcode_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->dropColumn(['receipt_show_sku', 'receipt_show_hsn', 'receipt_show_hsn_summary']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('hsn_snapshot');
        });
    }
};
