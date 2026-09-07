<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        foreach ($this->catalog() as $row) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $row['key']],
                [
                    'label'         => $row['label'],
                    'group'         => $row['group'],
                    'description'   => $row['description'] ?? null,
                    'is_dangerous'  => $row['is_dangerous'] ?? false,
                    'updated_at'    => $now,
                    'created_at'    => $now,
                ],
            );
        }
    }

    /**
     * Canonical permissions catalog per docs/features/auth-users.md §7.
     * Adding a new permission is a migration + seeder update.
     */
    private function catalog(): array
    {
        return [
            // §7.1 Sales
            ['key' => 'sales.view_own',                  'group' => 'Sales',       'label' => 'View own sales'],
            ['key' => 'sales.view_all',                  'group' => 'Sales',       'label' => 'View all sales in store'],
            ['key' => 'sales.create',                    'group' => 'Sales',       'label' => 'Create sales'],
            ['key' => 'sales.update',                    'group' => 'Sales',       'label' => 'Edit draft sales'],
            ['key' => 'sales.void',                      'group' => 'Sales',       'label' => 'Void a posted sale'],
            ['key' => 'sales.refund',                    'group' => 'Sales',       'label' => 'Process a refund'],
            ['key' => 'sales.change_payment_method',     'group' => 'Sales',       'label' => 'Change a completed sale\'s payment method', 'is_dangerous' => true],
            ['key' => 'sales.discount',                  'group' => 'Sales',       'label' => 'Apply discounts'],
            ['key' => 'sales.discount_above_threshold',  'group' => 'Sales',       'label' => 'Apply discounts above the threshold'],
            ['key' => 'sales.oversell',                  'group' => 'Sales',       'label' => 'Sell below available stock (overrides the negative-stock block)'],
            ['key' => 'sales.held.create',               'group' => 'Sales',       'label' => 'Park a sale'],
            ['key' => 'sales.held.resume_others',        'group' => 'Sales',       'label' => 'Resume sales parked by others'],
            ['key' => 'sales.print_receipt',             'group' => 'Sales',       'label' => 'Print or share receipts'],
            ['key' => 'sales.cross_store_view',          'group' => 'Sales',       'label' => 'View sales across all stores'],

            // §7.2 Returns
            ['key' => 'returns.create',                  'group' => 'Returns',     'label' => 'Process a return'],
            ['key' => 'returns.create_above_threshold',  'group' => 'Returns',     'label' => 'Process returns above thresholds'],
            ['key' => 'returns.void',                    'group' => 'Returns',     'label' => 'Void a return'],

            // §7.3 Products
            ['key' => 'products.view',                   'group' => 'Products',    'label' => 'View products'],
            ['key' => 'products.create',                 'group' => 'Products',    'label' => 'Create products'],
            ['key' => 'products.update',                 'group' => 'Products',    'label' => 'Update products'],
            ['key' => 'products.delete',                 'group' => 'Products',    'label' => 'Delete products'],
            ['key' => 'products.import',                 'group' => 'Products',    'label' => 'Import products'],
            ['key' => 'products.export',                 'group' => 'Products',    'label' => 'Export products'],
            ['key' => 'products.adjust_stock',           'group' => 'Products',    'label' => 'Manual stock adjustments'],
            ['key' => 'products.transfer_stock',         'group' => 'Products',    'label' => 'Transfer stock between stores'],
            ['key' => 'products.update_cost',            'group' => 'Products',    'label' => 'See and update cost prices'],
            ['key' => 'products.manage_price_rules',     'group' => 'Products',    'label' => 'Create and manage scheduled price rules (time-boxed product/category discounts)'],
            ['key' => 'inventory.sell_expired',          'group' => 'Products',    'label' => 'Sell expired batches (overrides the block-expired setting)'],
            ['key' => 'products.delete_batch',           'group' => 'Products',    'label' => 'Archive empty product batches'],
            ['key' => 'sync.log.view',                   'group' => 'Operations',  'label' => 'View the sync log (offline-completed sales, failures, conflicts)'],

            // §7.4 Taxonomies
            ['key' => 'taxonomies.manage',               'group' => 'Products',    'label' => 'Manage categories, brands, and units'],

            // §7.5 Customers
            ['key' => 'customers.view',                  'group' => 'Customers',   'label' => 'View customers'],
            ['key' => 'customers.create',                'group' => 'Customers',   'label' => 'Create customers'],
            ['key' => 'customers.update',                'group' => 'Customers',   'label' => 'Update customers'],
            ['key' => 'customers.delete',                'group' => 'Customers',   'label' => 'Delete customers'],
            ['key' => 'customers.import',                'group' => 'Customers',   'label' => 'Import customers'],
            ['key' => 'customers.export',                'group' => 'Customers',   'label' => 'Export customers'],
            ['key' => 'customers.credit_manage',         'group' => 'Customers',   'label' => 'Add or remove store credit'],
            ['key' => 'customers.payments_record',       'group' => 'Customers',   'label' => 'Record customer payments (settle outstanding sales)'],

            // §7.6 Suppliers
            ['key' => 'suppliers.view',                  'group' => 'Suppliers',   'label' => 'View suppliers'],
            ['key' => 'suppliers.create',                'group' => 'Suppliers',   'label' => 'Create suppliers'],
            ['key' => 'suppliers.update',                'group' => 'Suppliers',   'label' => 'Update suppliers'],
            ['key' => 'suppliers.delete',                'group' => 'Suppliers',   'label' => 'Delete suppliers'],
            ['key' => 'suppliers.payments_record',       'group' => 'Suppliers',   'label' => 'Record supplier payments'],
            ['key' => 'suppliers.payments_void',         'group' => 'Suppliers',   'label' => 'Void supplier payments'],

            // §7.7 Purchases
            ['key' => 'purchases.view',                  'group' => 'Purchases',   'label' => 'View purchases'],
            ['key' => 'purchases.create',                'group' => 'Purchases',   'label' => 'Create purchases'],
            ['key' => 'purchases.update',                'group' => 'Purchases',   'label' => 'Update purchases'],
            ['key' => 'purchases.delete',                'group' => 'Purchases',   'label' => 'Delete purchases'],
            ['key' => 'purchases.receive',               'group' => 'Purchases',   'label' => 'Mark goods received'],

            // §7.8 Shifts & cash
            ['key' => 'shifts.open',                     'group' => 'Shifts',      'label' => 'Open a shift'],
            ['key' => 'shifts.close_own',                'group' => 'Shifts',      'label' => 'Close own shift'],
            ['key' => 'shifts.close_others',             'group' => 'Shifts',      'label' => "Close other cashiers' shifts"],
            ['key' => 'shifts.open_day',                 'group' => 'Shifts',      'label' => 'Open the trading day'],
            ['key' => 'shifts.view_all',                 'group' => 'Shifts',      'label' => "View other cashiers' shifts"],
            ['key' => 'shifts.bypass_enforcement',       'group' => 'Shifts',      'label' => 'Sell without an open shift'],
            ['key' => 'cash_drawer.pay_in',              'group' => 'Shifts',      'label' => 'Record cash pay-in'],
            ['key' => 'cash_drawer.pay_out',             'group' => 'Shifts',      'label' => 'Record cash pay-out'],
            ['key' => 'cash_drawer.open_no_sale',        'group' => 'Shifts',      'label' => 'Open cash drawer without a sale'],

            // §7.9 Reports
            ['key' => 'reports.view_sales',              'group' => 'Reports',     'label' => 'View sales reports'],
            ['key' => 'reports.view_inventory',          'group' => 'Reports',     'label' => 'View inventory reports'],
            ['key' => 'reports.view_customers',          'group' => 'Reports',     'label' => 'View customer reports'],
            ['key' => 'reports.view_suppliers',          'group' => 'Reports',     'label' => 'View supplier reports'],
            ['key' => 'reports.view_employees',          'group' => 'Reports',     'label' => 'View employee / cashier reports'],
            ['key' => 'reports.view_financial',          'group' => 'Reports',     'label' => 'View financial reports'],
            ['key' => 'reports.view_tax',                'group' => 'Reports',     'label' => 'View tax reports'],
            ['key' => 'reports.cross_store',             'group' => 'Reports',     'label' => 'Run cross-store reports'],
            ['key' => 'reports.export',                  'group' => 'Reports',     'label' => 'Export reports'],
            ['key' => 'reports.save_shared',             'group' => 'Reports',     'label' => 'Save reports shared with the store'],
            ['key' => 'reports.schedule',                'group' => 'Reports',     'label' => 'Schedule recurring report deliveries'],

            // §7.11 Expenses — drawer cash-out CRUD (Register & Cash Mgmt).
            ['key' => 'expenses.view',                   'group' => 'Expenses',    'label' => 'View expenses'],
            ['key' => 'expenses.create',                 'group' => 'Expenses',    'label' => 'Record expenses'],
            ['key' => 'expenses.update',                 'group' => 'Expenses',    'label' => 'Edit expenses'],
            ['key' => 'expenses.delete',                 'group' => 'Expenses',    'label' => 'Delete expenses'],
            ['key' => 'expenses.export',                 'group' => 'Expenses',    'label' => 'Export expenses'],

            // §7.10 Accounting — double-entry ledger.
            ['key' => 'accounting.view',                 'group' => 'Accounting',  'label' => 'View chart of accounts, journal, and financial reports'],
            ['key' => 'accounting.manual_entry',         'group' => 'Accounting',  'label' => 'Post manual journal entries'],
            ['key' => 'accounting.reverse_entry',        'group' => 'Accounting',  'label' => 'Reverse posted journal entries'],
            ['key' => 'accounting.lock_period',          'group' => 'Accounting',  'label' => 'Lock a fiscal period'],
            ['key' => 'accounting.unlock_period',        'group' => 'Accounting',  'label' => 'Unlock a locked fiscal period', 'is_dangerous' => true],
            ['key' => 'accounting.year_end_close',       'group' => 'Accounting',  'label' => 'Run the year-end close', 'is_dangerous' => true],
            ['key' => 'accounting.chart.update',         'group' => 'Accounting',  'label' => 'Edit the chart of accounts'],
            ['key' => 'accounting.mappings.update',      'group' => 'Accounting',  'label' => 'Change business-event → account mappings'],
            ['key' => 'accounting.opening_balances',     'group' => 'Accounting',  'label' => 'Enter opening balances', 'is_dangerous' => true],

            // §7.12 Settings
            ['key' => 'settings.view',                   'group' => 'Settings',    'label' => 'View settings'],
            ['key' => 'settings.update',                 'group' => 'Settings',    'label' => 'Update most settings'],
            ['key' => 'settings.update_dangerous',       'group' => 'Settings',    'label' => 'Update license, updater, backup destinations, AI keys, Pusher creds',  'is_dangerous' => true],
            ['key' => 'settings.tax.view',               'group' => 'Settings',    'label' => 'View tax settings (components, groups)'],
            ['key' => 'settings.tax.update',             'group' => 'Settings',    'label' => 'Edit tax components and groups'],
            ['key' => 'settings.manage_receipt_templates', 'group' => 'Settings',  'label' => 'Create and manage receipt/invoice templates'],

            // §7.13 Users & roles
            ['key' => 'users.view',                      'group' => 'Users',       'label' => 'View users'],
            ['key' => 'users.create',                    'group' => 'Users',       'label' => 'Create users'],
            ['key' => 'users.update',                    'group' => 'Users',       'label' => 'Update users'],
            ['key' => 'users.delete',                    'group' => 'Users',       'label' => 'Delete users'],
            ['key' => 'users.create_super_admin',        'group' => 'Users',       'label' => 'Create super-admin users',      'is_dangerous' => true],
            ['key' => 'users.force_logout',              'group' => 'Users',       'label' => 'Force a user to log out'],
            ['key' => 'users.mfa_reset',                 'group' => 'Users',       'label' => 'Reset another user\'s MFA',     'is_dangerous' => true],
            ['key' => 'roles.manage',                    'group' => 'Users',       'label' => 'Manage roles and permissions'],

            // §7.14 Stores
            ['key' => 'stores.view',                     'group' => 'Stores',      'label' => 'View stores'],
            ['key' => 'stores.create',                   'group' => 'Stores',      'label' => 'Create stores'],
            ['key' => 'stores.update',                   'group' => 'Stores',      'label' => 'Update stores'],
            ['key' => 'stores.delete',                   'group' => 'Stores',      'label' => 'Delete stores'],

            // §7.15 Backup & updater
            ['key' => 'backup.run',                      'group' => 'Backup',      'label' => 'Run backups'],
            ['key' => 'backup.restore',                  'group' => 'Backup',      'label' => 'Restore from backup',           'is_dangerous' => true],
            ['key' => 'updater.check',                   'group' => 'Updater',     'label' => 'Check for updates'],
            ['key' => 'updater.run',                     'group' => 'Updater',     'label' => 'Run updates',                   'is_dangerous' => true],

            // §7.16 AI, §7.17 Plugins, §7.18 Audit log, §7.19 WhatsApp —
            // deferred to future releases; no routes/controllers exist yet, so
            // their permissions are not seeded (they'd otherwise show as dead
            // rows on the role page). Re-add the relevant block when the
            // feature ships.

            // §7.20 Terminals & hardware (docs/features/hardware.md §11)
            ['key' => 'terminals.view',                  'group' => 'Hardware',    'label' => 'View checkout terminals'],
            ['key' => 'terminals.configure',             'group' => 'Hardware',    'label' => 'Create, edit, and configure terminals + hardware'],
            ['key' => 'hardware.diagnostics',            'group' => 'Hardware',    'label' => 'View and run the hardware diagnostics page'],
            ['key' => 'hardware.test_print',             'group' => 'Hardware',    'label' => 'Trigger test prints'],
        ];
    }
}
