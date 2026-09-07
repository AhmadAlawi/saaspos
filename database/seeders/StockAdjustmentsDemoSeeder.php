<?php

namespace Database\Seeders;

use App\Actions\Inventory\CreateStockAdjustment;
use App\Actions\Inventory\PostStockAdjustment;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentReason;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo Stock Adjustments — a small mix that shows every flow the user
 * is likely to hit on day one:
 *
 *   1. POSTED  · Opening stock for ~10 SKUs (positive deltas, with cost)
 *      — gives the rest of the inventory pages something to display.
 *   2. POSTED  · A recount correction (positive + negative deltas)
 *      — demonstrates the "fix a miscount" workflow.
 *   3. POSTED  · A damaged write-off (negative deltas)
 *      — shows the audit trail for shrinkage.
 *   4. DRAFT   · An unfinished expired-goods write-off
 *      — surfaces the draft-vs-posted UI states.
 *
 * Idempotent. If posted demo documents already exist they're left alone;
 * the seeder only adds what's missing.
 *
 *   /seed?class=StockAdjustmentsDemoSeeder
 *
 * Depends on:
 *   - At least one store and one user
 *   - Some seeded products (`ProductsDemoSeeder`)
 *   - The reasons picklist (`StockAdjustmentReasonsSeeder`)
 */
class StockAdjustmentsDemoSeeder extends Seeder
{
    public function run(
        CreateStockAdjustment $create,
        PostStockAdjustment $post,
    ): void {
        $store = Store::query()->where('is_active', true)->orderBy('id')->first();
        $user  = User::query()->orderBy('id')->first();
        if (! $store || ! $user) {
            $this->command?->warn('Skipping — need at least one store and one user.');
            return;
        }

        $reasons   = StockAdjustmentReason::query()->pluck('id', 'code');
        $available = Product::query()->whereNull('deleted_at')->orderBy('id')->limit(12)->get();
        if ($available->count() < 4) {
            $this->command?->warn('Skipping — need at least four seeded products (run ProductsDemoSeeder).');
            return;
        }

        // Already-seeded check — if the demo numbers exist, bail out.
        if (StockAdjustment::query()->where('reason', 'like', '[DEMO] %')->exists()) {
            $this->command?->line('Stock adjustment demo data already present.');
            return;
        }

        // ── 1. POSTED · Opening stock ────────────────────────────────
        $opening = $create([
            'store_id'        => $store->id,
            'adjustment_date' => now()->subDays(30)->toDateString(),
            'reason_code_id'  => $reasons['opening_stock'] ?? null,
            'reason'          => '[DEMO] Opening stock — initial inventory',
            'notes'           => "Seeded by StockAdjustmentsDemoSeeder. Sets the\nopening on-hand for the first batch of demo SKUs.",
            'items'           => $available->take(10)->map(fn ($p) => [
                'product_id'     => $p->id,
                'quantity_delta' => random_int(40, 200),
                'unit_cost'      => (float) ($p->cost_price ?: 10),
            ])->all(),
        ], $user);
        $post($opening, $user);
        $this->command?->info("Posted {$opening->number} (opening stock, 10 lines).");

        // ── 2. POSTED · Recount correction (mixed +/-) ───────────────
        $recount = $create([
            'store_id'        => $store->id,
            'adjustment_date' => now()->subDays(7)->toDateString(),
            'reason_code_id'  => $reasons['physical_count'] ?? null,
            'reason'          => '[DEMO] Weekly recount — small over/short fixes',
            'notes'           => 'A few SKUs were off by a couple of units after the Saturday count.',
            'items'           => [
                ['product_id' => $available[0]->id, 'quantity_delta' =>  2, 'notes' => 'Found 2 extra in storeroom'],
                ['product_id' => $available[1]->id, 'quantity_delta' => -3, 'notes' => 'Short by 3 — possible miscount on receipt'],
                ['product_id' => $available[2]->id, 'quantity_delta' =>  1],
            ],
        ], $user);
        $post($recount, $user);
        $this->command?->info("Posted {$recount->number} (recount correction).");

        // ── 3. POSTED · Damaged write-off (negative deltas) ──────────
        $damaged = $create([
            'store_id'        => $store->id,
            'adjustment_date' => now()->subDays(2)->toDateString(),
            'reason_code_id'  => $reasons['damaged'] ?? null,
            'reason'          => '[DEMO] Water damage in aisle 4',
            'notes'           => 'Roof leak overnight. 3 SKUs unsellable.',
            'items'           => [
                ['product_id' => $available[3]->id, 'quantity_delta' => -5, 'notes' => 'Cardboard soaked through'],
                ['product_id' => $available[4]->id ?? $available[3]->id, 'quantity_delta' => -2],
                ['product_id' => $available[5]->id ?? $available[0]->id, 'quantity_delta' => -1],
            ],
        ], $user);
        $post($damaged, $user);
        $this->command?->info("Posted {$damaged->number} (damaged write-off).");

        // ── 4. DRAFT · Unfinished expired-goods write-off ────────────
        $draft = $create([
            'store_id'        => $store->id,
            'adjustment_date' => now()->toDateString(),
            'reason_code_id'  => $reasons['expired'] ?? null,
            'reason'          => '[DEMO] Monthly expiry sweep — pending review',
            'notes'           => 'Still pulling from shelves. Will post once everything is counted.',
            'items'           => [
                ['product_id' => $available[6]->id ?? $available[0]->id, 'quantity_delta' => -4, 'notes' => 'Past use-by'],
                ['product_id' => $available[7]->id ?? $available[1]->id, 'quantity_delta' => -2],
            ],
        ], $user);
        $this->command?->info("Created draft {$draft->number} (not yet posted).");

        $this->command?->info('Stock adjustment demo data complete.');
    }
}
