<?php

namespace App\Actions\Inventory;

use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cancels a stock transfer from either `draft` or `in_transit` state.
 *
 * If the transfer was already dispatched (`in_transit`), the source
 * store's stock is reinstated via a `transfer_in` reversal movement so
 * the ledger stays balanced.
 */
class CancelStockTransfer
{
    public function __construct(
        private readonly RecordStockMovement $recordMovement,
    ) {}

    public function __invoke(StockTransfer $transfer, ?User $user = null): StockTransfer
    {
        abort_unless(
            $transfer->isDraft() || $transfer->isInTransit(),
            422,
            'Only draft or in-transit transfers can be cancelled.'
        );

        return DB::transaction(function () use ($transfer, $user) {
            do_action('stock_transfer.before_cancel', $transfer);

            // Reverse the transfer_out for every line if already dispatched.
            if ($transfer->isInTransit()) {
                $transfer->loadMissing('items');

                foreach ($transfer->items as $item) {
                    ($this->recordMovement)(
                        storeId:       $transfer->from_store_id,
                        productId:     $item->product_id,
                        variantId:     $item->variant_id,
                        batchId:       $item->batch_id,
                        quantityDelta: (string) $item->requested_quantity,
                        type:          'transfer_in',
                        referenceType: StockTransfer::class,
                        referenceId:   $transfer->id,
                        unitCost:      $item->unit_cost !== null ? (string) $item->unit_cost : null,
                        notes:         "Cancelled: {$transfer->number}",
                        createdBy:     $user?->id,
                    );
                }
            }

            $transfer->update([
                'status'     => StockTransfer::STATUS_CANCELLED,
                'updated_by' => $user?->id,
            ]);

            do_action('stock_transfer.after_cancel', $transfer);

            return $transfer->fresh();
        });
    }
}
