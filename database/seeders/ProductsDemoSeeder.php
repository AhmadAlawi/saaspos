<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Demo products — ~40 SKUs across the seeded categories + brands. Run
 * with /seed?class=ProductsDemoSeeder.
 *
 * Idempotent: matches existing rows by SKU → updateOrInsert.
 * Skips a row gracefully if it references a category / brand / unit
 * the corresponding demo seeder hasn't run yet.
 *
 * Logos are deliberately NOT generated (unlike BrandsDemoSeeder) —
 * product photos are too custom to fake, and the row template falls
 * back to a colored initial-letter tile when image_path is null.
 */
class ProductsDemoSeeder extends Seeder
{
    /** code/slug → id lookups, populated once at the top of run(). */
    private array $catIds   = [];
    private array $brandIds = [];
    private array $unitIds  = [];
    private array $taxIds   = [];

    public function run(): void
    {
        $now = now();

        $this->catIds   = DB::table('categories')->pluck('id', 'slug')->all();
        $this->brandIds = DB::table('brands')->pluck('id', 'slug')->all();
        $this->unitIds  = DB::table('units')->pluck('id', 'code')->all();
        $this->taxIds   = DB::table('tax_groups')->pluck('id', 'code')->all();

        $skipped = 0;
        foreach ($this->productDefinitions() as $p) {
            $catId   = $this->catIds[$p['category_slug']]    ?? null;
            $brandId = isset($p['brand_slug']) ? ($this->brandIds[$p['brand_slug']] ?? null) : null;
            $unitId  = $this->unitIds[$p['unit_code']]       ?? null;
            $taxId   = isset($p['tax_code'])  ? ($this->taxIds[$p['tax_code']]   ?? null) : null;

            // unit_id is the only non-nullable FK on products. If we can't
            // resolve it, fall back to 'pc' (piece); if that also missing,
            // skip the row entirely rather than crash.
            $unitId ??= $this->unitIds['pc'] ?? null;
            if (! $unitId) {
                $skipped++;
                continue;
            }

            $productType = $p['type'] ?? 'simple';
            $isVariant   = $productType === 'variant';

            // Variant products price per-child — the parent's own price
            // columns sit at 0 and the attribute definitions live in meta.
            $meta = $isVariant && !empty($p['attributes'])
                ? json_encode(['variant_attributes' => $p['attributes']])
                : null;

            DB::table('products')->updateOrInsert(
                ['sku' => $p['sku']],
                [
                    'name'              => $p['name'],
                    'slug'              => Str::slug($p['name']).'-'.Str::lower(Str::random(4)),
                    'barcode'           => $p['barcode']           ?? null,
                    'category_id'       => $catId,
                    'brand_id'          => $brandId,
                    'unit_id'           => $unitId,
                    'tax_group_id'      => $taxId,
                    'is_tax_inclusive'  => $p['tax_inclusive']     ?? false,
                    'description'       => $p['description']       ?? null,
                    'short_description' => $p['short_description'] ?? null,
                    'type'              => $productType,
                    'cost_price'        => $isVariant ? 0 : $p['cost'],
                    'selling_price'     => $isVariant ? 0 : $p['price'],
                    'mrp'               => $p['mrp']               ?? null,
                    'sold_by_weight'    => $p['sold_by_weight']    ?? false,
                    'track_stock'       => $p['track_stock']       ?? true,
                    'track_batches'     => $p['track_batches']     ?? false,
                    'track_expiry'      => $p['track_expiry']      ?? false,
                    'reorder_level'     => $p['reorder_level']     ?? null,
                    'reorder_quantity'  => $p['reorder_quantity']  ?? null,
                    'hsn_code'          => $p['hsn_code']          ?? null,
                    'pharmacy_schedule' => $p['pharmacy_schedule'] ?? null,
                    'generic_name'      => $p['generic_name']      ?? null,
                    'manufacturer'      => $p['manufacturer']      ?? null,
                    'meta'              => $meta,
                    'is_active'         => true,
                    'is_featured'       => $p['featured']          ?? false,
                    'updated_at'        => $now,
                    'created_at'        => $now,
                ],
            );

            // For variant products, persist the child SKUs too. Each
            // variant's `attributes` JSON holds its combination map
            // ({Strength:"500mg"}); the model derives the display label
            // by joining the values. Keyed on SKU → re-running updates in
            // place without duplicating rows.
            if ($isVariant && !empty($p['variants'])) {
                $productId = DB::table('products')->where('sku', $p['sku'])->value('id');
                foreach ($p['variants'] as $v) {
                    DB::table('product_variants')->updateOrInsert(
                        ['sku' => $v['sku']],
                        [
                            'product_id'    => $productId,
                            'barcode'       => $v['barcode']       ?? null,
                            'attributes'    => json_encode($v['combo'] ?? []),
                            'cost_price'    => $v['cost']          ?? null,
                            'selling_price' => $v['price']         ?? null,
                            'mrp'           => $v['mrp']           ?? null,
                            'is_active'     => true,
                            'updated_at'    => $now,
                            'created_at'    => $now,
                        ],
                    );
                }
            }
        }

        // Second pass — kit components. Run after every parent product
        // is in place so component SKUs resolve cleanly even when the
        // kit appears earlier in the definitions array than one of its
        // components.
        $kitRowCount = 0;
        foreach ($this->productDefinitions() as $p) {
            if (($p['type'] ?? 'simple') !== 'kit' || empty($p['kit_items'])) {
                continue;
            }
            $parentId = DB::table('products')->where('sku', $p['sku'])->value('id');
            if (! $parentId) continue;

            // Wipe + re-insert each run so quantity / order edits stay in
            // sync. product_kit_items doesn't carry soft-deletes and isn't
            // referenced from sale_items, so this is safe to do.
            DB::table('product_kit_items')->where('parent_product_id', $parentId)->delete();

            foreach (array_values($p['kit_items']) as $position => $line) {
                $componentId = DB::table('products')->where('sku', $line['component_sku'])->value('id');
                if (! $componentId || $componentId === $parentId) continue;

                $variantId = null;
                if (! empty($line['component_variant_sku'])) {
                    $variantId = DB::table('product_variants')
                        ->where('sku', $line['component_variant_sku'])
                        ->value('id');
                }

                DB::table('product_kit_items')->insert([
                    'parent_product_id'    => $parentId,
                    'component_product_id' => $componentId,
                    'component_variant_id' => $variantId,
                    'quantity'             => $line['quantity'] ?? 1,
                    'sort_order'           => $position,
                    'updated_at'           => $now,
                    'created_at'           => $now,
                ]);
                $kitRowCount++;
            }
        }

        $count        = DB::table('products')->whereNull('deleted_at')->count();
        $variantCount = DB::table('product_variants')->whereNull('deleted_at')->count();
        $this->command?->info("Seeded products ({$count} rows, {$variantCount} variants, {$kitRowCount} kit lines; {$skipped} skipped due to missing FKs).");
    }

    /**
     * Realistic catalog drawn from the demo categories + brands. Most
     * SKUs are barcode-bearing CPG items; a couple of pharmacy SKUs
     * exercise the compliance fields.
     *
     * @return array<int, array<string, mixed>>
     */
    private function productDefinitions(): array
    {
        return [
            // ── Beverages: Cola ────────────────────────────────────
            ['sku' => 'COKE-330',  'name' => 'Coca-Cola 330ml Can',         'barcode' => '5449000000996', 'category_slug' => 'cola',          'brand_slug' => 'coca-cola', 'unit_code' => 'pc', 'cost' => 0.45, 'price' => 1.20, 'mrp' => 1.50, 'featured' => true,  'short_description' => 'The classic cola.'],
            ['sku' => 'COKE-500',  'name' => 'Coca-Cola 500ml Bottle',      'barcode' => '5449000054227', 'category_slug' => 'cola',          'brand_slug' => 'coca-cola', 'unit_code' => 'pc', 'cost' => 0.75, 'price' => 1.95, 'mrp' => 2.25],
            ['sku' => 'PEPSI-330', 'name' => 'Pepsi 330ml Can',             'barcode' => '4060800141606', 'category_slug' => 'cola',          'brand_slug' => 'pepsi',     'unit_code' => 'pc', 'cost' => 0.45, 'price' => 1.20, 'mrp' => 1.50],

            // ── Beverages: Other ──────────────────────────────────
            ['sku' => 'REDB-250',  'name' => 'Red Bull Energy 250ml',       'barcode' => '9002490100070', 'category_slug' => 'energy-drinks', 'brand_slug' => 'red-bull',  'unit_code' => 'pc', 'cost' => 1.20, 'price' => 2.95, 'mrp' => 3.25],
            ['sku' => 'MNSTR-500', 'name' => 'Monster Original 500ml',      'barcode' => '5060751210016', 'category_slug' => 'energy-drinks', 'brand_slug' => 'monster',   'unit_code' => 'pc', 'cost' => 1.45, 'price' => 3.25],
            ['sku' => 'GATR-600',  'name' => 'Gatorade Lemon-Lime 600ml',                                  'category_slug' => 'energy-drinks', 'brand_slug' => 'gatorade',  'unit_code' => 'pc', 'cost' => 1.10, 'price' => 2.75],
            ['sku' => 'TROP-1L',   'name' => 'Tropicana Orange Juice 1L',   'barcode' => '5410976051213', 'category_slug' => 'juices',        'brand_slug' => 'tropicana', 'unit_code' => 'pc', 'cost' => 2.30, 'price' => 4.50],
            ['sku' => 'NESC-100',  'name' => 'Nescafé Classic 100g',                                       'category_slug' => 'coffee-tea',    'brand_slug' => 'nescafe',   'unit_code' => 'pc', 'cost' => 3.10, 'price' => 5.95],
            ['sku' => 'LIPT-25',   'name' => 'Lipton Yellow Label Tea 25 bags',                            'category_slug' => 'coffee-tea',    'brand_slug' => 'lipton',    'unit_code' => 'pc', 'cost' => 1.40, 'price' => 2.95],
            ['sku' => 'EVIN-1L',   'name' => 'Evian Mineral Water 1L',      'barcode' => '3068320055008', 'category_slug' => 'water',         'brand_slug' => 'evian',     'unit_code' => 'pc', 'cost' => 0.70, 'price' => 1.85, 'tax_code' => 'EXEMPT'],

            // ── Snacks: Chips ─────────────────────────────────────
            ['sku' => 'LAYS-CLS',  'name' => "Lay's Classic 150g",                                          'category_slug' => 'chips',         'brand_slug' => 'lays',      'unit_code' => 'pc', 'cost' => 1.10, 'price' => 2.45],
            ['sku' => 'LAYS-BBQ',  'name' => "Lay's BBQ 150g",                                              'category_slug' => 'chips',         'brand_slug' => 'lays',      'unit_code' => 'pc', 'cost' => 1.10, 'price' => 2.45],
            ['sku' => 'PRG-ORIG',  'name' => 'Pringles Original 165g',                                      'category_slug' => 'chips',         'brand_slug' => 'pringles',  'unit_code' => 'pc', 'cost' => 1.60, 'price' => 3.25],
            ['sku' => 'PRG-SCRM',  'name' => 'Pringles Sour Cream & Onion 165g',                            'category_slug' => 'chips',         'brand_slug' => 'pringles',  'unit_code' => 'pc', 'cost' => 1.60, 'price' => 3.25],
            ['sku' => 'DRTS-NCH',  'name' => 'Doritos Nacho Cheese 180g',                                   'category_slug' => 'chips',         'brand_slug' => 'doritos',   'unit_code' => 'pc', 'cost' => 1.55, 'price' => 3.10],

            // ── Snacks: Cookies & Chocolate ───────────────────────
            ['sku' => 'OREO-154', 'name' => 'Oreo Original 154g',                                           'category_slug' => 'cookies-biscuits', 'brand_slug' => 'oreo',    'unit_code' => 'pc', 'cost' => 1.30, 'price' => 2.85, 'featured' => true],
            ['sku' => 'KITKAT-4', 'name' => 'KitKat 4-Finger 41.5g',        'barcode' => '7613031237074', 'category_slug' => 'chocolate',     'brand_slug' => 'kitkat',    'unit_code' => 'pc', 'cost' => 0.55, 'price' => 1.30],
            ['sku' => 'CDBR-DM',  'name' => 'Cadbury Dairy Milk 110g',                                     'category_slug' => 'chocolate',     'brand_slug' => 'cadbury',   'unit_code' => 'pc', 'cost' => 1.20, 'price' => 2.75],

            // ── Pantry ────────────────────────────────────────────
            ['sku' => 'HNZ-KCH',  'name' => 'Heinz Tomato Ketchup 567g',                                   'category_slug' => 'condiments-sauces', 'brand_slug' => 'heinz',  'unit_code' => 'pc', 'cost' => 2.40, 'price' => 4.50],
            ['sku' => 'MAGGI-2M', 'name' => 'Maggi 2-Minute Noodles 70g x4',                              'category_slug' => 'pasta-noodles',     'brand_slug' => 'maggi',  'unit_code' => 'pc', 'cost' => 1.50, 'price' => 3.25, 'tax_code' => 'FOOD'],
            ['sku' => 'KNOR-CB',  'name' => 'Knorr Chicken Stock Cubes 8pk',                              'category_slug' => 'pantry-staples',    'brand_slug' => 'knorr',  'unit_code' => 'pc', 'cost' => 0.85, 'price' => 1.95],
            ['sku' => 'BRLA-500', 'name' => 'Barilla Spaghetti N.5 500g',   'barcode' => '8076809513746', 'category_slug' => 'pasta-noodles',     'brand_slug' => 'barilla','unit_code' => 'pc', 'cost' => 0.95, 'price' => 2.25, 'tax_code' => 'FOOD'],

            // ── Breakfast ────────────────────────────────────────
            ['sku' => 'KELL-CF',  'name' => "Kellogg's Corn Flakes 500g",                                  'category_slug' => 'breakfast-cereal','brand_slug' => 'kelloggs','unit_code' => 'pc', 'cost' => 2.10, 'price' => 4.25],
            ['sku' => 'QKR-OAT',  'name' => 'Quaker Oats Original 1kg',                                    'category_slug' => 'breakfast-cereal','brand_slug' => 'quaker',  'unit_code' => 'pc', 'cost' => 2.95, 'price' => 5.75, 'tax_code' => 'FOOD'],

            // ── Dairy ─────────────────────────────────────────────
            ['sku' => 'NSTL-MLK', 'name' => 'Nestlé Full Cream Milk 1L',                                   'category_slug' => 'milk',          'brand_slug' => 'nestle',    'unit_code' => 'pc', 'cost' => 1.20, 'price' => 2.45, 'track_expiry' => true, 'tax_code' => 'FOOD'],
            ['sku' => 'DAN-YGR',  'name' => 'Danone Strawberry Yogurt 4x125g',                             'category_slug' => 'yogurt',        'brand_slug' => 'danone',    'unit_code' => 'pc', 'cost' => 2.20, 'price' => 4.50, 'track_expiry' => true],
            ['sku' => 'AMUL-BTR', 'name' => 'Amul Butter 500g',                                            'category_slug' => 'dairy-eggs',    'brand_slug' => 'amul',      'unit_code' => 'pc', 'cost' => 3.40, 'price' => 6.25, 'track_expiry' => true],
            ['sku' => 'YOPL-MLT', 'name' => 'Yoplait Original Multipack 8x113g',                           'category_slug' => 'yogurt',        'brand_slug' => 'yoplait',   'unit_code' => 'pc', 'cost' => 3.50, 'price' => 6.95, 'track_expiry' => true],

            // ── Fresh produce — sold by weight ───────────────────
            ['sku' => 'PROD-BAN', 'name' => 'Bananas (loose)',                                             'category_slug' => 'fruits',        'unit_code' => 'kg', 'cost' => 0.85, 'price' => 1.95, 'sold_by_weight' => true, 'tax_code' => 'EXEMPT', 'short_description' => 'Sold by the kilo.'],
            ['sku' => 'PROD-APL', 'name' => 'Apples (Gala, loose)',                                        'category_slug' => 'fruits',        'unit_code' => 'kg', 'cost' => 1.40, 'price' => 3.25, 'sold_by_weight' => true, 'tax_code' => 'EXEMPT'],
            ['sku' => 'PROD-POT', 'name' => 'Potatoes (loose)',                                            'category_slug' => 'vegetables',    'unit_code' => 'kg', 'cost' => 0.55, 'price' => 1.45, 'sold_by_weight' => true, 'tax_code' => 'EXEMPT'],

            // ── Household ────────────────────────────────────────
            ['sku' => 'TIDE-1KG', 'name' => 'Tide Laundry Powder 1kg',                                     'category_slug' => 'cleaning',      'brand_slug' => 'tide',      'unit_code' => 'pc', 'cost' => 4.20, 'price' => 7.95],
            ['sku' => 'DOVE-BAR', 'name' => 'Dove Beauty Bar 100g',                                        'category_slug' => 'personal-care', 'brand_slug' => 'dove',      'unit_code' => 'pc', 'cost' => 1.10, 'price' => 2.45],
            ['sku' => 'COLG-100', 'name' => 'Colgate Total Toothpaste 100ml',                              'category_slug' => 'personal-care', 'brand_slug' => 'colgate',   'unit_code' => 'pc', 'cost' => 2.15, 'price' => 4.25],
            ['sku' => 'PMPS-MD',  'name' => 'Pampers Baby-Dry Size 4 (44ct)',                              'category_slug' => 'personal-care', 'brand_slug' => 'pampers',   'unit_code' => 'pc', 'cost' => 7.80, 'price' => 13.95],

            // ── Pharmacy compliance examples ─────────────────────
            ['sku' => 'PARA-500',
                'name' => 'Paracetamol 500mg (20 tablets)',
                'category_slug' => 'personal-care',
                'unit_code' => 'pc',
                'cost' => 0.85, 'price' => 2.45,
                'hsn_code' => '30049099',
                'pharmacy_schedule' => 'OTC',
                'generic_name' => 'Paracetamol',
                'manufacturer' => 'Cipla',
                'track_batches' => true,
                'track_expiry'  => true,
                'short_description' => 'Pain reliever / fever reducer.'],
            ['sku' => 'IBUP-200',
                'name' => 'Ibuprofen 200mg (24 tablets)',
                'category_slug' => 'personal-care',
                'unit_code' => 'pc',
                'cost' => 1.10, 'price' => 3.25,
                'hsn_code' => '30049099',
                'pharmacy_schedule' => 'OTC',
                'generic_name' => 'Ibuprofen',
                'manufacturer' => 'Sun Pharma',
                'track_batches' => true,
                'track_expiry'  => true],

            // ── Variant products ─────────────────────────────────
            // Pharmacy: a TWO-attribute matrix (Strength × Pack). The
            // parent carries the generic name + schedule; the four child
            // SKUs are every combination, each with its own barcode,
            // price, and MRP. Demonstrates the cartesian variant matrix.
            ['sku' => 'AMOX-PARENT',
                'name' => 'Amoxicillin Capsules',
                'category_slug' => 'personal-care',
                'unit_code' => 'pc',
                'cost' => 0, 'price' => 0,
                'type' => 'variant',
                'hsn_code' => '30041020',
                'pharmacy_schedule' => 'Rx',
                'generic_name' => 'Amoxicillin',
                'manufacturer' => 'Cipla',
                'track_batches' => true,
                'track_expiry'  => true,
                'short_description' => 'Broad-spectrum antibiotic — strength × pack size.',
                'attributes' => [
                    ['name' => 'Strength', 'values' => ['250mg', '500mg']],
                    ['name' => 'Pack',     'values' => ['10 capsules', '21 capsules']],
                ],
                'variants' => [
                    ['sku' => 'AMOX-250-10', 'combo' => ['Strength' => '250mg', 'Pack' => '10 capsules'], 'barcode' => '8901111000017', 'cost' => 1.20, 'price' => 2.95, 'mrp' => 3.50],
                    ['sku' => 'AMOX-250-21', 'combo' => ['Strength' => '250mg', 'Pack' => '21 capsules'], 'barcode' => '8901111000031', 'cost' => 2.30, 'price' => 5.50, 'mrp' => 6.25],
                    ['sku' => 'AMOX-500-10', 'combo' => ['Strength' => '500mg', 'Pack' => '10 capsules'], 'barcode' => '8901111000024', 'cost' => 1.85, 'price' => 4.25, 'mrp' => 5.00],
                    ['sku' => 'AMOX-500-21', 'combo' => ['Strength' => '500mg', 'Pack' => '21 capsules'], 'barcode' => '8901111000048', 'cost' => 3.50, 'price' => 7.95, 'mrp' => 8.75],
                ]],

            // Supermarket: a TWO-attribute matrix (Roast × Size) — six
            // child SKUs. Shows a non-pharmacy variant catalog entry.
            ['sku' => 'COFFEE-PARENT',
                'name' => 'House Roast Ground Coffee',
                'category_slug' => 'coffee-tea',
                'brand_slug' => 'nescafe',
                'unit_code' => 'pc',
                'cost' => 0, 'price' => 0,
                'type' => 'variant',
                'featured' => true,
                'short_description' => 'Freshly ground — pick your roast and bag size.',
                'attributes' => [
                    ['name' => 'Roast', 'values' => ['Light', 'Medium', 'Dark']],
                    ['name' => 'Size',  'values' => ['250g', '500g']],
                ],
                'variants' => [
                    ['sku' => 'COFFEE-LT-250', 'combo' => ['Roast' => 'Light',  'Size' => '250g'], 'barcode' => '8902220000016', 'cost' => 2.80, 'price' => 5.50,  'mrp' => 6.00],
                    ['sku' => 'COFFEE-LT-500', 'combo' => ['Roast' => 'Light',  'Size' => '500g'], 'barcode' => '8902220000023', 'cost' => 5.20, 'price' => 9.95,  'mrp' => 10.50],
                    ['sku' => 'COFFEE-MD-250', 'combo' => ['Roast' => 'Medium', 'Size' => '250g'], 'barcode' => '8902220000030', 'cost' => 2.80, 'price' => 5.50,  'mrp' => 6.00],
                    ['sku' => 'COFFEE-MD-500', 'combo' => ['Roast' => 'Medium', 'Size' => '500g'], 'barcode' => '8902220000047', 'cost' => 5.20, 'price' => 9.95,  'mrp' => 10.50],
                    ['sku' => 'COFFEE-DK-250', 'combo' => ['Roast' => 'Dark',   'Size' => '250g'], 'barcode' => '8902220000054', 'cost' => 3.00, 'price' => 5.95,  'mrp' => 6.50],
                    ['sku' => 'COFFEE-DK-500', 'combo' => ['Roast' => 'Dark',   'Size' => '500g'], 'barcode' => '8902220000061', 'cost' => 5.60, 'price' => 10.95, 'mrp' => 11.50],
                ]],

            // Retail: single-attribute matrix (Flavor). Each variant has
            // its own scannable barcode + MRP.
            ['sku' => 'LAYS-PARTY',
                'name' => "Lay's Party Pack 250g",
                'category_slug' => 'chips',
                'brand_slug' => 'lays',
                'unit_code' => 'pc',
                'cost' => 0, 'price' => 0,
                'type' => 'variant',
                'featured' => true,
                'short_description' => 'Big-bag flavors for sharing.',
                'attributes' => [
                    ['name' => 'Flavor', 'values' => ['Classic Salted', 'BBQ', 'Salt & Vinegar', 'Cheese & Onion']],
                ],
                'variants' => [
                    ['sku' => 'LAYS-PARTY-CLS', 'combo' => ['Flavor' => 'Classic Salted'], 'barcode' => '0028400090000', 'cost' => 1.80, 'price' => 3.95, 'mrp' => 4.50],
                    ['sku' => 'LAYS-PARTY-BBQ', 'combo' => ['Flavor' => 'BBQ'],            'barcode' => '0028400090017', 'cost' => 1.80, 'price' => 3.95, 'mrp' => 4.50],
                    ['sku' => 'LAYS-PARTY-SV',  'combo' => ['Flavor' => 'Salt & Vinegar'], 'barcode' => '0028400090024', 'cost' => 1.80, 'price' => 4.25, 'mrp' => 4.95],
                    ['sku' => 'LAYS-PARTY-CO',  'combo' => ['Flavor' => 'Cheese & Onion'], 'barcode' => '0028400090031', 'cost' => 1.80, 'price' => 4.25, 'mrp' => 4.95],
                ]],

            // ── Kit / bundle products ───────────────────────────
            // A "movie night" snack bundle — one ringable SKU that
            // discounts a cola + chips + chocolate combo. Components
            // reference existing demo SKUs by their string SKU; the
            // seeder resolves them to ids on insert.
            ['sku' => 'KIT-MOVIE',
                'name' => 'Movie Night Bundle',
                'category_slug' => 'chips',
                'unit_code' => 'pc',
                'cost' => 2.85, 'price' => 5.50, 'mrp' => 6.50,
                'type' => 'kit',
                'featured' => true,
                'short_description' => 'Cola + chips + chocolate, for the couch.',
                'kit_items' => [
                    ['component_sku' => 'COKE-500', 'quantity' => 1],
                    ['component_sku' => 'LAYS-CLS', 'quantity' => 1],
                    ['component_sku' => 'KITKAT-4', 'quantity' => 2],
                ]],

            // A breakfast bundle of pantry staples.
            ['sku' => 'KIT-BFAST',
                'name' => 'Breakfast Starter Pack',
                'category_slug' => 'breakfast-cereal',
                'unit_code' => 'pc',
                'cost' => 5.20, 'price' => 9.50, 'mrp' => 11.00,
                'type' => 'kit',
                'short_description' => 'Cereal, milk, and tea — start the morning right.',
                'kit_items' => [
                    ['component_sku' => 'KELL-CF',  'quantity' => 1],
                    ['component_sku' => 'NSTL-MLK', 'quantity' => 1],
                    ['component_sku' => 'LIPT-25',  'quantity' => 1],
                ]],

            // A kit that locks a component to a SPECIFIC variant — the
            // "BBQ" flavor of the Lay's Party Pack, plus a cola. Shows
            // how a bundle can pin one variant of a variant product.
            ['sku' => 'KIT-GAMEDAY',
                'name' => 'Game Day Combo',
                'category_slug' => 'chips',
                'unit_code' => 'pc',
                'cost' => 2.20, 'price' => 4.95, 'mrp' => 5.75,
                'type' => 'kit',
                'short_description' => 'BBQ party chips + a cola for game night.',
                'kit_items' => [
                    ['component_sku' => 'LAYS-PARTY', 'component_variant_sku' => 'LAYS-PARTY-BBQ', 'quantity' => 1],
                    ['component_sku' => 'COKE-330', 'quantity' => 2],
                ]],

            // ── More variant products ───────────────────────────
            // Beverage in multiple sizes — single "Size" attribute.
            ['sku' => 'PEPSI-PARENT',
                'name' => 'Pepsi',
                'category_slug' => 'cola',
                'brand_slug' => 'pepsi',
                'unit_code' => 'pc',
                'cost' => 0, 'price' => 0,
                'type' => 'variant',
                'short_description' => 'Pick your size.',
                'attributes' => [
                    ['name' => 'Size', 'values' => ['330ml Can', '500ml Bottle', '1.5L Bottle']],
                ],
                'variants' => [
                    ['sku' => 'PEPSI-V-330', 'combo' => ['Size' => '330ml Can'],    'barcode' => '8903330000016', 'cost' => 0.45, 'price' => 1.20, 'mrp' => 1.50],
                    ['sku' => 'PEPSI-V-500', 'combo' => ['Size' => '500ml Bottle'], 'barcode' => '8903330000023', 'cost' => 0.75, 'price' => 1.95, 'mrp' => 2.25],
                    ['sku' => 'PEPSI-V-15L', 'combo' => ['Size' => '1.5L Bottle'],  'barcode' => '8903330000030', 'cost' => 1.10, 'price' => 2.95, 'mrp' => 3.50],
                ]],

            // Personal-care soap by scent — single "Scent" attribute.
            ['sku' => 'DOVE-PARENT',
                'name' => 'Dove Beauty Bar',
                'category_slug' => 'personal-care',
                'brand_slug' => 'dove',
                'unit_code' => 'pc',
                'cost' => 0, 'price' => 0,
                'type' => 'variant',
                'short_description' => 'Gentle cleansing — choose a scent.',
                'attributes' => [
                    ['name' => 'Scent', 'values' => ['Original', 'Cucumber', 'Shea Butter']],
                ],
                'variants' => [
                    ['sku' => 'DOVE-ORIG', 'combo' => ['Scent' => 'Original'],    'barcode' => '8903330001013', 'cost' => 1.10, 'price' => 2.45, 'mrp' => 2.95],
                    ['sku' => 'DOVE-CUKE', 'combo' => ['Scent' => 'Cucumber'],    'barcode' => '8903330001020', 'cost' => 1.10, 'price' => 2.45, 'mrp' => 2.95],
                    ['sku' => 'DOVE-SHEA', 'combo' => ['Scent' => 'Shea Butter'], 'barcode' => '8903330001037', 'cost' => 1.20, 'price' => 2.75, 'mrp' => 3.25],
                ]],

            // Laundry powder in pack sizes — single "Size" attribute.
            ['sku' => 'TIDE-PARENT',
                'name' => 'Tide Laundry Powder',
                'category_slug' => 'cleaning',
                'brand_slug' => 'tide',
                'unit_code' => 'pc',
                'cost' => 0, 'price' => 0,
                'type' => 'variant',
                'short_description' => 'Stain-fighting powder, three pack sizes.',
                'attributes' => [
                    ['name' => 'Size', 'values' => ['1kg', '2kg', '4kg']],
                ],
                'variants' => [
                    ['sku' => 'TIDE-V-1', 'combo' => ['Size' => '1kg'], 'barcode' => '8903330002010', 'cost' => 4.20,  'price' => 7.95,  'mrp' => 8.95],
                    ['sku' => 'TIDE-V-2', 'combo' => ['Size' => '2kg'], 'barcode' => '8903330002027', 'cost' => 7.80,  'price' => 14.50, 'mrp' => 15.95],
                    ['sku' => 'TIDE-V-4', 'combo' => ['Size' => '4kg'], 'barcode' => '8903330002034', 'cost' => 14.50, 'price' => 26.95, 'mrp' => 28.95],
                ]],

            // Pharmacy OTC by pack size — single "Pack" attribute.
            ['sku' => 'CROCIN-PARENT',
                'name' => 'Crocin Advance',
                'category_slug' => 'personal-care',
                'unit_code' => 'pc',
                'cost' => 0, 'price' => 0,
                'type' => 'variant',
                'hsn_code' => '30049099',
                'pharmacy_schedule' => 'OTC',
                'generic_name' => 'Paracetamol',
                'manufacturer' => 'GSK',
                'track_batches' => true,
                'track_expiry'  => true,
                'short_description' => 'Fast pain & fever relief — choose pack size.',
                'attributes' => [
                    ['name' => 'Pack', 'values' => ['10 tablets', '15 tablets', '20 tablets']],
                ],
                'variants' => [
                    ['sku' => 'CROCIN-10', 'combo' => ['Pack' => '10 tablets'], 'barcode' => '8903330003017', 'cost' => 0.40, 'price' => 1.20, 'mrp' => 1.50],
                    ['sku' => 'CROCIN-15', 'combo' => ['Pack' => '15 tablets'], 'barcode' => '8903330003024', 'cost' => 0.55, 'price' => 1.70, 'mrp' => 2.00],
                    ['sku' => 'CROCIN-20', 'combo' => ['Pack' => '20 tablets'], 'barcode' => '8903330003031', 'cost' => 0.70, 'price' => 2.20, 'mrp' => 2.60],
                ]],

            // Juice — TWO-attribute matrix (Flavor × Size) = 6 variants.
            ['sku' => 'TROP-PARENT',
                'name' => 'Tropicana Juice',
                'category_slug' => 'juices',
                'brand_slug' => 'tropicana',
                'unit_code' => 'pc',
                'cost' => 0, 'price' => 0,
                'type' => 'variant',
                'featured' => true,
                'short_description' => 'Not-from-concentrate — flavor × size.',
                'attributes' => [
                    ['name' => 'Flavor', 'values' => ['Orange', 'Apple', 'Mixed Fruit']],
                    ['name' => 'Size',   'values' => ['200ml', '1L']],
                ],
                'variants' => [
                    ['sku' => 'TROP-OR-200', 'combo' => ['Flavor' => 'Orange',      'Size' => '200ml'], 'barcode' => '8903330004014', 'cost' => 0.70, 'price' => 1.50, 'mrp' => 1.75],
                    ['sku' => 'TROP-OR-1L',  'combo' => ['Flavor' => 'Orange',      'Size' => '1L'],    'barcode' => '8903330004021', 'cost' => 2.30, 'price' => 4.50, 'mrp' => 4.95],
                    ['sku' => 'TROP-AP-200', 'combo' => ['Flavor' => 'Apple',       'Size' => '200ml'], 'barcode' => '8903330004038', 'cost' => 0.70, 'price' => 1.50, 'mrp' => 1.75],
                    ['sku' => 'TROP-AP-1L',  'combo' => ['Flavor' => 'Apple',       'Size' => '1L'],    'barcode' => '8903330004045', 'cost' => 2.30, 'price' => 4.50, 'mrp' => 4.95],
                    ['sku' => 'TROP-MX-200', 'combo' => ['Flavor' => 'Mixed Fruit', 'Size' => '200ml'], 'barcode' => '8903330004052', 'cost' => 0.75, 'price' => 1.60, 'mrp' => 1.95],
                    ['sku' => 'TROP-MX-1L',  'combo' => ['Flavor' => 'Mixed Fruit', 'Size' => '1L'],    'barcode' => '8903330004069', 'cost' => 2.40, 'price' => 4.75, 'mrp' => 5.25],
                ]],

            // ── More kit / bundle products ──────────────────────
            ['sku' => 'KIT-CLEAN',
                'name' => 'Cleaning Essentials',
                'category_slug' => 'cleaning',
                'unit_code' => 'pc',
                'cost' => 7.45, 'price' => 13.95, 'mrp' => 15.95,
                'type' => 'kit',
                'short_description' => 'Detergent, soap, and toothpaste in one pack.',
                'kit_items' => [
                    ['component_sku' => 'TIDE-1KG', 'quantity' => 1],
                    ['component_sku' => 'DOVE-BAR', 'quantity' => 2],
                    ['component_sku' => 'COLG-100', 'quantity' => 1],
                ]],

            ['sku' => 'KIT-LUNCH',
                'name' => 'Lunchbox Combo',
                'category_slug' => 'pantry-staples',
                'unit_code' => 'pc',
                'cost' => 4.90, 'price' => 9.50, 'mrp' => 10.95,
                'type' => 'kit',
                'short_description' => 'Noodles, juice, and a chocolate treat.',
                'kit_items' => [
                    ['component_sku' => 'MAGGI-2M', 'quantity' => 1],
                    ['component_sku' => 'TROP-1L',  'quantity' => 1],
                    ['component_sku' => 'KITKAT-4', 'quantity' => 2],
                ]],

            ['sku' => 'KIT-TEATIME',
                'name' => 'Tea Time Set',
                'category_slug' => 'coffee-tea',
                'unit_code' => 'pc',
                'cost' => 2.70, 'price' => 5.25, 'mrp' => 6.00,
                'type' => 'kit',
                'short_description' => 'Tea bags and biscuits for the afternoon.',
                'kit_items' => [
                    ['component_sku' => 'LIPT-25',  'quantity' => 1],
                    ['component_sku' => 'OREO-154', 'quantity' => 1],
                ]],

            ['sku' => 'KIT-FIRSTAID',
                'name' => 'First Aid Basics',
                'category_slug' => 'personal-care',
                'unit_code' => 'pc',
                'cost' => 1.95, 'price' => 5.25, 'mrp' => 6.00,
                'type' => 'kit',
                'short_description' => 'Everyday pain & fever relief tablets.',
                'kit_items' => [
                    ['component_sku' => 'PARA-500', 'quantity' => 1],
                    ['component_sku' => 'IBUP-200', 'quantity' => 1],
                ]],
        ];
    }
}
