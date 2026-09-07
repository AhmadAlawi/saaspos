<?php

namespace Database\Seeders\DemoData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Demo pharmacy catalogue — schedule-tracked medicines with batches + expiry,
 * generic names, manufacturers, and HSN codes (docs/features/installer.md §9.2).
 *
 * Unlike retail, a pharmacy's stock lives in dated BATCHES (FEFO), so this
 * seeder is self-contained: it creates the categories, the products
 * (track_batches + track_expiry), one or two batches each — a few expiring
 * soon so the expiry dashboard has something to flag — and the matching
 * aggregate stock level. No received-purchases supply chain is needed.
 *
 * Idempotent: products match by SKU, batches by (store, product, number),
 * stock by (store, product). Safe to re-run.
 */
class PharmacySeeder extends Seeder
{
    private array $manufacturers = [
        'Cipla', 'Sun Pharma', 'Dr. Reddy\'s', 'GSK', 'Pfizer', 'Abbott', 'Mankind', 'Lupin',
    ];

    public function run(): void
    {
        $now      = now();
        $unitPc   = DB::table('units')->where('code', 'pc')->value('id');
        $taxExempt = DB::table('tax_groups')->where('code', 'EXEMPT')->value('id');
        $taxStd   = DB::table('tax_groups')->where('code', 'STD')->value('id');
        $store    = DB::table('stores')->where('is_active', true)->orderBy('id')->first();

        if (! $unitPc || ! $store) {
            $this->command?->line('Pharmacy demo: missing unit "pc" or store — skipping.');
            return;
        }

        $sortCursor = 0;
        $seq = 0;

        foreach ($this->definitions() as $cat) {
            $slug  = Str::slug($cat['category']);
            $taxId = ($cat['otc'] ?? false) ? $taxStd : $taxExempt;

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

            foreach ($cat['drugs'] as $drug) {
                [$name, $generic, $price] = $drug;
                $seq++;
                $sku  = 'RX-'.sprintf('%04d', $seq);
                $cost = round($price * 0.6, 2);

                DB::table('products')->updateOrInsert(
                    ['sku' => $sku],
                    [
                        'name'              => $name,
                        'slug'              => Str::slug($name).'-'.Str::lower(Str::random(4)),
                        'barcode'           => '89'.sprintf('%010d', 1000 + $seq),
                        'category_id'       => $catId,
                        'unit_id'           => $unitPc,
                        'tax_group_id'      => $taxId,
                        'is_tax_inclusive'  => false,
                        'type'              => 'simple',
                        'cost_price'        => $cost,
                        'selling_price'     => $price,
                        'mrp'               => $price,
                        'sold_by_weight'    => false,
                        'track_stock'       => true,
                        'track_batches'     => true,
                        'track_expiry'      => true,
                        'reorder_level'     => 20,
                        'reorder_quantity'  => 100,
                        'hsn_code'          => $cat['hsn'] ?? '3004',
                        'pharmacy_schedule' => $cat['schedule'] ?? null,
                        'generic_name'      => $generic,
                        'manufacturer'      => $this->manufacturers[$seq % count($this->manufacturers)],
                        'is_active'         => true,
                        'updated_at'        => $now,
                        'created_at'        => $now,
                    ],
                );
                $productId = DB::table('products')->where('sku', $sku)->value('id');

                $this->seedBatches($store->id, $productId, $cost, $price, $seq, $now);
            }
        }

        $this->command?->info("Pharmacy demo: seeded {$seq} medicines with batches + expiry.");
    }

    /**
     * One or two dated batches per product. Every 7th product gets a
     * near-expiry batch so the "expiring soon" dashboard tile lights up.
     */
    private function seedBatches(int $storeId, int $productId, float $cost, float $price, int $seq, $now): void
    {
        $batches = [];

        // Primary batch — long shelf life.
        $batches[] = [
            'number'   => 'B'.now()->format('y').sprintf('%04d', $seq),
            'expiry'   => Carbon::today()->addDays(mt_rand(300, 720)),
            'quantity' => mt_rand(40, 240),
        ];

        // Occasional second batch, sometimes expiring soon.
        if ($seq % 7 === 0) {
            $batches[] = [
                'number'   => 'B'.now()->format('y').sprintf('%04dB', $seq),
                'expiry'   => Carbon::today()->addDays(mt_rand(12, 45)),
                'quantity' => mt_rand(10, 60),
            ];
        }

        $total = 0;
        foreach ($batches as $b) {
            DB::table('product_batches')->updateOrInsert(
                ['store_id' => $storeId, 'product_id' => $productId, 'variant_id' => null, 'batch_number' => $b['number']],
                [
                    'manufacture_date' => $b['expiry']->copy()->subYears(2)->toDateString(),
                    'expiry_date'      => $b['expiry']->toDateString(),
                    'cost_price'       => $cost,
                    'selling_price'    => $price,
                    'mrp'              => $price,
                    'quantity'         => $b['quantity'],
                    'initial_quantity' => $b['quantity'],
                    'updated_at'       => $now,
                    'created_at'       => $now,
                ],
            );
            $total += $b['quantity'];
        }

        DB::table('product_stock_levels')->updateOrInsert(
            ['store_id' => $storeId, 'product_id' => $productId, 'variant_id' => null],
            [
                'quantity'              => $total,
                'reserved_quantity'     => 0,
                'weighted_average_cost' => $cost,
                'updated_at'            => $now,
                'created_at'            => $now,
            ],
        );
    }

    /**
     * Compact, believable catalogue: category → schedule/HSN + a list of
     * [brand name, generic, price]. ~60 SKUs across 9 categories.
     *
     * @return array<int, array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            ['category' => 'Analgesics & Antipyretics', 'schedule' => null, 'hsn' => '3004', 'otc' => true, 'drugs' => [
                ['Paracetamol 500mg (10 tab)', 'Paracetamol', 25],
                ['Ibuprofen 400mg (10 tab)', 'Ibuprofen', 35],
                ['Aspirin 75mg (14 tab)', 'Acetylsalicylic acid', 18],
                ['Diclofenac 50mg (10 tab)', 'Diclofenac sodium', 40],
                ['Naproxen 250mg (10 tab)', 'Naproxen', 55],
                ['Combiflam (10 tab)', 'Ibuprofen + Paracetamol', 45],
            ]],
            ['category' => 'Antibiotics', 'schedule' => 'H', 'hsn' => '3004', 'drugs' => [
                ['Amoxicillin 500mg (10 cap)', 'Amoxicillin', 85],
                ['Azithromycin 500mg (3 tab)', 'Azithromycin', 110],
                ['Ciprofloxacin 500mg (10 tab)', 'Ciprofloxacin', 95],
                ['Doxycycline 100mg (10 cap)', 'Doxycycline', 70],
                ['Cephalexin 500mg (10 cap)', 'Cephalexin', 120],
                ['Metronidazole 400mg (10 tab)', 'Metronidazole', 40],
            ]],
            ['category' => 'Antacids & Digestives', 'schedule' => null, 'hsn' => '3004', 'otc' => true, 'drugs' => [
                ['Pantoprazole 40mg (10 tab)', 'Pantoprazole', 90],
                ['Omeprazole 20mg (10 cap)', 'Omeprazole', 65],
                ['Ranitidine 150mg (10 tab)', 'Ranitidine', 30],
                ['Digene Gel (200ml)', 'Antacid suspension', 120],
                ['Eno Sachet', 'Fruit salt', 10],
            ]],
            ['category' => 'Cough & Cold', 'schedule' => null, 'hsn' => '3004', 'otc' => true, 'drugs' => [
                ['Cetirizine 10mg (10 tab)', 'Cetirizine', 28],
                ['Levocetirizine 5mg (10 tab)', 'Levocetirizine', 45],
                ['Benadryl Syrup (100ml)', 'Diphenhydramine', 110],
                ['Vicks Action 500 (10 tab)', 'Paracetamol + Phenylephrine', 50],
                ['Strepsils (8 lozenges)', 'Amylmetacresol', 40],
            ]],
            ['category' => 'Cardiac Care', 'schedule' => 'H', 'hsn' => '3004', 'drugs' => [
                ['Atorvastatin 10mg (10 tab)', 'Atorvastatin', 75],
                ['Amlodipine 5mg (10 tab)', 'Amlodipine', 45],
                ['Telmisartan 40mg (10 tab)', 'Telmisartan', 95],
                ['Clopidogrel 75mg (10 tab)', 'Clopidogrel', 120],
                ['Metoprolol 25mg (10 tab)', 'Metoprolol', 55],
            ]],
            ['category' => 'Diabetes Care', 'schedule' => 'H', 'hsn' => '3004', 'drugs' => [
                ['Metformin 500mg (15 tab)', 'Metformin', 35],
                ['Glimepiride 2mg (10 tab)', 'Glimepiride', 65],
                ['Glibenclamide 5mg (10 tab)', 'Glibenclamide', 40],
                ['Insulin Glargine (vial)', 'Insulin glargine', 850],
            ]],
            ['category' => 'Vitamins & Supplements', 'schedule' => null, 'hsn' => '3004', 'otc' => true, 'drugs' => [
                ['Vitamin D3 60K (4 cap)', 'Cholecalciferol', 90],
                ['Vitamin C 500mg (15 tab)', 'Ascorbic acid', 60],
                ['B-Complex (30 tab)', 'Vitamin B complex', 110],
                ['Calcium + D3 (15 tab)', 'Calcium carbonate', 95],
                ['Iron + Folic Acid (30 tab)', 'Ferrous sulphate', 75],
                ['Multivitamin (30 tab)', 'Multivitamin', 180],
            ]],
            ['category' => 'Dermatology', 'schedule' => null, 'hsn' => '3004', 'otc' => true, 'drugs' => [
                ['Clotrimazole Cream (15g)', 'Clotrimazole', 65],
                ['Betamethasone Cream (15g)', 'Betamethasone', 55],
                ['Calamine Lotion (100ml)', 'Calamine', 80],
                ['Mupirocin Ointment (5g)', 'Mupirocin', 95],
            ]],
            ['category' => 'First Aid & Devices', 'schedule' => null, 'hsn' => '3005', 'otc' => true, 'drugs' => [
                ['Antiseptic Liquid (100ml)', 'Chlorhexidine', 75],
                ['Cotton Roll (100g)', 'Absorbent cotton', 45],
                ['Crepe Bandage (10cm)', 'Crepe bandage', 60],
                ['Adhesive Bandage (pack)', 'Adhesive dressing', 35],
                ['Digital Thermometer', 'Thermometer', 180],
                ['Surgical Face Mask (10)', 'Face mask', 50],
            ]],
        ];
    }
}
