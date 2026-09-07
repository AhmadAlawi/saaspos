<?php

namespace App\Actions\Inventory;

use App\Models\StockTake;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Post a draft stock take: walk every counted item with a non-zero
 * variance and call {@see RecordStockMovement} so the variance lands
 * in the ledger + bumps the per-store level. Flip the document to
 * `posted` after every movement has been written.
 *
 * What writes a movement:
 *   - `counted_quantity` is NOT NULL (skipped lines are ignored)
 *   - `counted - expected != 0` (exact-match lines are ignored)
 *
 * Movement type: `count`. The reference back-pointer is the stock-take
 * header so the ledger can link back to "why did this number change".
 *
 * Everything happens in one transaction — partial posts are
 * structurally impossible. If any line's `RecordStockMovement` throws
 * (negative-stock policy, plugin hook, etc.) the whole post rolls back.
 *
 * Hooks:
 *   - `stock_take.before_post` (action, $take) — listeners may throw
 *     to abort (closed-period guards, mandatory review, etc.)
 *   - `stock_take.after_post`  (action, $take) — listeners may notify,
 *     log, or push to Pusher.
 */
class PostStockTake
{
    public function __construct(private RecordStockMovement $record) {}

    public function __invoke(StockTake $take, User $user): StockTake
    {
        if (! $take->isDraft()) {
            throw new RuntimeException('Only draft stock takes can be posted.');
        }

        $take->load('items');
        if ($take->items->isEmpty()) {
            throw new RuntimeException('Cannot post a stock take with no items.');
        }

        // A take with zero counted lines is suspicious — the operator
        // likely meant to cancel. Reject so we don't silently flip
        // status with no ledger impact.
        if ($take->items->every(fn ($i) => $i->counted_quantity === null)) {
            throw new RuntimeException('Cannot post a stock take where nothing has been counted.');
        }

        return DB::transaction(function () use ($take, $user) {
            do_action('stock_take.before_post', $take);

            foreach ($take->items as $item) {
                if ($item->counted_quantity === null) continue;

                $delta = bcsub((string) $item->counted_quantity, (string) $item->expected_quantity, 4);
                if (bccomp($delta, '0', 4) === 0) continue;

                ($this->record)(
                    storeId:        $take->store_id,
                    productId:      (int) $item->product_id,
                    variantId:      $item->variant_id ? (int) $item->variant_id : null,
                    batchId:        null,
                    quantityDelta:  $delta,
                    type:           'count',
                    referenceType:  StockTake::class,
                    referenceId:    $take->id,
                    unitCost:       null,
                    notes:          $item->notes ?: "Stock take {$take->number}",
                    createdBy:      $user->id,
                );
            }

            $take->update([
                'status'     => StockTake::STATUS_POSTED,
                'posted_at'  => now(),
                'posted_by'  => $user->id,
                'updated_by' => $user->id,
            ]);

            do_action('stock_take.after_post', $take);

            return $take->refresh()->load('items');
        });
    }
}
