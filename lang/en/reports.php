<?php

return [
    'nav' => [
        'section' => 'Reports',
    ],

    'hub' => [
        'title' => 'Reports',
        'sub'   => 'Analyse your sales, inventory, and financials.',
    ],

    'filter' => [
        'from'      => 'From',
        'to'        => 'To',
        'store'     => 'Store',
        'store_all' => 'All stores',
    ],

    'actions' => [
        'export'      => 'Export',
        'export_csv'  => 'CSV',
        'export_xlsx' => 'Excel (XLSX)',
        'export_pdf'  => 'PDF',
    ],

    'pdf' => [
        'generated_at' => 'Generated :time',
        'no_rows'      => 'No data for this period.',
    ],

    'saved' => [
        'title'       => 'Saved reports',
        'sub'         => 'Your saved report views — open one to jump straight back to that report with its filters applied.',
        'list_title'  => 'Saved views',
        'nav'         => 'Saved reports',

        'save_button'      => 'Save view',
        'modal_title'      => 'Save this view',
        'name_label'       => 'Name',
        'name_placeholder' => 'e.g. This month — Main store',
        'share_label'      => 'Share with everyone in this store',
        'cancel'           => 'Cancel',
        'save_confirm'     => 'Save',
        'saved_toast'      => 'Report view saved.',
        'open'             => 'Open',

        'scope_private' => 'Private',
        'scope_shared'  => 'Shared',

        'columns' => [
            'name'     => 'Name',
            'report'   => 'Report',
            'scope'    => 'Scope',
            'saved_by' => 'Saved by',
        ],

        'empty' => [
            'title' => 'No saved reports yet',
            'sub'   => 'Open any report, set your filters, and use "Save view" to keep it here.',
        ],

        'delete' => [
            'button'  => 'Delete',
            'title'   => 'Delete saved report',
            'message' => 'Delete the saved view ":name"? This cannot be undone.',
            'confirm' => 'Delete',
            'done'    => 'Saved report deleted.',
        ],

        'errors' => [
            'name_required' => 'Give this view a name first.',
            'save_failed'   => 'Could not save the report view. Please try again.',
        ],
    ],

    // ── Scheduled reports (Slice 5b) ───────────────────────────────────
    'schedule' => [
        'title'      => 'Scheduled reports',
        'sub'        => 'Have reports emailed to you (and your team) automatically on a daily, weekly, or monthly cadence.',
        'list_title' => 'Schedules',
        'nav'        => 'Scheduled reports',

        'schedule_button' => 'Schedule',
        'modal_title'     => 'Schedule this report',
        'cancel'          => 'Cancel',
        'save_confirm'    => 'Create schedule',
        'saved_toast'     => 'Report scheduled.',

        'fields' => [
            'name'                  => 'Name',
            'name_placeholder'      => 'e.g. Weekly sales — Monday morning',
            'frequency'             => 'Frequency',
            'day_of_week'           => 'Day of week',
            'day_of_month'          => 'Day of month',
            'time'                  => 'Time',
            'format'                => 'Format',
            'recipients'            => 'Recipients',
            'recipients_placeholder' => 'name@example.com, second@example.com',
            'recipients_hint'       => 'Separate multiple email addresses with commas.',
            'subject'               => 'Email subject (optional)',
            'subject_placeholder'   => 'Leave blank for the default subject',
        ],

        'frequencies' => [
            'daily'   => 'Daily',
            'weekly'  => 'Weekly',
            'monthly' => 'Monthly',
        ],

        'formats' => [
            'xlsx' => 'Excel (XLSX)',
            'csv'  => 'CSV',
        ],

        'days' => [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ],

        'cadence' => [
            'daily'   => 'Daily at :time',
            'weekly'  => 'Weekly on :day at :time',
            'monthly' => 'Monthly on day :day at :time',
        ],

        'recipient_count' => '{0} No recipients|{1} :count recipient|[2,*] :count recipients',

        'columns' => [
            'name'       => 'Name',
            'report'     => 'Report',
            'cadence'    => 'Cadence',
            'recipients' => 'Recipients',
            'next_run'   => 'Next run',
            'last_run'   => 'Last run',
        ],

        'status_paused' => 'Paused',
        'run_ok'        => 'Delivered',
        'run_failed'    => 'Failed',
        'never_run'     => 'Not run yet',

        'run_now'        => 'Run now',
        'pause'          => 'Pause',
        'resume'         => 'Resume',
        'run_now_ok'     => 'Report generated and sent.',
        'run_now_failed' => 'The report could not be delivered. Check the recipients and mail settings.',

        'empty' => [
            'title' => 'No scheduled reports yet',
            'sub'   => 'Open any report, set your filters, and use "Schedule" to have it emailed automatically.',
        ],

        'delete' => [
            'button'  => 'Delete',
            'title'   => 'Delete schedule',
            'message' => 'Delete the schedule ":name"? It will stop sending immediately.',
            'confirm' => 'Delete',
            'done'    => 'Schedule deleted.',
        ],

        'errors' => [
            'name_required'       => 'Give this schedule a name first.',
            'recipients_required' => 'Add at least one recipient email address.',
            'recipient_invalid'   => 'One of the recipient addresses is not a valid email.',
            'save_failed'         => 'Could not create the schedule. Please try again.',
            'action_failed'       => 'That action could not be completed. Please try again.',
        ],

        'email' => [
            'subject' => ':report — :app',
            'heading' => 'Your scheduled report is ready',
            'body'    => 'Your scheduled report ":report" is attached.',
            'report'  => 'Report',
            'file'    => 'File',
            'footer'  => 'Sent by :app · schedule ":schedule". Manage or stop this in Reports → Scheduled reports.',
        ],
    ],

    'totals_row' => 'Totals',

    'period' => [
        'label'   => 'Period',
        'presets' => [
            'today'        => 'Today',
            'yesterday'    => 'Yesterday',
            'this_week'    => 'This week',
            'last_week'    => 'Last week',
            'this_month'   => 'This month',
            'last_month'   => 'Last month',
            'last_7_days'  => 'Last 7 days',
            'last_30_days' => 'Last 30 days',
            'last_90_days' => 'Last 90 days',
            'this_year'    => 'This year',
            'last_year'    => 'Last year',
            'custom'       => 'Custom range',
        ],
    ],

    // ── Sales Summary ──────────────────────────────────────────────────
    'sales_summary' => [
        'title' => 'Sales summary',
        'sub'   => 'Daily revenue, transaction counts, and payment method breakdown for any date range.',

        'list_title'        => 'Daily breakdown',
        'payment_breakdown' => 'By payment method',
        'payment_empty'     => 'No payment data for this period.',
        'sales_suffix'      => 'sales',

        'empty' => [
            'title' => 'No sales in this period',
            'sub'   => 'Try adjusting the date range or store filter.',
        ],

        'kpis' => [
            'revenue'      => 'Revenue',
            'transactions' => 'Transactions',
            'avg_ticket'   => 'Avg. ticket',
            'discount'     => 'Discounts',
            'tax'          => 'Tax collected',
            'refunds'      => 'Refunds',
        ],

        'columns' => [
            'date'         => 'Date',
            'transactions' => 'Transactions',
            'subtotal'     => 'Subtotal',
            'discount'     => 'Discount',
            'tax'          => 'Tax',
            'grand_total'  => 'Grand total',
        ],

        'export' => [
            'date'         => 'Date',
            'transactions' => 'Transactions',
            'subtotal'     => 'Subtotal',
            'discount'     => 'Discount',
            'tax'          => 'Tax',
            'grand_total'  => 'Grand Total',
            'total'        => 'TOTAL',
        ],
    ],

    // ── Sales by Product ───────────────────────────────────────────────
    'sales_by_product' => [
        'title' => 'Sales by product',
        'sub'   => 'Quantity sold, revenue, cost, and margin per product for any date range.',

        'list_title' => 'Products sold',

        'empty' => [
            'title' => 'No product sales in this period',
            'sub'   => 'Try adjusting the date range or store filter.',
        ],

        'filter' => [
            'search' => 'Search product name or SKU…',
        ],

        'kpis' => [
            'products' => 'Products sold',
            'qty_sold' => 'Units sold',
            'revenue'  => 'Revenue',
            'profit'   => 'Gross profit',
            'margin'   => 'Avg. margin',
        ],

        'columns' => [
            'product'  => 'Product',
            'sku'      => 'SKU',
            'qty_sold' => 'Qty sold',
            'revenue'  => 'Revenue',
            'cost'     => 'Cost',
            'profit'   => 'Gross profit',
            'margin'   => 'Margin',
        ],

        'export' => [
            'product'    => 'Product',
            'sku'        => 'SKU',
            'qty_sold'   => 'Qty Sold',
            'revenue'    => 'Revenue',
            'cost'       => 'Cost',
            'profit'     => 'Gross Profit',
            'margin_pct' => 'Margin %',
        ],
    ],

    // ── Sales by Cashier ───────────────────────────────────────────────
    'sales_by_cashier' => [
        'title' => 'Sales by cashier',
        'sub'   => 'Transactions, revenue, discounts given, and average basket per cashier.',

        'list_title' => 'Cashiers',

        'empty' => [
            'title' => 'No sales in this period',
            'sub'   => 'Try adjusting the date range or store filter.',
        ],

        'filter' => [
            'search' => 'Search cashier…',
        ],

        'kpis' => [
            'cashiers'   => 'Cashiers',
            'sales'      => 'Transactions',
            'revenue'    => 'Revenue',
            'avg_basket' => 'Avg. basket',
        ],

        'columns' => [
            'cashier'    => 'Cashier',
            'sales'      => 'Sales',
            'items'      => 'Items sold',
            'revenue'    => 'Revenue',
            'discounts'  => 'Discounts',
            'avg_basket' => 'Avg. basket',
        ],
    ],

    // ── Sales by Payment Method ────────────────────────────────────────
    'sales_by_payment_method' => [
        'title' => 'Sales by payment method',
        'sub'   => 'How much you took through each tender — the daily reconciliation view.',

        'list_title' => 'Payment methods',

        'empty' => [
            'title' => 'No payments in this period',
            'sub'   => 'Try adjusting the date range or store filter.',
        ],

        'kpis' => [
            'methods' => 'Methods used',
            'total'   => 'Total received',
        ],

        'columns' => [
            'method' => 'Method',
            'sales'  => 'Sales',
            'total'  => 'Total received',
            'share'  => 'Share',
        ],
    ],

    // ── Sales by Category ──────────────────────────────────────────────
    'sales_by_category' => [
        'title' => 'Sales by category',
        'sub'   => 'Quantity sold, revenue, cost, and margin grouped by product category.',

        'list_title'    => 'Categories',
        'uncategorised' => 'Uncategorised',

        'empty' => [
            'title' => 'No category sales in this period',
            'sub'   => 'Try adjusting the date range or store filter.',
        ],

        'filter' => [
            'search' => 'Search category…',
        ],

        'kpis' => [
            'categories' => 'Categories',
            'revenue'    => 'Revenue',
            'profit'     => 'Gross profit',
            'margin'     => 'Avg. margin',
        ],

        'columns' => [
            'category' => 'Category',
            'qty_sold' => 'Qty sold',
            'revenue'  => 'Revenue',
            'cost'     => 'Cost',
            'profit'   => 'Gross profit',
            'margin'   => 'Margin',
        ],

        'export' => [
            'category'   => 'Category',
            'qty_sold'   => 'Qty Sold',
            'revenue'    => 'Revenue',
            'cost'       => 'Cost',
            'profit'     => 'Gross Profit',
            'margin_pct' => 'Margin %',
        ],
    ],

    // ── Discounts ──────────────────────────────────────────────────────
    'discounts' => [
        'title' => 'Discounts given',
        'sub'   => 'Every discount applied at checkout — who gave it, how much, and who approved it. Click a row to open the sale.',

        'list_title' => 'Discount log',

        'empty' => [
            'title' => 'No discounts in this period',
            'sub'   => 'Try adjusting the date range or store filter.',
        ],

        'filter' => [
            'search' => 'Search sale, cashier, or reason…',
        ],

        'kpis' => [
            'count'    => 'Discounts given',
            'total'    => 'Total discounted',
            'avg'      => 'Avg. discount',
            'approved' => 'Manager-approved',
        ],

        'columns' => [
            'sale'        => 'Sale #',
            'date'        => 'Date',
            'cashier'     => 'Cashier',
            'reason'      => 'Reason',
            'discount'    => 'Discount',
            'approved_by' => 'Approved by',
        ],

        'export' => [
            'sale'        => 'Sale #',
            'date'        => 'Date',
            'cashier'     => 'Cashier',
            'type'        => 'Type',
            'value'       => 'Value',
            'amount'      => 'Amount',
            'category'    => 'Category',
            'reason'      => 'Reason',
            'approved_by' => 'Approved By',
        ],
    ],

    // ── Top Customers ──────────────────────────────────────────────────
    'top_customers' => [
        'title' => 'Top customers',
        'sub'   => 'Your best customers by spend — visits, average basket, and last visit. Click a customer to open their profile.',

        'list_title' => 'Customers',

        'empty' => [
            'title' => 'No customer sales in this period',
            'sub'   => 'Walk-in sales aren\'t counted here. Try adjusting the date range or store filter.',
        ],

        'filter' => [
            'search' => 'Search customer name or code…',
        ],

        'kpis' => [
            'customers'   => 'Customers',
            'revenue'     => 'Revenue',
            'avg_basket'  => 'Avg. basket',
            'outstanding' => 'Outstanding',
        ],

        'columns' => [
            'customer'    => 'Customer',
            'visits'      => 'Visits',
            'total_spent' => 'Total spent',
            'avg_basket'  => 'Avg. basket',
            'last_visit'  => 'Last visit',
            'outstanding' => 'Outstanding',
        ],
    ],

    // ── Top Suppliers ──────────────────────────────────────────────────
    'top_suppliers' => [
        'title' => 'Top suppliers',
        'sub'   => 'Who you buy from most — purchased, paid, and balance owed. Click a supplier to open their profile.',

        'list_title' => 'Suppliers',

        'empty' => [
            'title' => 'No purchases in this period',
            'sub'   => 'Draft and cancelled purchases aren\'t counted. Try adjusting the date range or store filter.',
        ],

        'filter' => [
            'search' => 'Search supplier name or code…',
        ],

        'kpis' => [
            'suppliers' => 'Suppliers',
            'purchased' => 'Total purchased',
            'paid'      => 'Total paid',
            'balance'   => 'Balance owed',
        ],

        'columns' => [
            'supplier'      => 'Supplier',
            'purchases'     => 'Purchases',
            'purchased'     => 'Total purchased',
            'paid'          => 'Paid',
            'balance'       => 'Balance',
            'last_purchase' => 'Last purchase',
        ],
    ],

    // ── Shifts by Cashier ──────────────────────────────────────────────
    'shifts_by_cashier' => [
        'title' => 'Shifts by cashier',
        'sub'   => 'Register sessions per cashier — hours worked, sales rung up, and cumulative cash variance. Only closed shifts count.',

        'list_title' => 'Cashiers',

        'empty' => [
            'title' => 'No closed shifts in this period',
            'sub'   => 'Only closed shifts appear here. Try adjusting the date range or store filter.',
        ],

        'filter' => [
            'search' => 'Search cashier…',
        ],

        'kpis' => [
            'cashiers' => 'Cashiers',
            'shifts'   => 'Shifts',
            'hours'    => 'Total hours',
            'variance' => 'Total variance',
        ],

        'columns' => [
            'cashier'      => 'Cashier',
            'shifts'       => 'Shifts',
            'hours'        => 'Hours',
            'avg_duration' => 'Avg. duration (h)',
            'sales'        => 'Sales',
            'variance'     => 'Cash variance',
        ],
    ],

    // ── Low Stock (hub cross-link to the Inventory report) ─────────────
    'low_stock' => [
        'title' => 'Low stock',
        'sub'   => 'Products at or below their reorder level — reorder before you run out.',
    ],

    'oversold' => [
        'title' => 'Oversold items',
        'sub'   => 'Products sold below available stock (on-hand negative) — restock to clear the backorder.',
    ],

    // ── Aged Receivables ───────────────────────────────────────────────
    'aged_receivables' => [
        'title' => 'Aged receivables',
        'sub'   => 'Who owes you what, broken down by how overdue it is. Click any customer to see their open sales.',

        'list_title' => 'Customers with open balance',

        'empty' => [
            'title' => 'No outstanding balances',
            'sub'   => 'Every credit sale is fully paid. Treat yourself to a coffee.',
        ],

        'filter' => [
            'as_of'     => 'As of',
            'store'     => 'Store',
            'store_all' => 'All stores',
            'search'    => 'Search customer name or code…',
        ],

        'kpis' => [
            'total'     => 'Total outstanding',
            'b0_30'     => '0-30 days',
            'b31_60'    => '31-60 days',
            'b61_90'    => '61-90 days',
            'b90_plus'  => '90+ days',
        ],

        'columns' => [
            'customer'    => 'Customer',
            'b0_30'       => '0-30 days',
            'b31_60'      => '31-60 days',
            'b61_90'      => '61-90 days',
            'b90_plus'    => '90+ days',
            'total'       => 'Total',
            'oldest'      => 'Oldest (days)',
            'sales_count' => 'Open sales',
        ],

        'actions' => [
            'export'      => 'Export',
            'export_csv'  => 'CSV',
            'export_xlsx' => 'Excel (XLSX)',
        ],
    ],

    // ── Trial Balance (Accounting Slice 3) ─────────────────────────────
    'trial_balance' => [
        'title' => 'Trial balance',
        'sub'   => 'Every account\'s balance as of a date. Total debits must equal total credits — click a row to open its ledger.',

        'list_title' => 'Accounts',
        'balanced'   => 'Balanced',
        'unbalanced' => 'Out of balance',

        'kpis' => [
            'total_debit'  => 'Total debit',
            'total_credit' => 'Total credit',
            'status'       => 'Status',
        ],

        'filter' => [
            'as_of'  => 'As of',
            'search' => 'Search code or account…',
        ],

        'columns' => [
            'code'    => 'Code',
            'account' => 'Account',
            'debit'   => 'Debit',
            'credit'  => 'Credit',
        ],

        'empty' => [
            'title' => 'Nothing posted yet',
            'sub'   => 'Once sales, purchases, and other operations post to the ledger, their balances appear here.',
        ],
    ],

    // ── General Ledger (Accounting Slice 3) ────────────────────────────
    'general_ledger' => [
        'title' => 'General ledger',
        'sub'   => 'Every posted line for one account over a date range, with a running balance.',

        'list_title'      => 'Ledger',
        'opening_balance' => 'Opening balance',
        'closing_balance' => 'Closing balance',
        'total_debits'    => 'Total debits',
        'total_credits'   => 'Total credits',

        'filter' => [
            'account'             => 'Account',
            'account_placeholder' => 'Select an account…',
        ],

        'pick' => [
            'title' => 'Pick an account',
            'sub'   => 'Choose an account above to see its ledger and running balance.',
        ],

        'columns' => [
            'date'        => 'Date',
            'entry'       => 'Entry',
            'source'      => 'Source',
            'description' => 'Description',
            'debit'       => 'Debit',
            'credit'      => 'Credit',
            'balance'     => 'Balance',
        ],

        'sources' => [
            'sale'             => 'Sale',
            'sale_return'      => 'Return',
            'sale_void'        => 'Void',
            'purchase'         => 'Purchase',
            'purchase_return'  => 'Purchase return',
            'customer_payment' => 'Customer payment',
            'supplier_payment' => 'Supplier payment',
            'expense'          => 'Expense',
            'stock_adjustment' => 'Adjustment',
            'shift_variance'   => 'Shift variance',
            'opening_balance'  => 'Opening balance',
            'manual'           => 'Manual',
            'closing_entry'    => 'Year-end close',
        ],
    ],

    // ── Profit & Loss (Accounting Slice 4) ─────────────────────────────
    'pnl' => [
        'title' => 'Profit & loss',
        'sub'   => 'Income, cost of goods sold, and expenses for a period — your bottom line.',

        'income'           => 'Income',
        'total_income'     => 'Total income',
        'cogs'             => 'Cost of goods sold',
        'total_cogs'       => 'Total cost of goods sold',
        'gross_profit'     => 'Gross profit',
        'operating'        => 'Operating expenses',
        'total_operating'  => 'Total operating expenses',
        'other'            => 'Other expenses',
        'total_other'      => 'Total other expenses',
        'total_expenses'   => 'Total expenses',
        'net_profit'       => 'Net profit',
        'margin'           => ':pct% margin',

        'columns' => [
            'account' => 'Account',
            'amount'  => 'Amount',
        ],
    ],

    // ── Balance Sheet (Accounting Slice 4) ─────────────────────────────
    'balance_sheet' => [
        'title' => 'Balance sheet',
        'sub'   => 'What you own and what you owe as of a date. Assets must equal liabilities plus equity.',

        'as_of'          => 'As of',
        'out_of_balance' => 'This balance sheet does not balance — assets don\'t equal liabilities plus equity. This signals a data-integrity issue.',

        'assets'                   => 'Assets',
        'total_assets'             => 'Total assets',
        'liabilities'              => 'Liabilities',
        'total_liabilities'        => 'Total liabilities',
        'equity'                   => 'Equity',
        'current_earnings'         => 'Current earnings',
        'total_equity'             => 'Total equity',
        'total_liabilities_equity' => 'Total liabilities + equity',

        'columns' => [
            'account' => 'Account',
            'amount'  => 'Amount',
        ],
    ],

    // ── Cash Flow (Accounting Slice 7) ─────────────────────────────────
    'cash_flow' => [
        'title' => 'Cash flow',
        'sub'   => 'Where cash came from and went over a period — operating, investing, and financing.',

        'operating' => 'Operating activities',
        'investing' => 'Investing activities',
        'financing' => 'Financing activities',

        'net_operating' => 'Net cash from operations',
        'net_investing' => 'Net cash from investing',
        'net_financing' => 'Net cash from financing',

        'net_change' => 'Net change in cash',
        'opening'    => 'Opening cash balance',
        'closing'    => 'Closing cash balance',

        'from_sales'     => 'Cash from sales',
        'from_customers' => 'Cash from customer payments',
        'refunds'        => 'Cash refunds',
        'to_suppliers'   => 'Cash to suppliers',
        'expenses_paid'  => 'Operating expenses paid',
        'cash_variance'  => 'Cash over / short',
        'other'          => 'Other',
        'none'           => 'No activity',

        'columns' => [
            'item'   => 'Item',
            'amount' => 'Amount',
        ],
    ],
];
