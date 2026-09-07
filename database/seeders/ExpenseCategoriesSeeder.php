<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Default expense categories so the module is usable out of the box.
 * Idempotent — keyed by name; re-running never duplicates. A category
 * management UI (add/edit/retire) is a later slice.
 */
class ExpenseCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        // Category → chart-of-accounts code, so posted expenses land on the
        // right P&L line instead of a single Misc bucket. Resolved to an
        // account_id when the chart of accounts is present (it seeds first);
        // null otherwise, and the poster falls back to Misc Expense.
        $categories = [
            'Rent'                  => '6010',
            'Utilities'             => '6020',
            'Salaries & Wages'      => '6030',
            'Supplies'              => '6040',
            'Maintenance & Repairs' => '6090',
            'Marketing'             => '6050',
            'Transport'             => '6100',
            'Bank Charges'          => '6060',
            'Miscellaneous'         => '7990',
        ];

        $accountIds = DB::table('accounts')->pluck('id', 'code')->all();

        foreach ($categories as $name => $code) {
            DB::table('expense_categories')->updateOrInsert(
                ['name' => $name],
                ['is_active' => true, 'account_id' => $accountIds[$code] ?? null],
            );
        }
    }
}
