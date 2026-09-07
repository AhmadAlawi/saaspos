<?php

namespace App\Actions\Products;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Reconciles a product's `product_barcodes` rows (extra/alternate
 * barcodes, beyond the primary `products.barcode`) against a flat list
 * of strings from the editor. Uniqueness across every barcode source in
 * the system (this product's own primary barcode, other products'
 * primary/extra barcodes, every variant's barcode) is validated up front
 * in {@see \App\Http\Requests\Admin\ProductRequest}, so this action can
 * assume the payload is already safe to write — it just replaces the
 * whole set with what was submitted.
 *
 * Wrapped in a transaction so a mid-loop failure (a race against a
 * concurrent edit, a DB hiccup) rolls back to the product's PREVIOUS
 * barcode set instead of leaving it with only however many rows made
 * it through before the failure — the delete-then-recreate shape below
 * is otherwise all-or-nothing only in the happy path.
 */
class SyncProductBarcodes
{
    /** @param list<string> $barcodes already-trimmed, blanks removed */
    public function __invoke(Product $product, array $barcodes): void
    {
        DB::transaction(function () use ($product, $barcodes) {
            $product->barcodes()->delete();

            foreach ($barcodes as $barcode) {
                $product->barcodes()->create(['barcode' => $barcode]);
            }
        });
    }
}
