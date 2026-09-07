<?php

namespace App\Actions\Products;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Reconciles a product's `product_store_prices` rows against an array
 * payload keyed by store id.
 *
 * Each entry in the payload is shaped like:
 *
 *   $payload[$storeId] = [
 *       'cost_price'    => '1.20' | null,
 *       'selling_price' => '2.95' | null,
 *       'mrp'           => '3.50' | null,
 *   ];
 *
 * `$variantId` selects the override target:
 *   - null → product-level override (simple / kit products)
 *   - set  → variant-level override (variant products price per child)
 *
 * Semantics:
 *   - Any column left blank / null cascades to the variant/product
 *     default at resolve time (see {@see ResolveProductPrice}).
 *   - An entry where ALL three columns are blank deletes the row —
 *     no point keeping an empty override.
 *   - Stores not present in the payload are left alone (the editor
 *     never sends them, so the existing override survives).
 *
 * Hook points:
 *   - filter `product.prices.payload` → adjust the incoming array
 *   - action `product.prices.synced`  → fires after sync completes
 */
class SyncProductPrices
{
    /** @param array<int|string, array<string, mixed>> $payload */
    public function __invoke(Product $product, array $payload, ?int $variantId = null): void
    {
        $payload = apply_filters('product.prices.payload', $payload, $product);

        DB::transaction(function () use ($product, $payload, $variantId) {
            foreach ($payload as $storeId => $row) {
                $storeId = (int) $storeId;
                if ($storeId <= 0 || ! is_array($row)) continue;

                $values = [];
                foreach (['cost_price', 'selling_price', 'mrp'] as $col) {
                    $raw = $row[$col] ?? null;
                    $values[$col] = ($raw === null || $raw === '') ? null : $raw;
                }

                // Build the lookup for this (store, variant) target.
                // Explicit whereNull — `where('variant_id', null)` would
                // emit `= NULL`, which never matches.
                $base = $product->prices()->where('store_id', $storeId);
                $base = $variantId === null
                    ? $base->whereNull('variant_id')
                    : $base->where('variant_id', $variantId);

                // Empty override → delete the row instead of storing
                // three nulls. Keeps the table tidy and the resolver
                // fast (no row to inspect at all).
                if ($values['cost_price'] === null
                    && $values['selling_price'] === null
                    && $values['mrp'] === null) {
                    (clone $base)->delete();
                    continue;
                }

                $existing = (clone $base)->first();
                if ($existing) {
                    $existing->update($values);
                } else {
                    $product->prices()->create($values + [
                        'store_id'   => $storeId,
                        'variant_id' => $variantId,
                    ]);
                }
            }
        });

        do_action('product.prices.synced', $product);
    }
}
