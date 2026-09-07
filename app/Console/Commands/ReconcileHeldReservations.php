<?php

namespace App\Console\Commands;

use App\Models\Sale;
use App\Models\StockLevel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Walks every `held` sale and ensures the matching stock_level rows
 * carry a reservation that covers the held quantities.
 *
 * Use cases:
 *   - Backfilling holds that pre-date the HoldSale reservation logic
 *     (anything created before 2026-06-09).
 *   - Recovering from a corrupted state where reservations drifted —
 *     the command is idempotent, so re-running is always safe.
 *
 * Run with:  php artisan sales:reconcile-held-reservations
 * Dry-run:   php artisan sales:reconcile-held-reservations --dry-run
 */
class ReconcileHeldReservations extends Command
{
    protected $signature = 'sales:reconcile-held-reservations {--dry-run : Print what would change without writing}';
    protected $description = 'Resync stock_levels.reserved_quantity against every held sale.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        if ($dry) {
            $this->warn('Dry-run — no changes will be written.');
        }

        // Total qty held per (store_id, product_id, variant_id). We
        // need EVERY held line summed up, then compare against the
        // existing reserved_quantity per stock_level row.
        $heldRows = DB::table('sales')
            ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.status', Sale::STATUS_HELD)
            ->whereNull('sales.deleted_at')
            ->groupBy('sales.store_id', 'sale_items.product_id', 'sale_items.variant_id')
            ->selectRaw('sales.store_id, sale_items.product_id, sale_items.variant_id, SUM(sale_items.quantity) AS held_qty')
            ->get();

        if ($heldRows->isEmpty()) {
            $this->info('No held sales found — nothing to reconcile.');
            return self::SUCCESS;
        }

        $fixed = 0;
        $alreadyOk = 0;

        foreach ($heldRows as $row) {
            $level = StockLevel::query()
                ->where('store_id', $row->store_id)
                ->where('product_id', $row->product_id)
                ->where('variant_id', $row->variant_id)
                ->lockForUpdate()
                ->first();

            $current = (string) ($level?->reserved_quantity ?? '0');
            $desired = (string) $row->held_qty;

            if (bccomp($current, $desired, 4) === 0) {
                $alreadyOk++;
                continue;
            }

            $this->line(sprintf(
                'store=%d  product=%d  variant=%s  reserved %s -> %s',
                $row->store_id, $row->product_id, $row->variant_id ?? '-', $current, $desired,
            ));

            if (! $dry) {
                if (! $level) {
                    StockLevel::create([
                        'store_id'              => $row->store_id,
                        'product_id'            => $row->product_id,
                        'variant_id'            => $row->variant_id,
                        'quantity'              => 0,
                        'reserved_quantity'     => $desired,
                        'weighted_average_cost' => 0,
                    ]);
                } else {
                    $level->forceFill(['reserved_quantity' => $desired])->save();
                }
            }
            $fixed++;
        }

        $this->info(sprintf('Done. %d row(s) %s, %d already correct.',
            $fixed, $dry ? 'would have been updated' : 'updated', $alreadyOk));

        return self::SUCCESS;
    }
}
