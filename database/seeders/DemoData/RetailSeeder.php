<?php

namespace Database\Seeders\DemoData;

use Database\Seeders\BrandsDemoSeeder;
use Database\Seeders\CategoriesDemoSeeder;
use Database\Seeders\ProductsDemoSeeder;
use Illuminate\Database\Seeder;

/**
 * Demo retail catalogue (clothing, electronics, general goods, with variants).
 *
 * Retail's demo catalogue is the generic one the dev seeders already build —
 * brands → categories → variant-aware products. Stock for these arrives via
 * the received-purchases supply chain that `SeedDemoData` runs in "full" mode,
 * so this seeder intentionally does NOT seed stock itself.
 *
 * Triggered by the installer's `SeedDemoData` when the company industry is
 * retail (the default). Idempotent — the underlying seeders match by slug/SKU.
 */
class RetailSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BrandsDemoSeeder::class,
            CategoriesDemoSeeder::class,
            ProductsDemoSeeder::class,
        ]);
    }
}
