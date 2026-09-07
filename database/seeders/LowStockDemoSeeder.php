<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo Low-stock data — picks the first handful of demo products that
 * already have an opening stock level and sets their `reorder_level`
 * just above current on-hand. That immediately surfaces them on the
 * Low-stock report so a fresh /seed gives the page something to show.
 *
 * Idempotent: skips if any product already has a reorder_level
 * configured (assumes the user has tuned thresholds for their store).
 * Depends on:
 *   - `ProductsDemoSeeder` having created products
 *   - `StockAdjustmentsDemoSeeder` having posted opening stock
 *
 *   /seed?class=LowStockDemoSeeder
 */
class LowStockDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Bail if anyone (real user or a prior demo) has already set
        // thresholds — we don't want to clobber their config.
        if (DB::table('products')->whereNotNull('reorder_level')->exists()) {
            $this->command?->line('Low-stock demo: thresholds already configured — skipping.');
            return;
        }

        // The first ~10 products that have a stock level get a threshold
        // tuned to put them in the Low-stock report. Mix of states so the
        // page shows both "Below threshold" and "Out of stock" badges.
        $levels = DB::table('product_stock_levels as l')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->orderBy('l.id')
            ->limit(10)
            ->get(['l.product_id', 'l.quantity']);

        if ($levels->isEmpty()) {
            $this->command?->warn('Low-stock demo: no stock levels to flag — run ProductsDemoSeeder + StockAdjustmentsDemoSeeder first.');
            return;
        }

        $now     = now();
        $updated = 0;

        foreach ($levels as $i => $level) {
            $qty = (float) $level->quantity;

            // Spread the deficits so the report looks real:
            //   first 2 → way above current ("Out of stock"-ish big deficit)
            //   next 5 → moderately above ("Below threshold")
            //   last 3 → just one or two units above (mild low)
            $reorder = match (true) {
                $i < 2  => max(80.0, $qty + 60),
                $i < 7  => $qty + 15,
                default => $qty + 2,
            };

            DB::table('products')
                ->where('id', $level->product_id)
                ->update([
                    'reorder_level' => $reorder,
                    'updated_at'    => $now,
                ]);
            $updated++;
        }

        $this->command?->info("Low-stock demo: flagged {$updated} products as low (mix of out-of-stock and below-threshold).");
    }
}
