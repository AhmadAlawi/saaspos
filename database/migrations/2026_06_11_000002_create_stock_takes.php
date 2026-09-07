<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock Take (cycle count) — header + items.
 *
 * Status flow:
 *   draft   → operator is walking the shelves; counted_quantity is being
 *             filled in line by line. Editable. Nothing in the ledger.
 *   posted  → variance rows have been written to stock_movements via
 *             RecordStockMovement(type='count'). View-only after this.
 *   cancelled → operator abandoned the count. Items are kept for audit
 *               but no movements were ever written.
 *
 * `expected_quantity` is snapshotted at start so the variance the
 * operator sees matches the system state AT THAT MOMENT, not whatever
 * the level looks like by the time they finish counting half an hour
 * later (real-world: sales happen during the count).
 *
 * Number format: `COUNT-{STORE}-{YYYYMM}-{NNNN}`, per-store/per-month
 * sequence. Mirrors the purchase / sale numbering family.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_takes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('number', 32);
            $table->string('name')->nullable();
            $table->date('take_date');
            $table->text('notes')->nullable();
            // Widened to 32 from the start — `cancelled` (9 chars) fits
            // in 16 but we learned from sales.status that 16 is a trap.
            $table->string('status', 32)->default('draft')->index();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'number']);
        });

        Schema::create('stock_take_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_take_id')->constrained('stock_takes')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            // Snapshot of product_stock_levels.quantity at the moment
            // the take was created. Never mutates after that — this is
            // the "what the system thought" half of the variance.
            $table->decimal('expected_quantity', 15, 4)->default(0);
            // Null until the operator enters a count. A null is NOT
            // zero — null means "skipped / not yet counted"; only
            // non-null lines with non-zero variance flow to the ledger.
            $table->decimal('counted_quantity', 15, 4)->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['stock_take_id', 'product_id', 'variant_id'], 'stock_take_items_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_take_items');
        Schema::dropIfExists('stock_takes');
    }
};
