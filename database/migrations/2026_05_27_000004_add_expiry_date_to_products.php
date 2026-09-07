<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a `expiry_date` column to products. This is the product-level
 * "default expiry" — useful for pharmacy items and any SKU where
 * shelf-life is set at the product level rather than per delivery.
 *
 * Per-batch / lot expiry is tracked separately in `product_batches`
 * when the inventory module lands. The product-level date acts as
 * the fallback the cashier sees when no batch info is available.
 *
 * Idempotent: re-run safely on already-migrated installs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'expiry_date')) {
                $table->date('expiry_date')->nullable()->after('track_expiry');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'expiry_date')) {
                $table->dropColumn('expiry_date');
            }
        });
    }
};
