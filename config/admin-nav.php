<?php

/**
 * Admin sidebar navigation schema.
 *
 * Each item has:
 *   - id             : stable string used to mark the active row from a page
 *   - label          : sidebar label (also used in command palette + tooltips)
 *   - icon           : icon name from app/View/Components/Icon.php's CATALOG
 *   - href           : URL (use route() in the controller / view component if needed)
 *   - permission     : single permission required to see the row
 *   - permission_any : array — row shows if the user holds ANY of these
 *   - active_ids     : array — other page ids that should light this row up
 *   - count          : optional number badge
 *   - alert          : optional warning dot
 *   - hidden         : row is NOT drawn in the sidebar, but is still indexed by
 *                      the command palette (used for the individual report
 *                      screens, which now live on the All reports hub page)
 *   - items          : nested array — renders as a collapsible sub-group
 *
 * Sections stay flat: every section header is always visible so the feature
 * surface reads at a glance. Only the read-only / configure-once tails nest
 * into a collapsible sub-group (Catalog setup, Inventory reports, Accounting
 * setup).
 *
 * This file is the canonical source for both the rendered sidebar and the
 * command palette's "Pages" scope.
 */

/** Mirrors ReportsHubController — any report viewer may open the hub. */
$reportViewer = [
    'reports.view_financial',
    'reports.view_sales',
    'reports.view_inventory',
    'reports.view_customers',
    'reports.view_suppliers',
    'reports.view_employees',
    'reports.view_tax',
];

/** Every individual report screen — lights up "All reports" while open. */
$reportPages = [
    'sales-report', 'sales-by-product', 'sales-by-cashier', 'sales-by-payment-method',
    'sales-by-category', 'discounts', 'top-customers', 'top-suppliers', 'shifts-by-cashier',
    'aged-receivables', 'trial-balance', 'general-ledger', 'profit-and-loss', 'balance-sheet', 'cash-flow',
];

return [
    [
        'label' => 'Operations',
        'items' => [
            ['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'href' => '/admin'],
            ['id' => 'cashier',   'label' => 'POS',       'icon' => 'pos',       'href' => '/cashier',         'permission' => 'sales.create'],
            ['id' => 'terminals', 'label' => 'Terminals', 'icon' => 'pos',       'href' => '/admin/terminals', 'permission' => 'terminals.view'],
        ],
    ],
    [
        'label' => 'Sales',
        'items' => [
            ['id' => 'sales',             'label' => 'Sales History',     'icon' => 'receipt', 'href' => '/admin/sales',             'permission' => 'sales.view_own'],
            ['id' => 'kiosk-orders',      'label' => 'Kiosk orders',      'icon' => 'pos',     'href' => '/admin/kiosk-orders',      'permission' => 'sales.view_own'],
            ['id' => 'customer-payments', 'label' => 'Customer payments', 'icon' => 'cash',    'href' => '/admin/customer-payments', 'permission' => 'customers.payments_record'],
            ['id' => 'return-reasons',    'label' => 'Return reasons',    'icon' => 'refund',  'href' => '/admin/return-reasons',    'permission' => 'sales.refund'],
        ],
    ],
    [
        'label' => 'Customers',
        'items' => [
            ['id' => 'customers',       'label' => 'Customers',       'icon' => 'customers', 'href' => '/admin/customers',       'permission' => 'customers.view'],
            ['id' => 'customer-groups', 'label' => 'Customer groups', 'icon' => 'grip',      'href' => '/admin/customer-groups', 'permission' => 'customers.view'],
        ],
    ],
    [
        'label' => 'Shifts',
        'items' => [
            ['id' => 'shifts',                 'label' => 'Shift History',         'icon' => 'clock', 'href' => '/admin/shifts',                  'permission' => 'shifts.open'],
            ['id' => 'shift-variance-reasons', 'label' => 'Cash Mismatch Reasons', 'icon' => 'bar',   'href' => '/admin/shift-variance-reasons',  'permission' => 'shifts.close_others'],
        ],
    ],
    [
        'label' => 'Products',
        'items' => [
            ['id' => 'products',        'label' => 'Products',               'icon' => 'box',     'href' => '/admin/products',         'permission' => 'products.view'],
            ['id' => 'product-labels',  'label' => 'Print labels',           'icon' => 'barcode', 'href' => '/admin/products/labels',  'permission' => 'products.view'],
            ['id' => 'categories',      'label' => 'Categories',             'icon' => 'tree',    'href' => '/admin/categories',       'permission' => 'taxonomies.manage'],
            ['id' => 'brands',          'label' => 'Brands',                 'icon' => 'star',    'href' => '/admin/brands',           'permission' => 'taxonomies.manage'],
            ['id' => 'units',           'label' => 'Units',                  'icon' => 'grip',    'href' => '/admin/units',            'permission' => 'taxonomies.manage'],
            ['id' => 'unit-categories', 'label' => 'Measurement categories', 'icon' => 'tag',     'href' => '/admin/units/categories', 'permission' => 'taxonomies.manage'],
        ],
    ],
    [
        'label' => 'Inventory',
        'items' => [
            ['id' => 'stock-adjustments',  'label' => 'Stock adjustments',    'icon' => 'edit',      'href' => '/admin/inventory/adjustments',        'permission' => 'products.adjust_stock'],
            ['id' => 'stock-takes',        'label' => 'Stock Reconciliation', 'icon' => 'check-all', 'href' => '/admin/inventory/stock-takes',        'permission' => 'products.adjust_stock'],
            ['id' => 'stock-transfers',    'label' => 'Stock transfers',      'icon' => 'external',  'href' => '/admin/inventory/transfers',          'permission' => 'products.transfer_stock'],
            ['id' => 'adjustment-reasons', 'label' => 'Adjustment reasons',   'icon' => 'note',      'href' => '/admin/inventory/adjustment-reasons', 'permission' => 'products.adjust_stock'],
            ['id' => 'price-rules',        'label' => 'Price rules',          'icon' => 'tag',       'href' => '/admin/pricing/rules',                'permission' => 'products.manage_price_rules'],
            [
                'id' => 'inventory-reports', 'label' => 'Inventory reports', 'icon' => 'bar',
                'items' => [
                    ['id' => 'stock-levels',    'label' => 'Available Stock',  'icon' => 'database', 'href' => '/admin/inventory/levels',    'permission' => 'products.view'],
                    ['id' => 'low-stock',       'label' => 'Low stock',        'icon' => 'alert',    'href' => '/admin/inventory/low-stock', 'permission' => 'products.view'],
                    ['id' => 'oversold',        'label' => 'Oversold items',   'icon' => 'alert',    'href' => '/admin/inventory/oversold',  'permission' => 'products.view'],
                    ['id' => 'batches',         'label' => 'Batches & expiry', 'icon' => 'clock',    'href' => '/admin/inventory/batches',   'permission' => 'products.view'],
                    ['id' => 'stock-movements', 'label' => 'Stock Activity',   'icon' => 'refresh',  'href' => '/admin/inventory/movements', 'permission' => 'products.view'],
                ],
            ],
        ],
    ],
    [
        'label' => 'Purchase & Suppliers',
        'items' => [
            ['id' => 'suppliers',          'label' => 'Suppliers',          'icon' => 'truck',  'href' => '/admin/suppliers',          'permission' => 'suppliers.view'],
            ['id' => 'purchases',          'label' => 'Purchases',          'icon' => 'cart',   'href' => '/admin/purchases',          'permission' => 'purchases.view'],
            ['id' => 'purchase-returns',   'label' => 'Purchase returns',   'icon' => 'refund', 'href' => '/admin/purchase-returns',   'permission' => 'purchases.view'],
            ['id' => 'supplier-payments',  'label' => 'Supplier payments',  'icon' => 'card',   'href' => '/admin/supplier-payments',  'permission' => 'suppliers.view'],
            ['id' => 'expenses',           'label' => 'Expenses',           'icon' => 'cash',   'href' => '/admin/expenses',           'permission' => 'expenses.view'],
            ['id' => 'expense-categories', 'label' => 'Expense categories', 'icon' => 'tag',    'href' => '/admin/expense-categories', 'permission' => 'expenses.view'],
        ],
    ],
    [
        'label' => 'Reports',
        'items' => [
            ['id' => 'reports-hub',       'label' => 'All reports',       'icon' => 'pie',   'href' => '/admin/reports',           'permission_any' => $reportViewer, 'active_ids' => $reportPages],
            ['id' => 'saved-reports',     'label' => 'Saved reports',     'icon' => 'star',  'href' => '/admin/reports/saved',     'permission_any' => $reportViewer],
            ['id' => 'scheduled-reports', 'label' => 'Scheduled reports', 'icon' => 'clock',   'href' => '/admin/reports/schedules', 'permission' => 'reports.schedule'],
            ['id' => 'sync-log',          'label' => 'Sync log',          'icon' => 'refresh', 'href' => '/admin/sync-log',          'permission' => 'sync.log.view'],

            // Individual report screens. They live on the All reports hub now,
            // so they are hidden from the sidebar but stay searchable in the
            // command palette. Permissions mirror App\Support\ReportRegistry.
            ['id' => 'sales-report',            'label' => 'Sales summary',       'icon' => 'trending', 'href' => '/admin/reports/sales',                   'permission' => 'reports.view_financial', 'hidden' => true],
            ['id' => 'sales-by-product',        'label' => 'Sales by product',    'icon' => 'bar',      'href' => '/admin/reports/sales-by-product',        'permission' => 'reports.view_financial', 'hidden' => true],
            ['id' => 'sales-by-cashier',        'label' => 'Sales by cashier',    'icon' => 'user',     'href' => '/admin/reports/sales-by-cashier',        'permission' => 'reports.view_sales',     'hidden' => true],
            ['id' => 'sales-by-payment-method', 'label' => 'Sales by payment',    'icon' => 'card',     'href' => '/admin/reports/sales-by-payment-method', 'permission' => 'reports.view_sales',     'hidden' => true],
            ['id' => 'sales-by-category',       'label' => 'Sales by category',   'icon' => 'tree',     'href' => '/admin/reports/sales-by-category',       'permission' => 'reports.view_sales',     'hidden' => true],
            ['id' => 'discounts',               'label' => 'Discounts',           'icon' => 'tag',      'href' => '/admin/reports/discounts',               'permission' => 'reports.view_sales',     'hidden' => true],
            ['id' => 'top-customers',           'label' => 'Top customers',       'icon' => 'customers','href' => '/admin/reports/top-customers',           'permission' => 'reports.view_customers', 'hidden' => true],
            ['id' => 'top-suppliers',           'label' => 'Top suppliers',       'icon' => 'truck',    'href' => '/admin/reports/top-suppliers',           'permission' => 'reports.view_suppliers', 'hidden' => true],
            ['id' => 'shifts-by-cashier',       'label' => 'Shifts by cashier',   'icon' => 'clock',    'href' => '/admin/reports/shifts-by-cashier',       'permission' => 'reports.view_employees', 'hidden' => true],
            ['id' => 'aged-receivables',        'label' => 'Aged receivables',    'icon' => 'cash',     'href' => '/admin/reports/aged-receivables',        'permission' => 'reports.view_financial', 'hidden' => true],
            ['id' => 'trial-balance',           'label' => 'Trial balance',       'icon' => 'calculator','href' => '/admin/reports/trial-balance',          'permission' => 'reports.view_financial', 'hidden' => true],
            ['id' => 'general-ledger',          'label' => 'General ledger',      'icon' => 'list',     'href' => '/admin/reports/general-ledger',          'permission' => 'reports.view_financial', 'hidden' => true],
            ['id' => 'profit-and-loss',         'label' => 'Profit and loss',     'icon' => 'trending', 'href' => '/admin/reports/profit-and-loss',         'permission' => 'reports.view_financial', 'hidden' => true],
            ['id' => 'balance-sheet',           'label' => 'Balance sheet',       'icon' => 'bar',      'href' => '/admin/reports/balance-sheet',           'permission' => 'reports.view_financial', 'hidden' => true],
            ['id' => 'cash-flow',               'label' => 'Cash flow',           'icon' => 'cash',     'href' => '/admin/reports/cash-flow',               'permission' => 'reports.view_financial', 'hidden' => true],
        ],
    ],
    [
        'label' => 'Accounting',
        'items' => [
            ['id' => 'journal',           'label' => 'Journal',           'icon' => 'note',       'href' => '/admin/accounting/journal',           'permission' => 'accounting.view'],
            ['id' => 'chart-of-accounts', 'label' => 'Chart of accounts', 'icon' => 'list',       'href' => '/admin/accounting/chart-of-accounts',  'permission' => 'accounting.view'],
            ['id' => 'business-mappings', 'label' => 'Business mappings', 'icon' => 'external',   'href' => '/admin/accounting/mappings',           'permission' => 'accounting.view'],
            ['id' => 'fiscal-periods',    'label' => 'Fiscal periods',    'icon' => 'lock',       'href' => '/admin/accounting/periods',            'permission' => 'accounting.view'],
            ['id' => 'opening-balances',  'label' => 'Opening balances',  'icon' => 'calculator', 'href' => '/admin/accounting/opening-balances',   'permission' => 'accounting.opening_balances'],
        ],
    ],
    [
        'label' => 'Tax Management',
        'items' => [
            ['id' => 'tax-components',      'label' => 'Tax components',      'icon' => 'tag',  'href' => '/admin/settings/tax/components',      'permission' => 'settings.tax.view'],
            ['id' => 'tax-groups',          'label' => 'Tax groups',          'icon' => 'tree', 'href' => '/admin/settings/tax/groups',          'permission' => 'settings.tax.view'],
            ['id' => 'tax-classifications', 'label' => 'Tax classifications', 'icon' => 'list', 'href' => '/admin/settings/tax/classifications', 'permission' => 'settings.tax.view'],
            ['id' => 'drug-schedules',      'label' => 'Drug Schedules',      'icon' => 'note', 'href' => '/admin/drug-schedules',               'permission' => 'taxonomies.manage'],
        ],
    ],
    [
        'label' => 'User & Access Management',
        'items' => [
            ['id' => 'users', 'label' => 'Users', 'icon' => 'user',   'href' => '/admin/users', 'permission' => 'users.view'],
            ['id' => 'roles', 'label' => 'Roles', 'icon' => 'shield', 'href' => '/admin/roles', 'permission' => 'roles.manage'],
        ],
    ],
    [
        'label' => 'System Configuration',
        'items' => [
            ['id' => 'stores',        'label' => 'Stores',           'icon' => 'store',    'href' => '/admin/stores',                'permission' => 'stores.view'],
            ['id' => 'hardware',      'label' => 'Hardware',         'icon' => 'printer',  'href' => '/admin/settings/hardware',      'permission' => 'hardware.diagnostics'],
            ['id' => 'system-health', 'label' => 'System Health',    'icon' => 'shield',   'href' => '/admin/settings/system-health', 'permission' => 'settings.view'],
            ['id' => 'languages',     'label' => 'Languages',        'icon' => 'globe',    'href' => '/admin/languages',              'permission' => 'settings.view'],
            ['id' => 'settings',      'label' => 'General settings', 'icon' => 'settings', 'href' => '/admin/settings',               'permission' => 'settings.view'],
        ],
    ],
];
