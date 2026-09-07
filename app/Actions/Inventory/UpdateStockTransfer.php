<?php

namespace App\Actions\Inventory;

use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateStockTransfer
{
    /**
     * @param  array<string, mixed>  $data  Validated transfer attributes + items.
     */
    public function __invoke(StockTransfer $transfer, array $data, ?User $user = null): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $data, $user) {
            $transfer->update([
                'from_store_id'         => $data['from_store_id'],
                'to_store_id'           => $data['to_store_id'],
                'transfer_date'         => $data['transfer_date'],
                'expected_arrival_date' => $data['expected_arrival_date'] ?? null,
                'notes'                 => $data['notes'] ?? null,
                'updated_by'            => $user?->id,
            ]);

            // Full replace — simpler than diffing for a draft document.
            $transfer->items()->delete();

            foreach ($data['items'] ?? [] as $item) {
                StockTransferItem::create([
                    'stock_transfer_id'  => $transfer->id,
                    'product_id'         => $item['product_id'],
                    'variant_id'         => $item['variant_id'] ?: null,
                    'batch_id'           => $item['batch_id'] ?? null,
                    'requested_quantity' => $item['requested_quantity'],
                    'unit_cost'          => $item['unit_cost'] ?: null,
                ]);
            }

            do_action('stock_transfer.after_update', $transfer);

            return $transfer;
        });
    }
}
