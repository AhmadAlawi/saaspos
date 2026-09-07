<?php

namespace App\Actions\Inventory;

use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateStockTransfer
{
    public function __construct(
        private readonly GenerateTransferNumber $generateNumber,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated transfer attributes + items.
     */
    public function __invoke(array $data, ?User $user = null): StockTransfer
    {
        return DB::transaction(function () use ($data, $user) {
            $transfer = StockTransfer::create([
                'number'                => ($this->generateNumber)(),
                'from_store_id'         => $data['from_store_id'],
                'to_store_id'           => $data['to_store_id'],
                'transfer_date'         => $data['transfer_date'],
                'expected_arrival_date' => $data['expected_arrival_date'] ?? null,
                'notes'                 => $data['notes'] ?? null,
                'status'                => StockTransfer::STATUS_DRAFT,
                'created_by'            => $user?->id,
                'updated_by'            => $user?->id,
            ]);

            $this->writeItems($transfer, $data['items'] ?? []);

            do_action('stock_transfer.after_create', $transfer);

            return $transfer;
        });
    }

    /** @param  array<int, array<string, mixed>>  $items */
    private function writeItems(StockTransfer $transfer, array $items): void
    {
        foreach ($items as $item) {
            StockTransferItem::create([
                'stock_transfer_id'  => $transfer->id,
                'product_id'         => $item['product_id'],
                'variant_id'         => $item['variant_id'] ?: null,
                'batch_id'           => $item['batch_id'] ?? null,
                'requested_quantity' => $item['requested_quantity'],
                'unit_cost'          => $item['unit_cost'] ?: null,
            ]);
        }
    }
}
