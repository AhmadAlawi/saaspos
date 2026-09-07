<?php

namespace App\Actions\Products;

use App\Exceptions\ProductVariantHasSales;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reconciles a product's `product_variants` rows against an array
 * payload from the editor's attribute-matrix generator.
 *
 * Each item in the payload may carry:
 *   - `id`           — existing variant to update (omit / null = create new)
 *   - `combo`        — { attrName => value } map; stored as the variant's
 *                      `attributes` JSON and the source of its display label
 *   - `sku`          — required, unique across the product_variants table
 *   - `barcode`      — optional, unique when set
 *   - `cost_price`   — optional override
 *   - `selling_price`— required (variant price is the source of truth)
 *   - `is_active`    — bool, defaults true
 *   - `store_prices` — { storeId => {cost,selling,mrp} } per-store overrides
 *                      for THIS variant, persisted via {@see SyncProductPrices}
 *
 * Behavior:
 *   - Items in payload with an `id` → update that variant.
 *   - Items in payload without an `id` → create a new variant.
 *   - Existing variants NOT in the payload → soft-delete (with sales guard);
 *     their per-store price rows are removed too.
 *
 * The whole thing runs in a transaction so a mid-sync failure doesn't
 * leave the product with a half-updated variant set.
 *
 * Sales guard: a variant that's already on a `sale_items` row can't be
 * deleted — removing it would orphan history. Throws
 * {@see ProductVariantHasSales}; the controller surfaces a 422 message
 * naming the offending variant.
 *
 * Hook points:
 *   - filter `product.variants.payload` → adjust the incoming array
 *   - action `product.variants.synced`   → fires after sync completes
 */
class SyncProductVariants
{
    public function __construct(private SyncProductPrices $syncPrices) {}

    /** @param array<int, array<string, mixed>> $payload */
    public function __invoke(Product $product, array $payload): void
    {
        $payload = apply_filters('product.variants.payload', $payload, $product);

        DB::transaction(function () use ($product, $payload) {
            // Index the incoming list by id so we know which existing
            // variants survive vs. need deletion.
            $incomingIds = collect($payload)
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->all();

            // ── Delete the variants the user removed ──────────────
            $product->variants()
                ->whereNotIn('id', $incomingIds ?: [0])
                ->get()
                ->each(function (ProductVariant $variant) use ($product) {
                    $this->assertNoLiveSales($variant);
                    // Drop the variant's per-store overrides first — the
                    // FK cascades on hard delete, but variants soft-delete,
                    // so clean them up explicitly to avoid orphan rows.
                    $product->prices()->where('variant_id', $variant->id)->delete();
                    $variant->delete();
                });

            // ── Upsert each variant in the payload ────────────────
            foreach ($payload as $row) {
                $cost    = $row['cost_price']    ?? null;
                $selling = $row['selling_price'] ?? null;
                $sale    = $row['sale_price']    ?? null;
                $mrp     = $row['mrp']           ?? null;

                // Combo: { attrName => value }. Stored as the variant's
                // attributes JSON; the model's `label` accessor joins the
                // values ("Red · S") when no explicit label key exists.
                $combo = [];
                foreach ((array) ($row['combo'] ?? []) as $attrName => $value) {
                    $value = trim((string) $value);
                    if ($value === '') continue;
                    $combo[(string) $attrName] = $value;
                }

                $attrs = [
                    'sku'           => trim((string) ($row['sku'] ?? '')),
                    'barcode'       => isset($row['barcode']) && $row['barcode'] !== '' ? (string) $row['barcode'] : null,
                    'attributes'    => $combo,
                    'cost_price'    => $cost    !== null && $cost    !== '' ? $cost    : null,
                    'selling_price' => $selling !== null && $selling !== '' ? $selling : null,
                    'sale_price'    => $sale    !== null && $sale    !== '' ? $sale    : null,
                    'mrp'           => $mrp     !== null && $mrp     !== '' ? $mrp     : null,
                    'is_active'     => (bool) ($row['is_active'] ?? true),
                ];

                $variant = null;
                if (! empty($row['id'])) {
                    // Update existing — only if it actually belongs to
                    // this product (defence against payload tampering).
                    $variant = $product->variants()->find($row['id']);
                    if ($variant) {
                        $variant->update($attrs);
                    }
                } else {
                    $variant = $product->variants()->create($attrs);
                }

                // Per-store overrides for this specific variant.
                if ($variant) {
                    ($this->syncPrices)(
                        $product,
                        is_array($row['store_prices'] ?? null) ? $row['store_prices'] : [],
                        $variant->id,
                    );
                }
            }
        });

        do_action('product.variants.synced', $product);
    }

    private function assertNoLiveSales(ProductVariant $variant): void
    {
        if (! Schema::hasTable('sale_items')) {
            return;
        }

        $count = DB::table('sale_items')
            ->where('variant_id', $variant->id)
            ->count();

        if ($count > 0) {
            throw new ProductVariantHasSales($count, (string) ($variant->label ?? $variant->sku));
        }
    }
}
