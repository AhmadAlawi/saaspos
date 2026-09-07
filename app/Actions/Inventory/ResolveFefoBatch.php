<?php

namespace App\Actions\Inventory;

use App\Models\ProductBatch;

/**
 * Resolve the batch a sale line should draw from when the customer can't (or
 * shouldn't) pick one — FEFO: First-Expired, First-Out.
 *
 * The cashier lets staff pick a batch by hand; the self-serve kiosk has no
 * operator to do that, so it needs a deterministic server-side pick. This
 * returns the earliest-expiring LIVE batch (quantity > 0) for a
 * (store, product, variant), so short-dated stock clears first and per-batch
 * quantities stay in step with the stock level once
 * {@see \App\Actions\Inventory\RecordStockMovement} decrements it.
 *
 * Null-expiry batches never expire, so they sort LAST behind every dated
 * batch. When $excludeExpired is set (the company blocks expired-batch sales
 * and the actor lacks the `inventory.sell_expired` override) already-expired
 * batches are skipped entirely, matching the guard in {@see \App\Actions\Sales\CompleteSale}.
 *
 * Returns null when no live batch qualifies — the caller then falls back to
 * batchless stock handling (the line simply carries no batch_id).
 */
class ResolveFefoBatch
{
    public function __invoke(int $storeId, int $productId, ?int $variantId, bool $excludeExpired = false): ?ProductBatch
    {
        return ProductBatch::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->whereRaw('quantity > 0')
            ->when($excludeExpired, fn ($q) => $q->where(function ($q) {
                $q->whereNull('expiry_date')
                  ->orWhereDate('expiry_date', '>=', now()->toDateString());
            }))
            // Dated batches first (FEFO), null-expiry last; earliest expiry
            // wins, id breaks ties for a stable pick.
            ->orderByRaw('expiry_date IS NULL')
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->first();
    }
}
