<?php

namespace App\Actions\Inventory;

use App\Models\ProductBatch;
use RuntimeException;

/**
 * Un-archive a batch — the inverse of {@see DeleteProductBatch}.
 *
 * Nothing to reconcile: archiving only ever removes an EMPTY batch and the row
 * itself never left the table, so restoring is a pure visibility change. Stock
 * levels, the ledger and every historical `batch_id` were untouched throughout.
 *
 * Batches also restore themselves when stock comes back — receiving or
 * adjusting the same batch number in, or a void/return reversing onto it. This
 * action is the manual path for an archive done by mistake.
 */
class RestoreProductBatch
{
    public function __invoke(ProductBatch $batch): void
    {
        if (! $batch->trashed()) {
            throw new RuntimeException('That batch is not archived.');
        }

        do_action('product_batch.before_restore', $batch);

        $batch->restore();

        do_action('product_batch.after_restore', $batch);
    }
}
