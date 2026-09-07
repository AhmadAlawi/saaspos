<?php

namespace App\Actions\Inventory;

use App\Models\StockTake;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Bulk-save counted_quantity (and per-line notes) for a draft stock
 * take. The editor sends a sparse array — only rows whose values
 * actually changed — keyed by `id` of the existing item rows.
 *
 * `counted_quantity` is nullable: an empty string / null clears the
 * count back to "skipped" (won't post a movement). Zero is a
 * legitimate count value (different from null) — the operator saying
 * "I checked, there's actually zero on the shelf".
 *
 * Header fields (name, notes, take_date) are updated in the same
 * call so the editor's save button is one trip.
 *
 * @param array{
 *   name?: ?string,
 *   take_date?: string,
 *   notes?: ?string,
 *   items?: array<int, array{
 *     id: int,
 *     counted_quantity?: numeric|null|string,
 *     notes?: ?string,
 *   }>,
 * } $data
 */
class UpdateStockTake
{
    public function __invoke(StockTake $take, array $data, User $user): StockTake
    {
        if (! $take->isDraft()) {
            throw new RuntimeException('Only draft stock takes can be edited.');
        }

        return DB::transaction(function () use ($take, $data, $user) {
            $take->fill([
                'name'       => $data['name']      ?? $take->name,
                'take_date'  => $data['take_date'] ?? $take->take_date,
                'notes'      => $data['notes']     ?? $take->notes,
                'updated_by' => $user->id,
            ])->save();

            if (!empty($data['items'] ?? [])) {
                $ownIds = $take->items()->pluck('id')->all();
                foreach ($data['items'] as $row) {
                    $id = (int) ($row['id'] ?? 0);
                    if (! in_array($id, $ownIds, true)) continue;

                    // Empty string / null on counted_quantity → clear
                    // back to "skipped". Zero stays as a real count.
                    $cq = $row['counted_quantity'] ?? null;
                    if ($cq === '' || $cq === null) {
                        $counted = null;
                    } else {
                        $counted = (string) $cq;
                    }

                    DB::table('stock_take_items')
                        ->where('id', $id)
                        ->update([
                            'counted_quantity' => $counted,
                            'notes'            => $row['notes'] ?? null,
                            'updated_at'       => now(),
                        ]);
                }
            }

            return $take->refresh()->load('items');
        });
    }
}
