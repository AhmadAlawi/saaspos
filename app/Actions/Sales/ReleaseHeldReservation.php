<?php

namespace App\Actions\Sales;

use App\Models\Sale;
use App\Models\StockLevel;
use Illuminate\Support\Facades\DB;

/**
 * Release the stock reservations held against a {@see Sale} when its
 * hold goes away — either resumed back into a cart (where each line
 * gets re-walked through the normal sale flow) or voided (discarded).
 *
 * Idempotent: only runs while the sale is in `held` status; once the
 * reservation has been released by either path, the next call is a
 * no-op so a double-click can't double-decrement.
 */
class ReleaseHeldReservation
{
    public function __invoke(Sale $sale): void
    {
        if ($sale->status !== Sale::STATUS_HELD) {
            return; // Already released, or never was a hold.
        }

        DB::transaction(function () use ($sale) {
            foreach ($sale->items as $item) {
                $level = StockLevel::query()
                    ->where('store_id',   $sale->store_id)
                    ->where('product_id', $item->product_id)
                    ->where('variant_id', $item->variant_id)
                    ->lockForUpdate()
                    ->first();

                if (! $level) continue; // No reservation to give back.

                // Floor at 0 — defends against a bad-state row where
                // reserved drifted below the held qty.
                $next = bcsub((string) $level->reserved_quantity, (string) $item->quantity, 4);
                if (bccomp($next, '0', 4) < 0) $next = '0.0000';

                $level->forceFill(['reserved_quantity' => $next])->save();
            }
        });
    }
}
