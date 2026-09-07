<?php

return [
    'title'        => 'Expenses',
    'sub'          => 'Money paid out — rent, utilities, supplies, and other running costs.',
    'crumb_parent' => 'Purchase & Suppliers',
    'new'          => 'New expense',

    'list' => [
        'title' => 'Expenses',
    ],

    'summary' => [
        'count' => 'Expenses',
        'total' => 'Total spent',
    ],

    'columns' => [
        'number'         => 'Number',
        'date'           => 'Date',
        'category'       => 'Category',
        'payment_method' => 'Paid via',
        'supplier'       => 'Supplier',
        'amount'         => 'Amount',
        'actions'        => 'Actions',
    ],

    'filter' => [
        'all_categories' => 'All categories',
        'from'           => 'From',
        'to'             => 'To',
        'search'         => 'Search number, reference, notes…',
    ],

    'empty_state' => [
        'title' => 'No expenses yet',
        'sub'   => 'Record your first expense to track money leaving the drawer.',
    ],

    'sections' => [
        'details'     => 'Expense details',
        'details_sub' => 'What was paid, when, and how.',
    ],

    'fields' => [
        'date'               => 'Date',
        'category'           => 'Category',
        'no_category'        => '— No category —',
        'manage_categories'  => 'Manage categories',
        'amount'             => 'Amount',
        'tax_amount'         => 'Tax',
        'payment_method'     => 'Paid via',
        'no_payment_method'  => '— Not specified —',
        'supplier'           => 'Supplier',
        'no_supplier'        => '— No supplier —',
        'reference'          => 'Reference',
        'reference_help'     => 'Bill / invoice no.',
        'description'        => 'Notes',
    ],

    'actions' => [
        'export'      => 'Export',
        'export_csv'  => 'Export as CSV',
        'export_xlsx' => 'Export as Excel',
        'filter'      => 'Filter',
        'delete'      => 'Delete expense',
        'discard'     => 'Discard',
        'save'        => 'Save changes',
        'create'      => 'Record expense',
    ],

    'confirm_delete' => [
        'title'   => 'Delete expense :number?',
        'message' => 'This removes the expense from the books. This can\'t be undone.',
        'confirm' => 'Delete',
    ],

    'flash' => [
        'created' => 'Expense :number recorded.',
        'updated' => 'Expense :number updated.',
        'deleted' => 'Expense :number deleted.',
    ],
];
