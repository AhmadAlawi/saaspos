<?php

namespace App\Actions\Inventory;

use App\Models\StockAdjustment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Edit a DRAFT adjustment. Replaces the entire line set on each save —
 * editor is line-replace, not line-merge, which is simpler to reason
 * about and matches how the editor UI thinks about the document.
 *
 * Refuses to touch a posted adjustment. The controller's policy already
 * blocks the route; this is a defence-in-depth check for any caller
 * that bypasses it.
 *
 * @param array{
 *   adjustment_date?: string,
 *   reason_code_id?: ?int,
 *   reason?: ?string,
 *   notes?: ?string,
 *   items?: array<int, array{
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
class UpdateStockAdjustment
{
    public function __invoke(StockAdjustment $adjustment, array $data, User $updater): StockAdjustment
    {
        if (! $adjustment->isDraft()) {
            throw new RuntimeException('Cannot edit a posted stock adjustment.');
        }

        $data = apply_filters('stock_adjustment.attributes', $data, $adjustment);

        return DB::transaction(function () use ($adjustment, $data, $updater) {
            do_action('stock_adjustment.before_update', $adjustment, $data);

            $adjustment->update([
                'adjustment_date' => $data['adjustment_date'] ?? $adjustment->adjustment_date,
                'reason_code_id'  => $data['reason_code_id'] ?? $adjustment->reason_code_id,
                'reason'          => $data['reason']          ?? $adjustment->reason,
                'notes'           => $data['notes']           ?? $adjustment->notes,
                'updated_by'      => $updater->id,
            ]);

            if (array_key_exists('items', $data)) {
                $adjustment->items()->delete();
                foreach ($data['items'] as $line) {
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
            }

            do_action('stock_adjustment.after_update', $adjustment);

            return $adjustment->refresh();
        });
    }
}
