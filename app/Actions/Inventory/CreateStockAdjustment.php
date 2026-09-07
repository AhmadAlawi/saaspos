<?php

namespace App\Actions\Inventory;

use App\Models\StockAdjustment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a new draft stock adjustment + its line items in one
 * transaction. The header gets a freshly-generated `ADJ-YYYY-NNNN`
 * number; status is always `draft` — posting is a separate action.
 *
 * Hooks: `stock_adjustment.attributes` (filter), `stock_adjustment.before_create`,
 *        `stock_adjustment.after_create`.
 *
 * @param array{
 *   store_id: int,
 *   adjustment_date: string,
 *   reason_code_id?: ?int,
 *   reason?: ?string,
 *   notes?: ?string,
 *   items: array<int, array{
 *     product_id: int,
 *     variant_id?: ?int,
 *     batch_id?: ?int,
 *     batch_number?: ?string,
 *     manufacture_date?: ?string,
 *     expiry_date?: ?string,
 *     quantity_delta: int|float|string,
 *     unit_cost?: ?float,
 *     notes?: ?string,
 *   }>,
 * } $data
 */
class CreateStockAdjustment
{
    public function __construct(private GenerateStockAdjustmentNumber $generateNumber) {}

    public function __invoke(array $data, User $creator): StockAdjustment
    {
        $data = apply_filters('stock_adjustment.attributes', $data);

        return DB::transaction(function () use ($data, $creator) {
            $header = [
                'store_id'        => $data['store_id'],
                'number'          => ($this->generateNumber)(),
                'adjustment_date' => $data['adjustment_date'],
                'reason_code_id'  => $data['reason_code_id'] ?? null,
                'reason'          => $data['reason'] ?? null,
                'notes'           => $data['notes'] ?? null,
                'status'          => StockAdjustment::STATUS_DRAFT,
                'created_by'      => $creator->id,
                'updated_by'      => $creator->id,
            ];

            do_action('stock_adjustment.before_create', $header, $data['items'] ?? []);

            $adjustment = StockAdjustment::create($header);

            foreach ($data['items'] ?? [] as $line) {
                $adjustment->items()->create([
                    'product_id'       => $line['product_id'],
                    'variant_id'       => $line['variant_id'] ?? null,
                    'batch_id'         => $line['batch_id'] ?? null,
                    'batch_number'     => $line['batch_number'] ?? null,
                    'manufacture_date' => $line['manufacture_date'] ?? null,
                    'expiry_date'      => $line['expiry_date'] ?? null,
                    'quantity_delta'   => $line['quantity_delta'],
                    'unit_cost'        => $line['unit_cost'] ?? null,
                    'notes'            => $line['notes'] ?? null,
                ]);
            }

            do_action('stock_adjustment.after_create', $adjustment);

            return $adjustment;
        });
    }
}
