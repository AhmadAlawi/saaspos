<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReturnReasonsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->reasons() as $row) {
            DB::table('return_reasons')->updateOrInsert(
                ['code' => $row['code']],
                [
                    'name'                 => $row['name'],
                    'default_restock'      => $row['default_restock'],
                    'requires_permission'  => $row['requires_permission'] ?? null,
                    'is_active'            => true,
                    'sort_order'           => $row['sort_order'],
                ],
            );
        }
    }

    private function reasons(): array
    {
        return [
            ['code' => 'customer_changed_mind',  'name' => 'Customer changed mind',     'default_restock' => true,  'sort_order' => 1],
            ['code' => 'wrong_item',             'name' => 'Wrong item sold',           'default_restock' => true,  'sort_order' => 2],
            ['code' => 'wrong_size',             'name' => 'Wrong size / variant',      'default_restock' => true,  'sort_order' => 3],
            ['code' => 'defective',              'name' => 'Defective / not working',   'default_restock' => false, 'sort_order' => 4],
            ['code' => 'damaged_in_transit',     'name' => 'Damaged in transit',        'default_restock' => false, 'sort_order' => 5],
            ['code' => 'expired',                'name' => 'Expired product',           'default_restock' => false, 'sort_order' => 6],
            ['code' => 'price_dispute',          'name' => 'Pricing dispute',           'default_restock' => true,  'sort_order' => 7],
            ['code' => 'duplicate_charge',       'name' => 'Duplicate billing',         'default_restock' => true,  'sort_order' => 8],
            ['code' => 'exchange',               'name' => 'Exchange',                  'default_restock' => true,  'sort_order' => 9],
            ['code' => 'other',                  'name' => 'Other',                     'default_restock' => true,  'sort_order' => 99],
        ];
    }
}
