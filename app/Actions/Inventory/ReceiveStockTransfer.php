<?php

namespace App\Actions\Inventory;

use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Moves a stock transfer from `in_transit` → `received`.
 *
 * Posts a `transfer_in` movement for every line item against the
 * destination store using the entered received quantities, which may
 * differ from the requested quantities (partial delivery or overage).
 *
 * Short receipts are reconciled: any quantity that was dispatched but not
 * received is returned to the SOURCE store (a `transfer_in` back to it), so
 * the source isn't silently left short for goods that never arrived. The
 * shortfall stays visible on the document (requested vs received) and shows
 * up in the stock-movement ledger.
 */
class ReceiveStockTransfer
{
    public function __construct(
        private readonly RecordStockMovement $recordMovement,
    ) {}

    /**
     * @param  array<int, array{id: int, received_quantity: string|float}>  $receivedQtys
     *         Maps StockTransferItem IDs to their received quantities.
     */
    public function __invoke(StockTransfer $transfer, array $receivedQtys, ?User $user = null): StockTransfer
    {
        abort_unless($transfer->isInTransit(), 422, 'Transfer must be in transit to receive.');

        return DB::transaction(function () use ($transfer, $receivedQtys, $user) {
            do_action('stock_transfer.before_receive', $transfer);

            $transfer->loadMissing('items');

            // Index qty map by item id for O(1) lookup.
            $qtyMap = collect($receivedQtys)->keyBy('id');

            foreach ($transfer->items as $item) {
                $requested   = (string) $item->requested_quantity;
                $receivedQty = (string) ($qtyMap->get($item->id)['received_quantity'] ?? $requested);

                // Guard against a negative entry (the form caps at 0, but be safe).
                if (bccomp($receivedQty, '0', 4) < 0) {
                    $receivedQty = '0';
                }

                $item->update(['received_quantity' => $receivedQty]);

                // 1. What actually arrived lands at the destination store.
                if (bccomp($receivedQty, '0', 4) > 0) {
                    ($this->recordMovement)(
                        storeId:       $transfer->to_store_id,
                        productId:     $item->product_id,
                        variantId:     $item->variant_id,
                        batchId:       $item->batch_id,
                        quantityDelta: $receivedQty,
                        type:          'transfer_in',
                        referenceType: StockTransfer::class,
                        referenceId:   $transfer->id,
                        unitCost:      $item->unit_cost !== null ? (string) $item->unit_cost : null,
                        notes:         "Received: {$transfer->number}",
                        createdBy:     $user?->id,
                    );
                }

                // 2. Anything dispatched but not received goes BACK to the source
                //    store, so it isn't silently lost. (Overages get no debit.)
                $shortfall = bcsub($requested, $receivedQty, 4);
                if (bccomp($shortfall, '0', 4) > 0) {
                    ($this->recordMovement)(
                        storeId:       $transfer->from_store_id,
                        productId:     $item->product_id,
                        variantId:     $item->variant_id,
                        batchId:       $item->batch_id,
                        quantityDelta: $shortfall,
                        type:          'transfer_in',
                        referenceType: StockTransfer::class,
                        referenceId:   $transfer->id,
                        unitCost:      $item->unit_cost !== null ? (string) $item->unit_cost : null,
                        notes:         "Returned to source (short receipt): {$transfer->number}",
                        createdBy:     $user?->id,
                    );
                }
            }

            $transfer->update([
                'status'        => StockTransfer::STATUS_RECEIVED,
                'received_date' => Carbon::now()->toDateString(),
                'updated_by'    => $user?->id,
            ]);

            do_action('stock_transfer.after_receive', $transfer);

            return $transfer->fresh();
        });
    }
}
