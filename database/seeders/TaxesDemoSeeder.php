<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo tax groups + matching components. Drop in via
 * /seed?class=TaxesDemoSeeder. Idempotent: rows are matched by code.
 *
 * Three groups are seeded so the Categories editor's tax dropdown
 * has something to pick from out of the box:
 *   - STD    Standard 8.25%
 *   - FOOD   Reduced food rate 3%
 *   - EXEMPT 0%
 */
class TaxesDemoSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $groups = [
            ['code' => 'STD',    'name' => 'Standard',          'rate' => '8.2500'],
            ['code' => 'FOOD',   'name' => 'Reduced food rate', 'rate' => '3.0000'],
            ['code' => 'EXEMPT', 'name' => 'Exempt',            'rate' => '0.0000'],
        ];

        foreach ($groups as $row) {
            $componentId = DB::table('tax_components')->updateOrInsert(
                ['code' => $row['code']],
                [
                    'name'       => $row['name'],
                    'rate'       => $row['rate'],
                    'is_active'  => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
            $componentId = DB::table('tax_components')->where('code', $row['code'])->value('id');

            DB::table('tax_groups')->updateOrInsert(
                ['code' => $row['code']],
                [
                    'name'           => $row['name'].' ('.rtrim(rtrim($row['rate'], '0'), '.').'%)',
                    'classification' => $row['code'] === 'EXEMPT' ? 'exempt' : 'taxable',
                    'is_active'      => true,
                    'is_default'     => $row['code'] === 'STD',
                    'updated_at'     => $now,
                    'created_at'     => $now,
                ],
            );
            $groupId = DB::table('tax_groups')->where('code', $row['code'])->value('id');

            DB::table('tax_group_components')->updateOrInsert(
                ['tax_group_id' => $groupId, 'tax_component_id' => $componentId],
                ['sort_order' => 1],
            );
        }

        $this->command?->info('Seeded '.count($groups).' demo tax groups.');
    }
}
