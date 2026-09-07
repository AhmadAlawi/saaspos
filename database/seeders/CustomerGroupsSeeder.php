<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Default customer-group picklist — the four canonical tiers from
 * `docs/features/customers.md` §12.2. Walk-in is the implicit group for
 * unidentified sales; Regular is the default group for new customers.
 *
 * Idempotent: updateOrInsert by name. Safe to re-run.
 */
class CustomerGroupsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $defaults = [
            ['name' => 'Walk-in',   'default_discount_percent' => null, 'is_active' => true],
            ['name' => 'Regular',   'default_discount_percent' => null, 'is_active' => true],
            ['name' => 'Wholesale', 'default_discount_percent' => 15.0, 'is_active' => true],
            ['name' => 'Premium',   'default_discount_percent' => 5.0,  'is_active' => true],
        ];

        foreach ($defaults as $row) {
            DB::table('customer_groups')->updateOrInsert(
                ['name' => $row['name']],
                [
                    'default_discount_percent' => $row['default_discount_percent'],
                    'is_active'                => $row['is_active'],
                    'updated_at'               => $now,
                    'created_at'               => $now,
                ],
            );
        }
    }
}
