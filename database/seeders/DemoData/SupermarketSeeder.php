<?php

namespace Database\Seeders\DemoData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Demo supermarket catalogue — weight-based produce/deli + packaged goods
 * (docs/features/installer.md §9.2).
 *
 * Self-contained: creates categories, products, and an aggregate stock level
 * each (no received-purchases supply chain). Loose items are `sold_by_weight`
 * priced per kg with a sequential `scale_plu` (so the Settings → Scale demo +
 * the cashier weight prompt have real products to ring up); everything else is
 * a normal per-piece SKU. Food categories carry the reduced FOOD tax; non-food
 * (household / personal care) carries the standard rate.
 *
 * Idempotent: products match by SKU, stock by (store, product). Safe to re-run.
 */
class SupermarketSeeder extends Seeder
{
    /** Sequential PLU assigned to weighed items, mirroring a real scale department. */
    private int $pluCursor = 100;

    public function run(): void
    {
        $now      = now();
        $unitPc   = DB::table('units')->where('code', 'pc')->value('id');
        $unitKg   = DB::table('units')->where('code', 'kg')->value('id');
        $taxFood  = DB::table('tax_groups')->where('code', 'FOOD')->value('id');
        $taxStd   = DB::table('tax_groups')->where('code', 'STD')->value('id');
        $store    = DB::table('stores')->where('is_active', true)->orderBy('id')->first();

        if (! $unitPc || ! $store) {
            $this->command?->line('Supermarket demo: missing unit "pc" or store — skipping.');
            return;
        }
        $unitKg ??= $unitPc; // fall back if the weight unit wasn't seeded

        $sortCursor = 0;
        $seq = 0;

        foreach ($this->definitions() as $cat) {
            $slug    = Str::slug($cat['category']);
            $taxId   = ($cat['food'] ?? true) ? $taxFood : $taxStd;
            $byWeight = $cat['weight'] ?? false;

            DB::table('categories')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name'         => $cat['category'],
                    'tax_group_id' => $taxId,
                    'sort_order'   => ++$sortCursor,
                    'is_active'    => true,
                    'updated_at'   => $now,
                    'created_at'   => $now,
                ],
            );
            $catId = DB::table('categories')->where('slug', $slug)->value('id');

            foreach ($cat['items'] as $item) {
                [$name, $price] = $item;
                $seq++;
                $sku = 'SM-'.sprintf('%04d', $seq);

                DB::table('products')->updateOrInsert(
                    ['sku' => $sku],
                    [
                        'name'             => $name,
                        'slug'             => Str::slug($name).'-'.Str::lower(Str::random(4)),
                        'barcode'          => $byWeight ? null : '88'.sprintf('%010d', 2000 + $seq),
                        'category_id'      => $catId,
                        'unit_id'          => $byWeight ? $unitKg : $unitPc,
                        'tax_group_id'     => $taxId,
                        'is_tax_inclusive' => true, // grocery shelf prices are tax-inclusive
                        'type'             => 'simple',
                        'cost_price'       => round($price * 0.72, 2),
                        'selling_price'    => $price,
                        'mrp'              => $price,
                        'sold_by_weight'   => $byWeight,
                        'scale_plu'        => $byWeight ? $this->pluCursor++ : null,
                        'track_stock'      => true,
                        'track_batches'    => false,
                        'reorder_level'    => $byWeight ? 5 : 24,
                        'reorder_quantity' => $byWeight ? 50 : 100,
                        'is_active'        => true,
                        'updated_at'       => $now,
                        'created_at'       => $now,
                    ],
                );
                $productId = DB::table('products')->where('sku', $sku)->value('id');

                // Weighed items carry kg on hand; packaged items carry units.
                $qty = $byWeight ? mt_rand(15, 80) : mt_rand(24, 300);
                DB::table('product_stock_levels')->updateOrInsert(
                    ['store_id' => $store->id, 'product_id' => $productId, 'variant_id' => null],
                    [
                        'quantity'              => $qty,
                        'reserved_quantity'     => 0,
                        'weighted_average_cost' => round($price * 0.72, 2),
                        'updated_at'            => $now,
                        'created_at'            => $now,
                    ],
                );
            }
        }

        $this->command?->info("Supermarket demo: seeded {$seq} grocery items (".($this->pluCursor - 100).' weighed).');
    }

    /**
     * category → food flag / weight flag + [name, price] list. ~60 SKUs.
     *
     * @return array<int, array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            ['category' => 'Fruits & Vegetables', 'weight' => true, 'food' => true, 'items' => [
                ['Bananas', 1.20], ['Apples (Royal Gala)', 2.80], ['Tomatoes', 1.90],
                ['Potatoes', 0.95], ['Onions', 1.10], ['Carrots', 1.40],
                ['Oranges', 2.20], ['Spinach', 2.60], ['Green Capsicum', 3.10],
            ]],
            ['category' => 'Meat & Seafood', 'weight' => true, 'food' => true, 'items' => [
                ['Chicken Breast', 7.50], ['Mutton Curry Cut', 12.90],
                ['Fish Fillet (Basa)', 9.20], ['Prawns (medium)', 14.50],
            ]],
            ['category' => 'Dairy & Eggs', 'weight' => false, 'food' => true, 'items' => [
                ['Full Cream Milk 1L', 1.60], ['Greek Yogurt 500g', 3.20],
                ['Cheddar Cheese Block 250g', 4.50], ['Butter 200g', 3.80],
                ['Eggs (tray of 12)', 3.40], ['Paneer 200g', 2.90],
            ]],
            ['category' => 'Bakery', 'weight' => false, 'food' => true, 'items' => [
                ['White Sandwich Bread', 1.80], ['Whole Wheat Bread', 2.10],
                ['Croissant (4 pack)', 3.50], ['Burger Buns (6)', 2.20],
                ['Chocolate Muffins (4)', 4.00],
            ]],
            ['category' => 'Staples & Grains', 'weight' => false, 'food' => true, 'items' => [
                ['Basmati Rice 5kg', 12.50], ['Wheat Flour 5kg', 6.90],
                ['Toor Dal 1kg', 3.40], ['Sugar 1kg', 1.50],
                ['Sunflower Oil 1L', 4.20], ['Salt 1kg', 0.80],
            ]],
            ['category' => 'Beverages', 'weight' => false, 'food' => true, 'items' => [
                ['Cola 1.5L', 1.90], ['Orange Juice 1L', 2.80],
                ['Mineral Water 1L', 0.70], ['Instant Coffee 100g', 5.50],
                ['Green Tea (25 bags)', 3.30], ['Energy Drink 250ml', 2.10],
            ]],
            ['category' => 'Snacks & Confectionery', 'weight' => false, 'food' => true, 'items' => [
                ['Potato Chips 150g', 2.20], ['Salted Peanuts 200g', 1.80],
                ['Chocolate Bar 100g', 1.60], ['Biscuits (assorted)', 1.40],
                ['Instant Noodles (5 pack)', 3.00],
            ]],
            ['category' => 'Frozen Foods', 'weight' => false, 'food' => true, 'items' => [
                ['Frozen Peas 1kg', 3.10], ['French Fries 1kg', 3.60],
                ['Frozen Pizza', 5.40], ['Ice Cream Tub 1L', 4.80],
            ]],
            ['category' => 'Personal Care', 'weight' => false, 'food' => false, 'items' => [
                ['Toothpaste 100g', 2.40], ['Shampoo 200ml', 4.10],
                ['Bath Soap (3 pack)', 2.90], ['Hand Wash 250ml', 2.20],
            ]],
            ['category' => 'Household', 'weight' => false, 'food' => false, 'items' => [
                ['Dish Wash Liquid 500ml', 2.70], ['Laundry Detergent 1kg', 5.90],
                ['Floor Cleaner 1L', 3.20], ['Garbage Bags (30)', 2.50],
                ['Aluminium Foil 10m', 2.10],
            ]],
        ];
    }
}
