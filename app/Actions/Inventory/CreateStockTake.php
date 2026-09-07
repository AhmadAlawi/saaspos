<?php

namespace App\Actions\Inventory;

use App\Models\StockLevel;
use App\Models\StockTake;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Create a new draft stock take + snapshot every (product, variant)
 * with a `product_stock_levels` row for the chosen store. The snapshot
 * is taken NOW so the variance the operator sees later reflects the
 * system state at start-of-count, not whatever sales happened during
 * the walk-through.
 *
 * Skips products that don't track stock — there's nothing to count
 * for services / non-stocked kits.
 *
 * Hooks: `stock_take.attributes` (filter), `stock_take.before_create`,
 *        `stock_take.after_create`.
 *
 * @param array{
 *   store_id: int,
 *   take_date: string,
 *   name?: ?string,
 *   notes?: ?string,
 * } $data
 */
class CreateStockTake
{
    public function __construct(private GenerateStockTakeNumber $generateNumber) {}

    public function __invoke(array $data, User $creator): StockTake
    {
        $data = apply_filters('stock_take.attributes', $data);

        return DB::transaction(function () use ($data, $creator) {
            $store = Store::query()->findOrFail((int) $data['store_id']);

            $header = [
                'store_id'   => $store->id,
                'number'     => ($this->generateNumber)($store),
                'name'       => $data['name'] ?? null,
                'take_date'  => $data['take_date'],
                'notes'      => $data['notes'] ?? null,
                'status'     => StockTake::STATUS_DRAFT,
                'created_by' => $creator->id,
                'updated_by' => $creator->id,
            ];

            do_action('stock_take.before_create', $header);

            $take = StockTake::create($header);

            // Snapshot every level row for this store. Joining
            // products to skip rows where the product doesn't track
            // stock (service, kits with non-stocked components).
            $levels = StockLevel::query()
                ->where('store_id', $store->id)
                ->whereHas('product', fn ($q) => $q->where('track_stock', true)->where('is_active', true))
                ->get(['id', 'product_id', 'variant_id', 'quantity']);

            $now = now();
            $rows = $levels->map(fn ($l) => [
                'stock_take_id'     => $take->id,
                'product_id'        => $l->product_id,
                'variant_id'        => $l->variant_id,
                'expected_quantity' => (string) $l->quantity,
                'counted_quantity'  => null,
                'notes'             => null,
                'created_at'        => $now,
                'updated_at'        => $now,
            ])->all();

            if (!empty($rows)) {
                // Chunk to keep the insert reasonable on a large catalog.
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('stock_take_items')->insert($chunk);
                }
            }

            do_action('stock_take.after_create', $take);

            return $take->load('items');
        });
    }
}
