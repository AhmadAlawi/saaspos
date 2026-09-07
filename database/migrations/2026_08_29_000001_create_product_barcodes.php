<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A product can carry more than one barcode (a case/carton code from a
 * different supplier, a relabeled variant, etc.) alongside its primary
 * `products.barcode`. This table holds the EXTRA ones only — the primary
 * column is untouched, so every existing lookup that already matches on
 * `products.barcode` keeps working unchanged; call sites that need to
 * recognize the extra codes too (scan-to-add, price-check, label lookup,
 * mobile catalog) join this table as an additional fallback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_barcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('barcode', 64)->unique();
            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_barcodes');
    }
};
