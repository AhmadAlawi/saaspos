<?php

return [
    'nav' => [
        'section' => 'Accounting',
    ],

    'cancel' => 'Cancel',

    'journal' => [
        'title'       => 'Journal',
        'sub'         => 'Every posted entry — the day book. Auto-generated from operations, plus any manual entries.',
        'list_title'  => 'Entries',
        'new_entry'   => 'New entry',
        'create_sub'  => 'Post a manual journal entry — for adjustments, accruals, depreciation, owner transactions, and corrections.',

        'filter' => [
            'source'      => 'Source',
            'all_sources' => 'All sources',
            'search'      => 'Search number or description…',
        ],

        'columns' => [
            'date'             => 'Date',
            'number'           => 'Entry',
            'source'           => 'Source',
            'description'      => 'Description',
            'amount'           => 'Amount',
            'account'          => 'Account',
            'line_description' => 'Line description',
            'debit'            => 'Debit',
            'credit'           => 'Credit',
        ],

        'fields' => [
            'date'                    => 'Date',
            'description'             => 'Description',
            'description_placeholder' => 'e.g. Monthly depreciation',
            'select_account'          => 'Select an account…',
        ],

        'add_line'    => 'Add line',
        'remove_line' => 'Remove line',
        'off_by'      => 'Off by',
        'post'        => 'Post entry',
        'cancel'      => 'Cancel',

        'posted'   => 'Journal entry posted.',
        'reversed' => 'Entry reversed.',

        'reverse'         => 'Reverse',
        'reverse_confirm' => 'Post a reversing entry that cancels this one? This cannot be undone.',
        'reversed_by'     => 'This entry was reversed by',
        'reversal_of'     => 'This is a reversal of',
        'posted_by'       => 'Posted by :user on :at',

        'period_locked'    => 'That date falls in a locked accounting period.',
        'already_reversed' => 'This entry has already been reversed.',

        'empty' => [
            'title' => 'No entries in this period',
            'sub'   => 'Adjust the date range, or post a manual entry to get started.',
        ],

        'errors' => [
            'line_one_side' => 'Each line must be either a debit or a credit, not both.',
            'empty'         => 'Enter at least one debit and one credit.',
            'unbalanced'    => 'Debits (:debit) must equal credits (:credit).',
        ],
    ],

    'periods' => [
        'title'      => 'Fiscal periods',
        'sub'        => 'Your accounting years and their monthly periods. Lock a period to freeze its entries; close a year to roll its profit into equity.',
        'year'       => 'Fiscal year',
        'period'     => 'Period',
        'range'      => 'Range',
        'status'     => 'Status',
        'open'       => 'Open',
        'locked'     => 'Locked',
        'closed'     => 'Closed',
        'current'    => 'Current',
        'lock'       => 'Lock',
        'unlock'     => 'Unlock',
        'lock_confirm'   => 'Lock :period? New entries dated in it will be rejected until it is unlocked.',
        'unlock_confirm' => 'Unlock :period so corrections can be posted into it?',

        'close_year'         => 'Close year',
        'close_year_confirm' => 'Close :year? This posts the closing entry, moves the net profit to Retained Earnings, and permanently locks the year. Lock all periods first.',
        'year_closed_badge'  => 'Closed :at',
        'all_locked_hint'    => 'All periods locked — ready to close.',
        'lock_all_hint'      => 'Lock every period before closing the year.',

        'errors' => [
            'year_closed' => 'This period belongs to a closed year and cannot be unlocked.',
        ],

        'empty' => [
            'title' => 'No fiscal years yet',
            'sub'   => 'Your first year is created automatically as soon as you post an entry.',
        ],
    ],

    'close' => [
        'zero_expense' => 'Close expense to Retained Earnings',
        'zero_income'  => 'Close income to Retained Earnings',
        'retained'     => 'Net result to Retained Earnings',
        'description'  => 'Year-end close — :year',

        'errors' => [
            'already_closed' => 'This fiscal year is already closed.',
            'not_locked'     => 'Lock every period in the year before closing it.',
        ],
    ],

    // Journal-line descriptions for auto-posted entries.
    'lines' => [
        'payment'          => 'Payment received',
        'receivable'       => 'Accounts receivable',
        'discount'         => 'Sales discount',
        'sales_revenue'    => 'Sales revenue',
        'tax_output'       => 'Output tax',
        'tax_input'        => 'Input tax',
        'cogs'             => 'Cost of goods sold',
        'inventory'        => 'Inventory',
        'rounding'         => 'Rounding adjustment',
        'payable'          => 'Accounts payable',
        'expense'           => 'Expense',
        'customer_advance'  => 'Customer advance',
        'supplier_advance'  => 'Advance to supplier',
        'sales_return'      => 'Sales return',
        'refund'            => 'Refund',
        'store_credit'      => 'Store credit',
        'cash'              => 'Cash',
        'cash_overage'      => 'Cash overage',
        'cash_shortage'     => 'Cash shortage',
        'shrinkage'         => 'Inventory shrinkage',
        'adjustment_income' => 'Inventory adjustment income',
    ],

    // Journal-entry header descriptions.
    'descriptions' => [
        'sale'             => 'Sale :number',
        'purchase'         => 'Purchase :number',
        'expense'          => 'Expense :number',
        'customer_payment' => 'Payment from :name',
        'supplier_payment' => 'Payment to :name',
        'sale_void'        => 'Void of sale :number',
        'sale_return'      => 'Return :number',
        'stock_adjustment' => 'Stock adjustment :number',
        'shift_variance'   => 'Shift :number cash variance',
        'reversal'         => 'Reversal of :number',
    ],

    // Account / group reporting types.
    'types' => [
        'asset'     => 'Asset',
        'liability' => 'Liability',
        'equity'    => 'Equity',
        'income'    => 'Income',
        'expense'   => 'Expense',
    ],

    // Chart of Accounts (Slice 10).
    'chart' => [
        'title'        => 'Chart of Accounts',
        'sub'          => 'Every ledger account, grouped. Add your own, rename or re-file the built-in ones, and jump to any account’s ledger.',
        'new'          => 'New account',
        'system'       => 'System',
        'system_hint'  => 'Built-in account — backs the automatic postings. It can’t be deleted.',
        'mapped'       => 'Mapped',
        'mapped_hint'  => 'A business-event mapping points at this account.',
        'show_gl'      => 'Show ledger',

        'fields' => [
            'code'             => 'Code',
            'code_help'        => 'A short unique number, e.g. 6130.',
            'code_locked_help' => 'This account already has posted entries, so its code is locked.',
            'name'             => 'Name',
            'group'            => 'Group',
            'group_help'       => 'The account inherits its reporting type (asset, income, …) from this group.',
            'select_group'     => 'Select a group…',
            'is_active'        => 'Active — available when posting',
        ],

        'editor' => [
            'empty_title' => 'No account selected',
            'empty_sub'   => 'Pick an account from the tree to edit it, or add a new one.',
            'new_title'   => 'New account',
            'new_sub'     => 'Create a custom ledger account.',
            'edit_title'  => 'Edit account',
            'code_label'  => 'Code',
            'system_note' => 'This is a built-in account. You can rename or re-file it, but it can’t be deleted.',
        ],

        'actions' => [
            'save'    => 'Save account',
            'create'  => 'Create account',
            'discard' => 'Discard',
            'delete'  => 'Delete',
        ],

        'flash' => [
            'created' => 'Account “:name” created.',
            'updated' => 'Account “:name” updated.',
            'deleted' => 'Account “:name” deleted.',
        ],

        'errors' => [
            'code_format'         => 'The code may only contain letters, numbers, dots, dashes and underscores.',
            'code_locked'         => 'This account’s code can’t be changed — it already has posted entries.',
            'delete_system'       => 'Built-in accounts can’t be deleted.',
            'delete_in_use'       => 'This account has posted entries and can’t be deleted.',
            'delete_mapped'       => 'This account backs a business mapping — re-point the mapping first.',
            'deactivate_mapped'   => 'This account backs a business mapping and can’t be deactivated.',
            'deactivate_balance'  => 'A built-in account with a balance can’t be deactivated.',
        ],

        'empty' => [
            'title' => 'No accounts yet',
            'sub'   => 'The chart of accounts is seeded on install.',
        ],

        // Account groups (Slice 12).
        'groups' => [
            'new'       => 'New group',
            'edit_hint' => 'Edit group',

            'editor' => [
                'new_title'  => 'New group',
                'new_sub'    => 'Add a sub-group under an existing one.',
                'edit_title' => 'Edit group',
            ],

            'fields' => [
                'name'          => 'Name',
                'parent'        => 'Parent group',
                'select_parent' => 'Select a parent…',
                'parent_help'   => 'The group takes its reporting type from its parent.',
            ],

            'actions' => [
                'create'  => 'Create group',
                'save'    => 'Save group',
                'delete'  => 'Delete',
                'discard' => 'Discard',
            ],

            'system_note' => 'Built-in group. You can rename it, but it can’t be moved or deleted.',

            'flash' => [
                'created' => 'Group “:name” created.',
                'updated' => 'Group “:name” updated.',
                'deleted' => 'Group “:name” deleted.',
            ],

            'errors' => [
                'system_move'         => 'Built-in groups can’t be moved.',
                'root_move'           => 'Top-level groups can’t be moved.',
                'cross_type'          => 'A group can only move under another group of the same type.',
                'cycle'               => 'A group can’t be moved under itself.',
                'delete_system'       => 'Built-in groups can’t be deleted.',
                'delete_has_children' => 'Move or delete this group’s sub-groups first.',
                'delete_has_accounts' => 'Move or delete this group’s accounts first.',
            ],
        ],
    ],

    // Opening balances / migration (Slice 11).
    'opening' => [
        'title'       => 'Opening balances',
        'sub'         => 'Enter your starting balances when migrating from another system. Each account posts on its natural side; Opening Balance Equity absorbs the difference.',
        'description' => 'Opening balances',
        'line'        => 'Opening balance',
        'equity'      => 'Opening balance equity',

        'sections' => [
            'asset'     => 'Assets',
            'liability' => 'Liabilities',
            'equity'    => 'Equity',
        ],

        'date_label' => 'Opening date',
        'dr'         => 'Dr',
        'cr'         => 'Cr',

        'columns' => [
            'account' => 'Account',
            'side'    => 'Side',
            'amount'  => 'Amount',
        ],

        'debit_total'  => 'Total debits',
        'credit_total' => 'Total credits',
        'plug_label'   => 'Opening Balance Equity',
        'plug_credit'  => 'credit',
        'plug_debit'   => 'debit',
        'balanced'     => 'Balanced',
        'plug_hint'    => 'When your full trial balance is entered this should read “Balanced”. Any remainder is posted to Opening Balance Equity for you to reconcile.',

        'post'          => 'Post opening balances',
        'locked_notice' => 'Real transactions have already been posted, so opening balances can no longer be edited. Post an adjusting manual journal entry instead.',

        'flash' => [
            'posted' => 'Opening balances posted.',
        ],

        'errors' => [
            'locked' => 'Opening balances can’t be changed once real transactions exist.',
        ],
    ],

    // Business-event → account mappings (Slice 10).
    'mappings' => [
        'title'     => 'Business mappings',
        'sub'       => 'Choose which account each automatic posting uses. The defaults suit most stores — change one only if your bookkeeper asks.',
        'read_only' => 'You don’t have permission to change these mappings.',
        'account'   => 'Account',
        'save'      => 'Save mappings',

        'flash' => [
            'saved' => 'Business mappings saved.',
        ],

        'keys' => [
            'sales_revenue'                 => ['label' => 'Sales revenue',              'help' => 'Credited on every sale.'],
            'service_revenue'               => ['label' => 'Service revenue',            'help' => 'Credited on service-product sales.'],
            'sales_returns'                 => ['label' => 'Sales returns',              'help' => 'Debited when a sale is returned.'],
            'sales_discounts'               => ['label' => 'Sales discounts',            'help' => 'Debited for sale-level discounts.'],
            'inventory'                     => ['label' => 'Inventory',                  'help' => 'Debited on purchase, credited as goods are sold.'],
            'tax_output'                    => ['label' => 'Tax collected (output)',     'help' => 'Tax charged to customers, owed to the tax authority.'],
            'tax_input'                     => ['label' => 'Tax paid (input)',           'help' => 'Recoverable tax paid on purchases.'],
            'cogs'                          => ['label' => 'Cost of goods sold',         'help' => 'Debited with the cost of items sold.'],
            'inventory_wastage'             => ['label' => 'Inventory wastage',          'help' => 'Debited when stock is written off as wastage.'],
            'inventory_shrinkage'           => ['label' => 'Inventory shrinkage',        'help' => 'Debited on negative stock adjustments.'],
            'inventory_adjustment_income'   => ['label' => 'Inventory adjustment income','help' => 'Credited on positive stock adjustments.'],
            'accounts_receivable_customers' => ['label' => 'Accounts receivable',        'help' => 'Debited on credit sales owed by customers.'],
            'accounts_payable_suppliers'    => ['label' => 'Accounts payable',           'help' => 'Credited on purchases owed to suppliers.'],
            'customer_advances'             => ['label' => 'Customer advances',          'help' => 'Over-tender / prepayment held as customer credit.'],
            'advances_to_suppliers'         => ['label' => 'Advances to suppliers',      'help' => 'Prepayments made to suppliers.'],
            'store_credit_liability'        => ['label' => 'Store credit',               'help' => 'Store credit owed back to customers.'],
            'cash_overage'                  => ['label' => 'Cash overage',               'help' => 'Credited when a shift counts over.'],
            'cash_shortage'                 => ['label' => 'Cash shortage',              'help' => 'Debited when a shift counts short.'],
            'rounding_adjustment_income'    => ['label' => 'Rounding gain',              'help' => 'Credited for favourable rounding.'],
            'rounding_adjustment_expense'   => ['label' => 'Rounding loss',              'help' => 'Debited for unfavourable rounding.'],
            'payment_gateway_fees'          => ['label' => 'Payment gateway fees',       'help' => 'Debited when gateway fees are recorded.'],
            'cheque_in_hand'                => ['label' => 'Cheques in hand',            'help' => 'Debited on cheque receipt, cleared on realisation.'],
        ],
    ],
];
