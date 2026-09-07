<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Demo brands — well-known consumer-goods brands that pair with the
 * categories seeded by {@see CategoriesDemoSeeder}. Run with
 * /seed?class=BrandsDemoSeeder.
 *
 * Generates a small colored-initial SVG per brand and stores it under
 * `storage/app/public/brands/seed-{slug}.svg`. The list view renders
 * the SVG via `<img>` so the user sees both the "with logo" and
 * "without logo" (letter fallback) states across the dataset.
 *
 * Idempotent:
 *   - matches existing rows by slug → updateOrInsert
 *   - user-uploaded logos (paths that DON'T start with `brands/seed-`)
 *     are preserved across re-seeds. Only seeder-owned SVGs get
 *     regenerated.
 */
class BrandsDemoSeeder extends Seeder
{
    private const PALETTE = [
        '#F97316', '#5E6AD2', '#10B981', '#EAB308',
        '#EC4899', '#06B6D4', '#8B5CF6', '#94959B',
        '#EF4444', '#3B82F6', '#14B8A6', '#A855F7',
    ];

    public function run(): void
    {
        $now    = now();
        $brands = $this->brandDefinitions();

        foreach ($brands as $b) {
            $slug = Str::slug($b['name']);
            $logoPath = $this->resolveLogoPath($slug, $b['name'], $b['has_logo'] ?? true);

            DB::table('brands')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name'        => $b['name'],
                    'description' => $b['description'] ?? null,
                    'logo_path'   => $logoPath,
                    'is_active'   => true,
                    'updated_at'  => $now,
                    'created_at'  => $now,
                ],
            );
        }

        $count = DB::table('brands')->whereNull('deleted_at')->count();
        $this->command?->info("Seeded brands ({$count} rows).");
    }

    /**
     * Pick / generate the logo path for one brand.
     *
     *   - If the row exists with a non-seeder logo (user upload), keep it.
     *   - If the brand is flagged `has_logo => false`, return null so the
     *     row falls back to the letter tile (demonstrates the empty state).
     *   - Otherwise write a colored-initial SVG and return its path.
     */
    private function resolveLogoPath(string $slug, string $name, bool $hasLogo): ?string
    {
        $existing = DB::table('brands')->where('slug', $slug)->value('logo_path');
        if ($existing && ! Str::startsWith($existing, 'brands/seed-')) {
            // User has uploaded a real logo — leave it alone.
            return $existing;
        }

        if (! $hasLogo) {
            // Drop any previously-seeded SVG for this brand so the row
            // genuinely shows the letter fallback.
            if ($existing) {
                Storage::disk('public')->delete($existing);
            }
            return null;
        }

        $path = "brands/seed-{$slug}.svg";
        Storage::disk('public')->put($path, $this->renderSvg($name, $slug));
        return $path;
    }

    /** Tiny SVG: rounded color tile with the brand's initial in white. */
    private function renderSvg(string $name, string $slug): string
    {
        $letter = strtoupper(mb_substr($name, 0, 1));
        $color  = self::PALETTE[crc32($slug) % count(self::PALETTE)];

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64">
    <rect width="64" height="64" rx="12" fill="{$color}"/>
    <text x="32" y="42" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Inter, sans-serif"
          font-size="32" font-weight="600" fill="#fff" text-anchor="middle">{$letter}</text>
</svg>
SVG;
    }

    /**
     * @return array<int, array{name:string, description?:string, has_logo?:bool}>
     */
    private function brandDefinitions(): array
    {
        return [
            // ── Beverages ──────────────────────────────────────────
            ['name' => 'Coca-Cola',  'description' => 'Iconic American cola — the original recipe since 1886.'],
            ['name' => 'Pepsi',      'description' => 'American cola brand by PepsiCo, founded 1893.'],
            ['name' => 'Red Bull',   'description' => 'Austrian energy drink launched in 1987.'],
            ['name' => 'Tropicana',  'description' => 'Florida-based juice brand, best known for orange juice.'],
            ['name' => 'Nescafé',    'description' => 'Nestlé instant coffee, sold in 180+ countries.'],
            ['name' => 'Lipton',     'description' => 'Tea brand owned by Lipton Teas and Infusions.'],
            ['name' => 'Evian',      'description' => 'French natural mineral water from the Alps.'],
            ['name' => 'Monster',    'description' => 'Energy drink brand, second-largest in the US.', 'has_logo' => false],
            ['name' => 'Gatorade',   'description' => 'Sports drink brand owned by PepsiCo.'],

            // ── Snacks & Confectionery ─────────────────────────────
            ['name' => "Lay's",      'description' => 'Frito-Lay potato chips — flagship of the snacks portfolio.'],
            ['name' => 'Pringles',   'description' => 'Stackable potato crisps in the trademark canister.'],
            ['name' => 'Doritos',    'description' => 'Flavored tortilla chips by Frito-Lay.'],
            ['name' => 'Oreo',       'description' => 'Sandwich cookie by Mondelez International.'],
            ['name' => 'KitKat',     'description' => 'Wafer chocolate bar, "Have a break, have a KitKat."'],
            ['name' => 'Cadbury',    'description' => 'British multinational confectionery (Mondelez since 2010).'],
            ["name" => "Hershey's",  'description' => "America's largest chocolate maker, since 1894.", 'has_logo' => false],

            // ── Pantry / Breakfast ────────────────────────────────
            ['name' => 'Heinz',      'description' => 'Sauces and condiments, famous for ketchup.'],
            ['name' => 'Maggi',      'description' => 'Nestlé brand of instant noodles, bouillons, and sauces.'],
            ['name' => 'Knorr',      'description' => 'Unilever-owned brand of stocks, soups, and sauces.'],
            ['name' => 'Barilla',    'description' => 'Italian pasta giant, family-owned since 1877.'],
            ["name" => "Kellogg's",  'description' => 'American breakfast cereals — Corn Flakes, Frosties, etc.', 'has_logo' => false],
            ['name' => 'Quaker',     'description' => 'Oats and oat-based breakfast products (PepsiCo).'],

            // ── Dairy ──────────────────────────────────────────────
            ['name' => 'Nestlé',     'description' => 'Swiss multinational; dairy, water, coffee, and more.'],
            ['name' => 'Danone',     'description' => 'French dairy multinational — yogurts and waters.'],
            ['name' => 'Amul',       'description' => 'Indian dairy cooperative founded in 1946.'],
            ['name' => 'Yoplait',    'description' => 'Yogurt brand co-owned by General Mills and Sodiaal.'],

            // ── Household / Personal care ─────────────────────────
            ['name' => 'Tide',       'description' => 'P&G laundry detergent, launched in 1946.'],
            ['name' => 'Dove',       'description' => 'Unilever personal-care brand — soap, deodorant, hair.'],
            ['name' => 'Colgate',    'description' => 'Toothpaste and oral hygiene flagship (Colgate-Palmolive).'],
            ['name' => 'Pampers',    'description' => 'P&G disposable diaper brand, since 1961.'],
            ['name' => 'Kleenex',    'description' => 'Kimberly-Clark facial tissue brand.', 'has_logo' => false],
            ['name' => 'Gillette',   'description' => 'P&G razors and shaving products.'],
        ];
    }
}
