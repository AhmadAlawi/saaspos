<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UnitsSeeder extends Seeder
{
    public function run(): void
    {
        // Pass 1: base units (no base_unit_id).
        foreach ($this->baseUnits() as $row) {
            DB::table('units')->updateOrInsert(
                ['code' => $row['code']],
                [
                    'name'                => $row['name'],
                    'category'            => $row['category'],
                    'base_unit_id'        => null,
                    'conversion_factor'   => null,
                    'is_active'           => true,
                    'updated_at'          => now(),
                    'created_at'          => now(),
                ],
            );
        }

        $baseIds = DB::table('units')->pluck('id', 'code')->all();

        // Pass 2: derived units pointing at base_unit_id.
        foreach ($this->derivedUnits() as $row) {
            DB::table('units')->updateOrInsert(
                ['code' => $row['code']],
                [
                    'name'                => $row['name'],
                    'category'            => $row['category'],
                    'base_unit_id'        => $baseIds[$row['base_code']] ?? null,
                    'conversion_factor'   => $row['conversion_factor'],
                    'is_active'           => true,
                    'updated_at'          => now(),
                    'created_at'          => now(),
                ],
            );
        }
    }

    private function baseUnits(): array
    {
        return [
            ['code' => 'pc', 'name' => 'Piece',  'category' => 'count'],
            ['code' => 'kg', 'name' => 'Kilogram', 'category' => 'weight'],
            ['code' => 'l',  'name' => 'Litre',  'category' => 'volume'],
            ['code' => 'm',  'name' => 'Metre',  'category' => 'length'],
        ];
    }

    /**
     * conversion_factor = how many base units one unit of this equals.
     * e.g. 1 dozen = 12 pc → factor 12.  1 g = 0.001 kg → factor 0.001.
     */
    private function derivedUnits(): array
    {
        return [
            // Count
            ['code' => 'dozen', 'name' => 'Dozen', 'category' => 'count',  'base_code' => 'pc', 'conversion_factor' => 12],
            ['code' => 'pack',  'name' => 'Pack',  'category' => 'count',  'base_code' => 'pc', 'conversion_factor' => 1],
            ['code' => 'box',   'name' => 'Box',   'category' => 'count',  'base_code' => 'pc', 'conversion_factor' => 1],
            ['code' => 'case',  'name' => 'Case',  'category' => 'count',  'base_code' => 'pc', 'conversion_factor' => 1],

            // Weight
            ['code' => 'g',   'name' => 'Gram',     'category' => 'weight', 'base_code' => 'kg', 'conversion_factor' => 0.001],
            ['code' => 'mg',  'name' => 'Milligram','category' => 'weight', 'base_code' => 'kg', 'conversion_factor' => 0.000001],
            ['code' => 'lb',  'name' => 'Pound',    'category' => 'weight', 'base_code' => 'kg', 'conversion_factor' => 0.45359237],
            ['code' => 'oz',  'name' => 'Ounce',    'category' => 'weight', 'base_code' => 'kg', 'conversion_factor' => 0.028349523],
            ['code' => 'ton', 'name' => 'Metric ton','category' => 'weight','base_code' => 'kg', 'conversion_factor' => 1000],

            // Volume
            ['code' => 'ml',     'name' => 'Millilitre', 'category' => 'volume', 'base_code' => 'l', 'conversion_factor' => 0.001],
            ['code' => 'gal',    'name' => 'Gallon (US)','category' => 'volume', 'base_code' => 'l', 'conversion_factor' => 3.785411784],
            ['code' => 'fl_oz',  'name' => 'Fluid ounce','category' => 'volume', 'base_code' => 'l', 'conversion_factor' => 0.0295735296],

            // Length
            ['code' => 'cm',  'name' => 'Centimetre', 'category' => 'length', 'base_code' => 'm', 'conversion_factor' => 0.01],
            ['code' => 'mm',  'name' => 'Millimetre', 'category' => 'length', 'base_code' => 'm', 'conversion_factor' => 0.001],
            ['code' => 'in',  'name' => 'Inch',       'category' => 'length', 'base_code' => 'm', 'conversion_factor' => 0.0254],
            ['code' => 'ft',  'name' => 'Foot',       'category' => 'length', 'base_code' => 'm', 'conversion_factor' => 0.3048],
            ['code' => 'yd',  'name' => 'Yard',       'category' => 'length', 'base_code' => 'm', 'conversion_factor' => 0.9144],
        ];
    }
}
