<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Demo categories — supermarket / grocery-style hierarchy up to four
 * levels deep so tree-view drag, indentation, and the parent-picker get
 * exercised properly. Drop in via /seed?class=CategoriesDemoSeeder.
 *
 * Idempotent: rows are matched by slug. Tax-group assignment looks up
 * codes from TaxesDemoSeeder and silently skips when the group hasn't
 * been seeded yet (so this seeder works on a vanilla install too).
 */
class CategoriesDemoSeeder extends Seeder
{
    /**
     * Accent palette mirrored from CategoryRequest::COLOR_CHOICES so
     * the demo data uses the same palette the editor offers.
     */
    private const PALETTE = [
        '#F97316', '#5E6AD2', '#10B981', '#EAB308',
        '#EC4899', '#06B6D4', '#8B5CF6', '#94959B',
    ];

    /** Running counter for sort_order across the whole tree walk. */
    private int $sortCursor = 0;

    /** code → tax_group_id, populated once at the top of run(). */
    private array $taxGroupIds = [];

    public function run(): void
    {
        $now              = now();
        $this->sortCursor = 0;
        $this->taxGroupIds = DB::table('tax_groups')
            ->whereIn('code', ['STD', 'FOOD', 'EXEMPT'])
            ->pluck('id', 'code')
            ->all();

        $tree = $this->treeDefinition();
        $this->insertTree($tree, null, $now);

        $count = DB::table('categories')->whereNull('deleted_at')->count();
        $this->command?->info("Seeded category hierarchy ({$count} rows).");
    }

    /**
     * The canonical demo tree. Each node has a `name` and optional
     * `tax` code (matches a TaxesDemoSeeder code) and `children`.
     * Edit here; the rest of the seeder is generic.
     *
     * @return array<int, array{name:string, tax?:string, children?:array}>
     */
    private function treeDefinition(): array
    {
        return [
            ['name' => 'Grocery', 'tax' => 'STD', 'children' => [
                ['name' => 'Snacks', 'tax' => 'STD', 'children' => [
                    ['name' => 'Chips'],
                    ['name' => 'Cookies & Biscuits'],
                    ['name' => 'Chocolate'],
                    ['name' => 'Nuts & Trail Mix'],
                ]],
                ['name' => 'Pantry Staples', 'children' => [
                    ['name' => 'Rice & Grains',     'tax' => 'FOOD'],
                    ['name' => 'Pasta & Noodles',   'tax' => 'FOOD'],
                    ['name' => 'Canned Goods'],
                    ['name' => 'Cooking Oils',      'tax' => 'FOOD'],
                ]],
                ['name' => 'Breakfast & Cereal',    'tax' => 'FOOD'],
                ['name' => 'Condiments & Sauces'],
            ]],

            ['name' => 'Beverages', 'tax' => 'STD', 'children' => [
                ['name' => 'Soft Drinks', 'children' => [
                    ['name' => 'Cola'],
                    ['name' => 'Lemon-Lime'],
                    ['name' => 'Diet & Zero'],
                ]],
                ['name' => 'Juices'],
                ['name' => 'Coffee & Tea'],
                ['name' => 'Water',         'tax' => 'EXEMPT'],
                ['name' => 'Energy Drinks'],
            ]],

            ['name' => 'Fresh Food', 'tax' => 'FOOD', 'children' => [
                ['name' => 'Produce', 'children' => [
                    ['name' => 'Fruits', 'children' => [
                        ['name' => 'Citrus'],
                        ['name' => 'Berries'],
                        ['name' => 'Tropical'],
                    ]],
                    ['name' => 'Vegetables'],
                    ['name' => 'Herbs'],
                ]],
                ['name' => 'Dairy & Eggs', 'children' => [
                    ['name' => 'Milk'],
                    ['name' => 'Cheese', 'children' => [
                        ['name' => 'Cheddar'],
                        ['name' => 'Soft Cheese'],
                        ['name' => 'Hard Cheese'],
                    ]],
                    ['name' => 'Yogurt'],
                    ['name' => 'Eggs'],
                ]],
                ['name' => 'Meat & Seafood'],
                ['name' => 'Deli & Prepared'],
            ]],

            ['name' => 'Bakery', 'tax' => 'FOOD', 'children' => [
                ['name' => 'Bread'],
                ['name' => 'Pastries'],
                ['name' => 'Cakes'],
            ]],

            ['name' => 'Household', 'tax' => 'STD', 'children' => [
                ['name' => 'Cleaning'],
                ['name' => 'Paper Goods'],
                ['name' => 'Personal Care'],
            ]],

            ['name' => 'Tobacco & Lottery', 'children' => [
                ['name' => 'Cigarettes',     'tax' => 'STD'],
                ['name' => 'Cigars & Pipes', 'tax' => 'STD'],
                ['name' => 'Lottery Tickets','tax' => 'EXEMPT'],
            ]],
        ];
    }

    /**
     * Walks the tree depth-first, upserting each node and recursing
     * into children. `parent_id` of a child is the id we just learned
     * for its parent. `sort_order` increments once per row so the
     * tree-ordered listing in the controller matches the definition.
     */
    private function insertTree(array $nodes, ?int $parentId, $now): void
    {
        foreach ($nodes as $node) {
            $name   = $node['name'];
            $slug   = Str::slug($name);
            $taxKey = $node['tax'] ?? null;
            $taxId  = $taxKey ? ($this->taxGroupIds[$taxKey] ?? null) : null;

            DB::table('categories')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name'         => $name,
                    'parent_id'    => $parentId,
                    'color'        => self::PALETTE[crc32($slug) % count(self::PALETTE)],
                    'tax_group_id' => $taxId,
                    'sort_order'   => ++$this->sortCursor,
                    'is_active'    => true,
                    'updated_at'   => $now,
                    'created_at'   => $now,
                ],
            );

            $id = DB::table('categories')->where('slug', $slug)->value('id');

            if (!empty($node['children'])) {
                $this->insertTree($node['children'], $id, $now);
            }
        }
    }
}
