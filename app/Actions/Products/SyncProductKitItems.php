<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\ProductKitItem;
use Illuminate\Support\Facades\DB;

/**
 * Reconciles a kit product's `product_kit_items` rows against an array
 * payload from the editor.
 *
 * Each item in the payload may carry:
 *   - `id`                    — existing row to update (omit / null = create new)
 *   - `component_product_id`  — required, FK to products
 *   - `component_variant_id`  — optional, FK to product_variants
 *   - `quantity`              — required, > 0
 *   - `sort_order`            — display order (auto-stamped from position if absent)
 *
 * Behavior:
 *   - Rows in payload with an `id` → update that row.
 *   - Rows in payload without an `id` → create a new row.
 *   - Existing rows NOT in the payload → hard delete (no audit trail
 *     needed; kit items aren't directly referenced by sale_items).
 *   - A kit cannot include itself as a component — defensive guard.
 *
 * Hook points:
 *   - filter `product.kit_items.payload` → adjust the incoming array
 *   - action `product.kit_items.synced`   → fires after sync completes
 */
class SyncProductKitItems
{
    /** @param array<int, array<string, mixed>> $payload */
    public function __invoke(Product $product, array $payload): void
    {
        $payload = apply_filters('product.kit_items.payload', $payload, $product);

        DB::transaction(function () use ($product, $payload) {
            $incomingIds = collect($payload)
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->all();

            // ── Drop rows the user removed ────────────────────────
            $product->kitItems()
                ->whereNotIn('id', $incomingIds ?: [0])
                ->delete();

            // ── Upsert each row in the payload ────────────────────
            foreach (array_values($payload) as $position => $row) {
                $componentId = (int) ($row['component_product_id'] ?? 0);
                if ($componentId === 0 || $componentId === $product->id) {
                    // Skip empty rows + the self-include guard.
                    continue;
                }

                $variantId = ! empty($row['component_variant_id'])
                    ? (int) $row['component_variant_id']
                    : null;

                $attrs = [
                    'component_product_id' => $componentId,
                    'component_variant_id' => $variantId,
                    'quantity'             => $row['quantity'] ?? 1,
                    'sort_order'           => $row['sort_order'] ?? $position,
                ];

                if (! empty($row['id'])) {
                    $item = $product->kitItems()->find($row['id']);
                    if ($item) {
                        $item->update($attrs);
                    }
                } else {
                    $product->kitItems()->create($attrs);
                }
            }
        });

        do_action('product.kit_items.synced', $product);
    }
}
