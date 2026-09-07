<?php

namespace App\Actions\Inventory;

use App\Models\StockLevel;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Moves a stock transfer from `draft` → `in_transit`.
 *
 * Posts a `transfer_out` movement for every line item against the
 * source store, reducing its on-hand quantity. The destination store
 * is not touched until {@see ReceiveStockTransfer} runs.
 */
class DispatchStockTransfer
{
    public function __construct(
        private readonly RecordStockMovement $recordMovement,
    ) {}

    public function __invoke(StockTransfer $transfer, ?User $user = null): StockTransfer
    {
        abort_unless($transfer->isDraft(), 422, 'Transfer must be in draft to dispatch.');

        return DB::transaction(function () use ($transfer, $user) {
            do_action('stock_transfer.before_dispatch', $transfer);

            $transfer->loadMissing('items.product');

            // Pre-flight: verify available stock for every item with a row lock
            // so concurrent dispatches cannot both "see" the same available qty.
            $errors = [];
            foreach ($transfer->items as $item) {
                $level = StockLevel::query()
                    ->where('store_id', $transfer->from_store_id)
                    ->where('product_id', $item->product_id)
                    ->when(
                        $item->variant_id,
                        fn ($q) => $q->where('variant_id', $item->variant_id),
                        fn ($q) => $q->whereNull('variant_id')
                    )
                    ->lockForUpdate()
                    ->first(['quantity']);

                $available = $level ? (string) $level->quantity : '0.0000';
                $requested = (string) $item->requested_quantity;

                if (bccomp($requested, $available, 4) > 0) {
                    $label = $item->product?->name ?? "Product #{$item->product_id}";
                    $errors[] = __('inventory.transfers.errors.insufficient_stock_item', [
                        'product'   => $label,
                        'available' => $available,
                        'requested' => $requested,
                    ]);
                }
            }

            if ($errors !== []) {
                throw new \DomainException(
                    __('inventory.transfers.errors.insufficient_stock')."\n".implode("\n", $errors)
                );
            }

            foreach ($transfer->items as $item) {
                ($this->recordMovement)(
                    storeId:       $transfer->from_store_id,
                    productId:     $item->product_id,
                    variantId:     $item->variant_id,
                    batchId:       $item->batch_id,
                    quantityDelta: bcmul((string) $item->requested_quantity, '-1', 4),
                    type:          'transfer_out',
                    referenceType: StockTransfer::class,
                    referenceId:   $transfer->id,
                    unitCost:      $item->unit_cost !== null ? (string) $item->unit_cost : null,
                    notes:         "Dispatched: {$transfer->number}",
                    createdBy:     $user?->id,
                );
            }

            $transfer->update([
                'status'     => StockTransfer::STATUS_IN_TRANSIT,
                'updated_by' => $user?->id,
            ]);

            do_action('stock_transfer.after_dispatch', $transfer);

            return $transfer->fresh();
        });
    }
}
