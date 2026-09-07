<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StockAdjustmentReasonsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->reasons() as $row) {
            DB::table('stock_adjustment_reasons')->updateOrInsert(
                ['code' => $row['code']],
                [
                    'name'        => $row['name'],
                    'is_active'   => true,
                    'sort_order'  => $row['sort_order'],
                    'updated_at'  => now(),
                    'created_at'  => now(),
                ],
            );
        }
    }

    private function reasons(): array
    {
        return [
            ['code' => 'physical_count',     'name' => 'Physical stock count correction', 'sort_order' => 1],
            ['code' => 'damaged',            'name' => 'Damaged goods',                   'sort_order' => 2],
            ['code' => 'expired',            'name' => 'Expired goods',                   'sort_order' => 3],
            ['code' => 'theft',              'name' => 'Theft / shrinkage',               'sort_order' => 4],
            ['code' => 'sample',             'name' => 'Sample / testing',                'sort_order' => 5],
            ['code' => 'internal_use',       'name' => 'Internal use',                    'sort_order' => 6],
            ['code' => 'donation',           'name' => 'Donation',                        'sort_order' => 7],
            ['code' => 'data_entry_error',   'name' => 'Data entry correction',           'sort_order' => 8],
            ['code' => 'opening_stock',      'name' => 'Opening stock entry',             'sort_order' => 9],
            ['code' => 'other',              'name' => 'Other',                           'sort_order' => 99],
        ];
    }
}
