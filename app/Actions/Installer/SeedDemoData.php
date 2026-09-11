<?php

namespace App\Actions\Installer;

use Database\Seeders\CustomerGroupsSeeder;
use Database\Seeders\CustomersDemoSeeder;
use Database\Seeders\DemoRoleUsersSeeder;
use Database\Seeders\DemoData\PharmacySeeder;
use Database\Seeders\DemoData\RetailSeeder;
use Database\Seeders\DemoData\SupermarketSeeder;
use Database\Seeders\HistoricalSalesDemoSeeder;
use Database\Seeders\LowStockDemoSeeder;
use Database\Seeders\ExpensesDemoSeeder;
use Database\Seeders\PurchasesDemoSeeder;
use Database\Seeders\StockAdjustmentsDemoSeeder;
use Database\Seeders\SuppliersDemoSeeder;
use Database\Seeders\TaxesDemoSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the optional demo dataset chosen at installer Step 6
 * (docs/features/installer.md §6 + §9).
 *
 * Reuses the existing idempotent, FK-graceful demo seeders (the same ones the
 * dev bootstrap exposes via /seed?class=…). They target the installer's MAIN
 * store — every store-aware seeder picks the first active store — so they slot
 * straight into a fresh install. `StoresDemoSeeder` is deliberately excluded:
 * a real customer wants their one store, not extra demo branches.
 *
 *   - none     → seed nothing (empty install)
 *   - minimal  → catalog + customers, no stock movements or history
 *   - full     → + suppliers + received purchases (real stock) + adjustments
 *
 * Historical SALES (90 days, §9.3) and industry-specific catalogs
 * (pharmacy batches, supermarket weight items, §9.2) are separate follow-up
 * slices; this wires the foundation so Step 6 stops seeding nothing.
 *
 * Fires `installer.before_seed_demo` / `installer.after_seed_demo` (§15).
 */
class SeedDemoData
{
    /**
     * The seeder classes that run for a given mode + industry, in dependency
     * order.
     *
     * The catalogue is industry-specific (docs §9.2): retail uses the generic
     * variant catalogue (whose stock arrives via received purchases below);
     * pharmacy + supermarket seed their own industry catalogue AND their own
     * stock (batches / weight items), so they skip the retail supply chain.
     * Customers + the 90-day sales history are industry-agnostic.
     *
     * @return array<int, class-string>
     */
    public function seedersFor(string $mode, string $industry = 'retail'): array
    {
        if ($mode === 'none') {
            return [];
        }

        $catalogSeeder = match ($industry) {
            'pharmacy'    => PharmacySeeder::class,
            'supermarket' => SupermarketSeeder::class,
            default       => RetailSeeder::class,
        };

        $catalog = [
            // A working demo login per role (the login page shows their
            // credentials when demo mode is on). Self-guards on demo mode.
            DemoRoleUsersSeeder::class,
            TaxesDemoSeeder::class,
            CustomerGroupsSeeder::class,
            $catalogSeeder,
            CustomersDemoSeeder::class,
        ];

        if ($mode !== 'full') {
            return $catalog;
        }

        // Retail's stock comes from received purchases (+ a couple of
        // adjustments + a low-stock situation). Pharmacy/supermarket already
        // seeded their stock with the catalogue, so they skip all that.
        $supplyChain = $industry === 'retail'
            ? [
                SuppliersDemoSeeder::class,
                PurchasesDemoSeeder::class,
                StockAdjustmentsDemoSeeder::class,
                LowStockDemoSeeder::class,
            ]
            : [];

        // Historical sales run last for every industry — they need products,
        // customers, and (ideally) stock already in place. Expenses are
        // universal (rent/utilities/salaries/…) so they seed for every industry.
        return array_merge($catalog, $supplyChain, [
            HistoricalSalesDemoSeeder::class,
            ExpensesDemoSeeder::class,
        ]);
    }

    /**
     * @return array{ok: bool, mode: string, output?: string}
     */
    public function __invoke(string $mode): array
    {
        $industry = (string) (DB::table('company')->value('industry') ?: 'retail');
        $seeders = $this->seedersFor($mode, $industry);
        if ($seeders === []) {
            return ['ok' => true, 'mode' => $mode];
        }

        do_action('installer.before_seed_demo', $mode);

        try {
            foreach ($seeders as $seeder) {
                Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);
            }
        } catch (\Throwable $e) {
            // Demo data is optional — a seeding failure must not abort the
            // install. The caller records the outcome and the customer can
            // re-seed later from the admin.
            report($e);

            return ['ok' => false, 'mode' => $mode, 'output' => $e->getMessage()];
        }

        do_action('installer.after_seed_demo', $mode, ['seeders' => count($seeders)]);

        return ['ok' => true, 'mode' => $mode, 'output' => Artisan::output()];
    }
}
