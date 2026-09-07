<?php

namespace Database\Seeders;

use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Bulk catalog generator — for load-testing the Products list, the cashier's
 * offline catalog sync, search, and the label/export paths at realistic scale.
 *
 * This is NOT demo data. It exists so you can answer "does this screen still
 * work at 5,000 SKUs?" without hand-building a catalog. The rows are
 * deliberately boring but *varied*: names, prices and flags spread out enough
 * that search, sort, the margin thresholds and the status filters all have
 * something to chew on.
 *
 * Run it:
 *   /seed?class=BulkProductsSeeder              → 5,000 products
 *   /seed?class=BulkProductsSeeder&count=20000  → 20,000 products
 *
 * Idempotent. Every SKU is deterministic (`PERF-000001`…), the pseudo-random
 * stream is seeded from the row number, and rows are written with
 * `insertOrIgnore`. Re-running never duplicates and never mutates what's
 * already there. Raising `count` just tops the catalog up.
 *
 * Clean up (every row is prefixed, so nothing else is touched):
 *   DELETE FROM products WHERE sku LIKE 'PERF-%';
 *
 * Notes:
 *  - Every product is `type = 'simple'`. Variant/kit rows would need child
 *    records to be coherent, and this seeder's job is row count, not fixtures.
 *  - No stock levels are created. Products exist in the catalog; they have no
 *    on-hand quantity until you receive or adjust them.
 *  - `unit_id` is the only required FK. Run `UnitsSeeder` first (DatabaseSeeder
 *    does). Categories and brands are optional and used when present.
 */
class BulkProductsSeeder extends Seeder
{
    private const SKU_PREFIX = 'PERF-';
    private const DEFAULT_COUNT = 5000;
    private const CHUNK = 500;

    /** Overridable by tests; when > 0 it wins over the `count` query param. */
    public int $count = 0;

    public function run(): void
    {
        $target = $this->targetCount();

        $unitIds = DB::table('units')->pluck('id')->all();
        if (! $unitIds) {
            $this->command?->error('No units found. Run UnitsSeeder first — unit_id is required on products.');

            return;
        }

        $categoryIds = DB::table('categories')->pluck('id')->all() ?: [null];
        $brandIds    = DB::table('brands')->pluck('id')->all();
        // A product without a brand is normal, so let null win about a third
        // of the time rather than forcing every row to carry one.
        $brandIds    = $brandIds ? [...$brandIds, null, null] : [null];

        $now  = now();
        $rows = [];
        $written = 0;

        for ($i = 1; $i <= $target; $i++) {
            $rows[] = $this->buildRow($i, $unitIds, $categoryIds, $brandIds, $now);

            if (count($rows) === self::CHUNK) {
                $written += $this->flush($rows);
                $rows = [];
                $this->command?->getOutput()?->write('.');
            }
        }

        $written += $this->flush($rows);

        $this->command?->getOutput()?->writeln('');
        $this->command?->info("Bulk catalog: {$target} SKUs targeted, {$written} newly inserted.");
        $this->command?->info('Remove them with: DELETE FROM products WHERE sku LIKE \''.self::SKU_PREFIX.'%\';');
    }

    /**
     * `insertOrIgnore` rather than `updateOrInsert`: 5,000 individual
     * select-then-write round trips is minutes of work, and one bulk insert per
     * chunk is milliseconds. The unique `sku` index does the deduplicating.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return int rows actually inserted (0 on a re-run)
     */
    private function flush(array $rows): int
    {
        return $rows ? DB::table('products')->insertOrIgnore($rows) : 0;
    }

    /**
     * One product. Seeding `mt_srand` from the row number makes the whole
     * catalog reproducible: row 4,217 has the same price and flags on every
     * machine and every re-run, so a bug you find at scale is a bug you can
     * find again.
     *
     * @param  list<int>       $unitIds
     * @param  list<int|null>  $categoryIds
     * @param  list<int|null>  $brandIds
     * @return array<string, mixed>
     */
    private function buildRow(int $i, array $unitIds, array $categoryIds, array $brandIds, CarbonInterface $now): array
    {
        mt_srand($i);

        $name = $this->name();

        // Cost 10.00–500.00, marked up 10%–90%. That spread straddles the
        // list's margin colour thresholds (<20% danger, >=35% positive).
        $cost   = mt_rand(1000, 50000) / 100;
        $markup = mt_rand(110, 190) / 100;
        $price  = round($cost * $markup, 2);

        return [
            'sku'           => self::SKU_PREFIX.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
            // 13 digits, EAN-13 shaped. The '2' prefix is the in-store range,
            // so these can't collide with a real manufacturer barcode.
            'barcode'       => '2'.str_pad((string) $i, 12, '0', STR_PAD_LEFT),
            'name'          => $name,
            'slug'          => 'perf-'.$i,
            'category_id'   => $categoryIds[$i % count($categoryIds)],
            'brand_id'      => $brandIds[$i % count($brandIds)],
            'unit_id'       => $unitIds[$i % count($unitIds)],
            'type'          => 'simple',
            'cost_price'    => $cost,
            'selling_price' => $price,
            'mrp'           => round($price * 1.1, 2),
            'track_stock'   => true,
            // ~90% active, ~8% featured — enough of each that the status and
            // featured filters return a non-trivial page.
            'is_active'     => $i % 10 !== 0,
            'is_featured'   => $i % 12 === 0,
            'created_at'    => $now,
            'updated_at'    => $now,
        ];
    }

    /**
     * Names built from three word lists rather than `Product 1234`, so that
     * `LIKE %term%` search returns a realistic scatter of hits instead of
     * everything or nothing. Draws from the `mt_srand` stream `buildRow` seeded.
     */
    private function name(): string
    {
        $adjectives = ['Classic', 'Premium', 'Organic', 'Fresh', 'Deluxe', 'Value', 'Original', 'Extra', 'Pure', 'Golden'];
        $nouns      = ['Biscuits', 'Shampoo', 'Noodles', 'Detergent', 'Coffee', 'Chocolate', 'Soap', 'Rice', 'Juice', 'Toothpaste', 'Cereal', 'Tea'];
        $sizes      = ['100g', '250g', '500g', '1kg', '200ml', '500ml', '1L', '6 Pack', '12 Pack'];

        return sprintf(
            '%s %s %s',
            $adjectives[mt_rand(0, count($adjectives) - 1)],
            $nouns[mt_rand(0, count($nouns) - 1)],
            $sizes[mt_rand(0, count($sizes) - 1)],
        );
    }

    /** Test override → `?count=` on /seed → default. */
    private function targetCount(): int
    {
        if ($this->count > 0) {
            return $this->count;
        }

        $requested = (int) request()->query('count', 0);

        return $requested > 0 ? min($requested, 200_000) : self::DEFAULT_COUNT;
    }
}
