<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Universal baseline chart of accounts per docs/features/accounting.md §3.1.
 *
 * Country-specific tax accounts (CGST/SGST/IGST for India, VAT input/output for UK, etc.)
 * are seeded by the installer once the customer picks their country. Those rows are NOT
 * created here.
 *
 * Requires the `company` row to exist (provides base_currency_code). Skips gracefully if not.
 */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $currencyCode = DB::table('company')->value('base_currency_code');
        if (! $currencyCode) {
            $this->command?->warn('ChartOfAccountsSeeder skipped: company row not seeded (run the installer first).');
            return;
        }

        $groupIds = $this->seedGroups();
        $this->seedAccounts($groupIds, $currencyCode);
        $this->seedAccountMappings();
    }

    /** @return array<string, int>  group_path => id */
    private function seedGroups(): array
    {
        $now = now();
        $rows = $this->groupTree();

        // Sort by depth (parent path length) so parents are inserted before children.
        usort($rows, fn ($a, $b) => substr_count($a['path'], '/') <=> substr_count($b['path'], '/'));

        $ids = [];
        foreach ($rows as $i => $r) {
            $parentId = $r['parent_path'] ? ($ids[$r['parent_path']] ?? null) : null;

            DB::table('account_groups')->updateOrInsert(
                ['name' => $r['name'], 'parent_id' => $parentId],
                [
                    'type'        => $r['type'],
                    'sort_order'  => $i,
                    'is_system'   => true,
                    'updated_at'  => $now,
                    'created_at'  => $now,
                ],
            );

            $ids[$r['path']] = DB::table('account_groups')
                ->where('name', $r['name'])
                ->where(function ($q) use ($parentId) {
                    $parentId === null ? $q->whereNull('parent_id') : $q->where('parent_id', $parentId);
                })
                ->value('id');
        }

        return $ids;
    }

    /**
     * @return list<array{path:string, parent_path:?string, name:string, type:string}>
     */
    private function groupTree(): array
    {
        return [
            // Roots
            ['path' => 'assets',        'parent_path' => null,    'name' => 'Assets',        'type' => 'asset'],
            ['path' => 'liabilities',   'parent_path' => null,    'name' => 'Liabilities',   'type' => 'liability'],
            ['path' => 'equity',        'parent_path' => null,    'name' => 'Equity',        'type' => 'equity'],
            ['path' => 'income',        'parent_path' => null,    'name' => 'Income',        'type' => 'income'],
            ['path' => 'cogs',          'parent_path' => null,    'name' => 'Cost of Goods Sold', 'type' => 'expense'],
            ['path' => 'expenses',      'parent_path' => null,    'name' => 'Expenses',      'type' => 'expense'],

            // Asset subgroups
            ['path' => 'assets/current',        'parent_path' => 'assets',  'name' => 'Current Assets',     'type' => 'asset'],
            ['path' => 'assets/fixed',          'parent_path' => 'assets',  'name' => 'Fixed Assets',       'type' => 'asset'],
            ['path' => 'assets/tax_input',      'parent_path' => 'assets/current', 'name' => 'Tax Input (recoverable)', 'type' => 'asset'],
            ['path' => 'assets/other_current',  'parent_path' => 'assets/current', 'name' => 'Other Current Assets',    'type' => 'asset'],

            // Liability subgroups
            ['path' => 'liabilities/current',         'parent_path' => 'liabilities', 'name' => 'Current Liabilities',     'type' => 'liability'],
            ['path' => 'liabilities/long_term',       'parent_path' => 'liabilities', 'name' => 'Long-term Liabilities',   'type' => 'liability'],
            ['path' => 'liabilities/tax_output',      'parent_path' => 'liabilities/current', 'name' => 'Tax Output (payable)', 'type' => 'liability'],
            ['path' => 'liabilities/other_current',   'parent_path' => 'liabilities/current', 'name' => 'Other Current Liabilities', 'type' => 'liability'],

            // Income subgroups
            ['path' => 'income/other',  'parent_path' => 'income',  'name' => 'Other Income', 'type' => 'income'],

            // Expense subgroups
            ['path' => 'expenses/operating',  'parent_path' => 'expenses', 'name' => 'Operating Expenses', 'type' => 'expense'],
            ['path' => 'expenses/other',      'parent_path' => 'expenses', 'name' => 'Other Expenses',     'type' => 'expense'],
        ];
    }

    /** @param array<string, int> $groupIds */
    private function seedAccounts(array $groupIds, string $currencyCode): void
    {
        $now = now();

        // Code ranges per accounting.md §3.2:
        //   1xxx Assets, 2xxx Liabilities, 3xxx Equity, 4xxx Income, 5xxx COGS,
        //   6xxx Operating Expenses, 7xxx Other Expenses
        $accounts = [
            // ── Assets (1xxx) ─────────────────────────────────────────────────
            ['code' => '1010', 'name' => 'Cash on Hand',                'type' => 'asset',     'group' => 'assets/current'],
            ['code' => '1020', 'name' => 'Cash at Bank — Main Account', 'type' => 'asset',     'group' => 'assets/current'],
            ['code' => '1021', 'name' => 'Cash at Bank — Secondary',    'type' => 'asset',     'group' => 'assets/current'],
            ['code' => '1030', 'name' => 'Inventory',                   'type' => 'asset',     'group' => 'assets/current'],
            ['code' => '1031', 'name' => 'Inventory Wastage Adjustments','type' => 'asset',    'group' => 'assets/current'],
            ['code' => '1040', 'name' => 'Accounts Receivable — Customers', 'type' => 'asset', 'group' => 'assets/current'],
            ['code' => '1050', 'name' => 'Cheques in Hand',             'type' => 'asset',     'group' => 'assets/current'],
            ['code' => '1090', 'name' => 'Pre-paid Expenses',           'type' => 'asset',     'group' => 'assets/other_current'],
            ['code' => '1091', 'name' => 'Advances to Suppliers',       'type' => 'asset',     'group' => 'assets/other_current'],
            ['code' => '1500', 'name' => 'Furniture & Fixtures',        'type' => 'asset',     'group' => 'assets/fixed'],
            ['code' => '1510', 'name' => 'Equipment',                   'type' => 'asset',     'group' => 'assets/fixed'],
            ['code' => '1520', 'name' => 'Computers',                   'type' => 'asset',     'group' => 'assets/fixed'],
            ['code' => '1590', 'name' => 'Accumulated Depreciation',    'type' => 'asset',     'group' => 'assets/fixed'],

            // ── Assets: recoverable tax (1060) ────────────────────────────────
            // Generic input-tax account so purchase entries have somewhere to
            // post before the country installer seeds CGST/SGST/IGST leaves.
            ['code' => '1060', 'name' => 'Tax Input (recoverable)',    'type' => 'asset',     'group' => 'assets/tax_input'],

            // ── Liabilities (2xxx) ────────────────────────────────────────────
            ['code' => '2010', 'name' => 'Accounts Payable — Suppliers','type' => 'liability', 'group' => 'liabilities/current'],
            ['code' => '2100', 'name' => 'Tax Output (payable)',        'type' => 'liability', 'group' => 'liabilities/tax_output'],
            ['code' => '2030', 'name' => 'Store Credit Liability',      'type' => 'liability', 'group' => 'liabilities/current'],
            ['code' => '2040', 'name' => 'Customer Advances',           'type' => 'liability', 'group' => 'liabilities/current'],
            ['code' => '2090', 'name' => 'Reverse Charge Payable',      'type' => 'liability', 'group' => 'liabilities/other_current'],
            ['code' => '2091', 'name' => 'Salaries Payable',            'type' => 'liability', 'group' => 'liabilities/other_current'],
            ['code' => '2500', 'name' => 'Loans Payable',               'type' => 'liability', 'group' => 'liabilities/long_term'],

            // ── Equity (3xxx) ─────────────────────────────────────────────────
            ['code' => '3010', 'name' => "Owner's Capital",             'type' => 'equity',    'group' => 'equity'],
            ['code' => '3020', 'name' => "Owner's Drawings",            'type' => 'equity',    'group' => 'equity'],
            ['code' => '3030', 'name' => 'Retained Earnings',           'type' => 'equity',    'group' => 'equity'],
            ['code' => '3090', 'name' => 'Opening Balance Equity',      'type' => 'equity',    'group' => 'equity'],

            // ── Income (4xxx) ─────────────────────────────────────────────────
            ['code' => '4010', 'name' => 'Sales — Revenue',             'type' => 'income',    'group' => 'income'],
            ['code' => '4020', 'name' => 'Service Revenue',             'type' => 'income',    'group' => 'income'],
            ['code' => '4090', 'name' => 'Sales Returns (contra-revenue)',   'type' => 'income','group' => 'income'],
            ['code' => '4091', 'name' => 'Sales Discounts (contra-revenue)', 'type' => 'income','group' => 'income'],
            ['code' => '4900', 'name' => 'Cash Overage',                'type' => 'income',    'group' => 'income/other'],
            ['code' => '4910', 'name' => 'Inventory Adjustment Income', 'type' => 'income',    'group' => 'income/other'],
            ['code' => '4920', 'name' => 'Interest Income',             'type' => 'income',    'group' => 'income/other'],
            ['code' => '4990', 'name' => 'Misc Income',                 'type' => 'income',    'group' => 'income/other'],

            // ── COGS (5xxx) ───────────────────────────────────────────────────
            ['code' => '5010', 'name' => 'Cost of Goods Sold',          'type' => 'expense',   'group' => 'cogs'],
            ['code' => '5020', 'name' => 'Inventory Shrinkage',         'type' => 'expense',   'group' => 'cogs'],
            ['code' => '5030', 'name' => 'Inventory Wastage',           'type' => 'expense',   'group' => 'cogs'],

            // ── Operating Expenses (6xxx) ─────────────────────────────────────
            ['code' => '6010', 'name' => 'Rent',                        'type' => 'expense',   'group' => 'expenses/operating'],
            ['code' => '6020', 'name' => 'Utilities',                   'type' => 'expense',   'group' => 'expenses/operating'],
            ['code' => '6030', 'name' => 'Salaries & Wages',            'type' => 'expense',   'group' => 'expenses/operating'],
            ['code' => '6040', 'name' => 'Office Supplies',             'type' => 'expense',   'group' => 'expenses/operating'],
            ['code' => '6050', 'name' => 'Marketing',                   'type' => 'expense',   'group' => 'expenses/operating'],
            ['code' => '6060', 'name' => 'Bank Charges',                'type' => 'expense',   'group' => 'expenses/operating'],
            ['code' => '6070', 'name' => 'Payment Gateway Fees',        'type' => 'expense',   'group' => 'expenses/operating'],
            ['code' => '6080', 'name' => 'Software & Subscriptions',    'type' => 'expense',   'group' => 'expenses/operating'],
            ['code' => '6090', 'name' => 'Repairs & Maintenance',       'type' => 'expense',   'group' => 'expenses/operating'],
            ['code' => '6100', 'name' => 'Travel',                      'type' => 'expense',   'group' => 'expenses/operating'],
            ['code' => '6110', 'name' => 'Communications',              'type' => 'expense',   'group' => 'expenses/operating'],
            ['code' => '6120', 'name' => 'Professional Fees',           'type' => 'expense',   'group' => 'expenses/operating'],

            // ── Other Expenses (7xxx) ─────────────────────────────────────────
            ['code' => '7010', 'name' => 'Depreciation',                'type' => 'expense',   'group' => 'expenses/other'],
            ['code' => '7020', 'name' => 'Interest Expense',            'type' => 'expense',   'group' => 'expenses/other'],
            ['code' => '7030', 'name' => 'Cash Shortage',               'type' => 'expense',   'group' => 'expenses/other'],
            ['code' => '7040', 'name' => 'Loss on Disposal',            'type' => 'expense',   'group' => 'expenses/other'],
            ['code' => '7990', 'name' => 'Misc Expense',                'type' => 'expense',   'group' => 'expenses/other'],
        ];

        foreach ($accounts as $a) {
            DB::table('accounts')->updateOrInsert(
                ['code' => $a['code']],
                [
                    'account_group_id' => $groupIds[$a['group']] ?? null,
                    'name'             => $a['name'],
                    'type'             => $a['type'],
                    'currency_code'    => $currencyCode,
                    'is_system'        => true,
                    'is_active'        => true,
                    'updated_at'       => $now,
                    'created_at'       => $now,
                ],
            );
        }
    }

    /**
     * Default business-event → account mappings per accounting.md §3.4.
     * Customer can re-map any of these in Settings → Accounting → Mappings.
     */
    private function seedAccountMappings(): void
    {
        $now = now();
        $accountIds = DB::table('accounts')->pluck('id', 'code')->all();

        $mappings = [
            'sales_revenue'                 => '4010',
            'service_revenue'               => '4020',
            'sales_returns'                 => '4090',
            'sales_discounts'               => '4091',
            'inventory'                     => '1030',
            'tax_output'                    => '2100',
            'tax_input'                     => '1060',
            'cogs'                          => '5010',
            'inventory_wastage'             => '5030',
            'inventory_shrinkage'           => '5020',
            'inventory_adjustment_income'   => '4910',
            'accounts_receivable_customers' => '1040',
            'accounts_payable_suppliers'    => '2010',
            'customer_advances'             => '2040',
            'advances_to_suppliers'         => '1091',
            'store_credit_liability'        => '2030',
            'cash_overage'                  => '4900',
            'cash_shortage'                 => '7030',
            'rounding_adjustment_income'    => '4990',
            'rounding_adjustment_expense'   => '7990',
            'payment_gateway_fees'          => '6070',
            'cheque_in_hand'                => '1050',
        ];

        foreach ($mappings as $key => $code) {
            if (! isset($accountIds[$code])) {
                continue;
            }
            DB::table('account_mappings')->updateOrInsert(
                ['key' => $key, 'store_id' => null],
                [
                    'account_id' => $accountIds[$code],
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }
}
