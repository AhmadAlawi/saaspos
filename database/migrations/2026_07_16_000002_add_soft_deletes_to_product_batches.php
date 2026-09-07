<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Archiving a batch = soft delete.
 *
 * Every FK pointing at `product_batches.id` — sale_items, purchase_items,
 * stock_movements, stock_adjustment_items, stock_transfer_items — is
 * `nullOnDelete`. A hard delete would therefore silently blank `batch_id` on
 * posted invoices and ledger rows, destroying pharmacy traceability and the
 * reversal target VoidSale / RecordSaleReturn rely on.
 *
 * Soft delete keeps the row (so every historical batch_id still resolves) while
 * removing it from the pickers and the admin list. See DeleteProductBatch for
 * the guard that only permits archiving an EMPTY batch — archiving one holding
 * stock would strand that quantity in product_stock_levels with no batch behind it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('product_batches', 'deleted_at')) {
            return;
        }

        Schema::table('product_batches', function (Blueprint $table) {
            $table->softDeletes()->after('initial_quantity');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('product_batches', 'deleted_at')) {
            return;
        }

        Schema::table('product_batches', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
