<?php

namespace App\Actions\Inventory;

use App\Models\ProductBatch;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Archive a batch — a SOFT delete, deliberately.
 *
 * Every FK pointing at `product_batches.id` (sale_items, purchase_items,
 * stock_movements, stock_adjustment_items, stock_transfer_items) is
 * `nullOnDelete`, so the database offers no protection at all: a hard delete
 * would quietly succeed and blank `batch_id` on posted invoices and ledger
 * rows, destroying pharmacy traceability and the reversal target VoidSale /
 * RecordSaleReturn depend on. Keeping the row means every historical reference
 * still resolves while the batch leaves the pickers and the list.
 *
 * The one hard rule: the batch must be EMPTY. Archiving a batch that still
 * holds stock would strand that quantity — `product_stock_levels` would keep
 * saying N units exist with no batch behind them, and FEFO would find nothing.
 * To remove a batch that still has stock, adjust the stock out first; that's
 * the auditable path and it leaves a ledger trail.
 *
 * Reversible: receiving or adjusting the same batch number back in restores
 * this row rather than creating a duplicate (see ReceivePurchase::findBatch).
 */
class DeleteProductBatch
{
    public function __invoke(ProductBatch $batch): void
    {
        // Locked and re-read inside the transaction: a sale completing right
        // now could be moving this batch's quantity, and the guard has to see
        // the committed value, not one read before the click.
        DB::transaction(function () use ($batch) {
            $fresh = ProductBatch::query()
                ->whereKey($batch->getKey())
                ->lockForUpdate()
                ->first();

            if (! $fresh) {
                return; // already archived by a concurrent request — nothing to do
            }

            if (bccomp((string) $fresh->quantity, '0', 4) !== 0) {
                throw new RuntimeException('Cannot archive a batch that still holds stock.');
            }

            do_action('product_batch.before_delete', $fresh);

            $fresh->delete();

            do_action('product_batch.after_delete', $fresh);
        });
    }
}
