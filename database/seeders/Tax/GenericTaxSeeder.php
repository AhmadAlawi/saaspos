<?php

namespace Database\Seeders\Tax;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Generic tax seeder — runs for installs that haven't picked a specific
 * country, or as a starting point users can customise.
 *
 * Seeds two components + three groups so a fresh install has working
 * defaults the cashier can ring up against and the product editor's tax
 * dropdown isn't empty:
 *
 *   Components:
 *     STD_10   Standard 10%
 *     ZERO     Zero 0%
 *
 *   Groups:
 *     STANDARD  → STD_10        (default for new products)
 *     EXEMPT    → (none)        classification=exempt
 *     ZERO      → ZERO          classification=zero_rated
 *
 * Idempotent — keyed by `code`. Re-runnable from `/seed?class=Tax\GenericTaxSeeder`.
 *
 * Country-specific seeders (India / UK / EU / GCC / US / AU / CA) land
 * in later slices when the installer wizard picks a country.
 */
class GenericTaxSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $components = [
            ['code' => 'STD_10', 'name' => 'Standard 10%', 'rate' => '10.0000'],
            ['code' => 'ZERO',   'name' => 'Zero 0%',      'rate' => '0.0000'],
        ];
        foreach ($components as $c) {
            DB::table('tax_components')->updateOrInsert(
                ['code' => $c['code']],
                [
                    'name'       => $c['name'],
                    'rate'       => $c['rate'],
                    'is_active'  => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        $stdId  = DB::table('tax_components')->where('code', 'STD_10')->value('id');
        $zeroId = DB::table('tax_components')->where('code', 'ZERO')->value('id');

        $groups = [
            ['code' => 'STANDARD', 'name' => 'Standard (10%)', 'classification' => 'taxable',    'is_default' => true,  'component_id' => $stdId],
            ['code' => 'EXEMPT',   'name' => 'Exempt',         'classification' => 'exempt',     'is_default' => false, 'component_id' => null],
            ['code' => 'ZERO',     'name' => 'Zero-rated',     'classification' => 'zero_rated', 'is_default' => false, 'component_id' => $zeroId],
        ];
        foreach ($groups as $g) {
            DB::table('tax_groups')->updateOrInsert(
                ['code' => $g['code']],
                [
                    'name'              => $g['name'],
                    'classification'    => $g['classification'],
                    'is_reverse_charge' => false,
                    'is_inclusive'      => false,
                    'is_default'        => $g['is_default'],
                    'is_active'         => true,
                    'updated_at'        => $now,
                    'created_at'        => $now,
                ],
            );

            $groupId = DB::table('tax_groups')->where('code', $g['code'])->value('id');

            // Reset the pivot for this group so re-runs don't accumulate
            // stale rows if a group's component set changes.
            DB::table('tax_group_components')->where('tax_group_id', $groupId)->delete();
            if ($g['component_id']) {
                DB::table('tax_group_components')->insert([
                    'tax_group_id'     => $groupId,
                    'tax_component_id' => $g['component_id'],
                    'sort_order'       => 1,
                ]);
            }
        }
    }
}
