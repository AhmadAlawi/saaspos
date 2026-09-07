<?php

namespace App\Actions\Inventory;

use App\Models\StockAdjustment;
use RuntimeException;

/**
 * Delete a draft adjustment. Posted documents are immutable — once they
 * hit the ledger, the only way to undo is a counter-adjustment so the
 * audit trail stays intact.
 *
 * `stock_adjustment_items` cascade-delete via FK so we don't have to
 * walk them by hand.
 */
class DeleteStockAdjustment
{
    public function __invoke(StockAdjustment $adjustment): void
    {
        if (! $adjustment->isDraft()) {
            throw new RuntimeException('Cannot delete a posted stock adjustment.');
        }

        do_action('stock_adjustment.before_delete', $adjustment);

        $adjustment->delete();

        do_action('stock_adjustment.after_delete', $adjustment);
    }
}
