<?php

return [
    'title' => 'Customer payments',
    'sub'   => 'Settle outstanding sales for a customer. One payment can clear multiple sales; over-payment lands as credit.',

    'summary' => [
        'total'     => 'Total received',
        'count'     => 'Payments',
        'customers' => 'Customers',
    ],
    'new'   => 'Record payment',
    'new_sub' => 'Pick a customer to see their open sales, then split the payment across them or let Auto-allocate spread it.',
    'crumb_parent' => 'Customers',

    'unallocated_credit' => 'Customer credit',

    'list' => [
        'title' => 'Payments',
    ],

    'empty_state' => [
        'title' => 'No customer payments yet',
        'sub'   => 'Once you record a payment, it shows up here.',
    ],

    'filter' => [
        'customer_all' => 'All customers',
        'from'         => 'From',
        'to'           => 'To',
    ],

    'columns' => [
        'date'      => 'Date',
        'customer'  => 'Customer',
        'sale'      => 'Sale',
        'method'    => 'Method',
        'reference' => 'Reference',
        'amount'    => 'Amount',
    ],

    'sections' => [
        'header'           => 'Payment header',
        'header_sub'       => 'Who paid, when, by what method.',
        'allocations'      => 'Allocations',
        'allocations_sub'  => 'Split the payment across the customer\'s open sales.',
        'total'            => 'Total',
        'notes'            => 'Notes',
    ],

    'fields' => [
        'customer'        => 'Customer',
        'due'             => 'Due',
        'date'            => 'Payment date',
        'method'          => 'Payment method',
        'reference'       => 'Reference',
        'reference_help'  => 'Cheque #, UPI txn, or other proof of payment.',
        'notes'           => 'Notes',
        'recorded_by'     => 'Recorded by',
    ],

    'totals' => [
        'amount'      => 'Amount paid',
        'credit'      => 'Customer credit',
        'help'        => 'Allocations must total the amount above. Use Auto-allocate or per-row Pay-in-full.',
        'credit_help' => 'Extra beyond per-sale allocations lands as customer credit (negative outstanding balance).',
    ],

    'allocations' => [
        'pick_customer' => 'Pick a customer to see their open sales.',
        'loading'       => 'Loading open sales…',
        'none'          => 'This customer has no open sales — every sale is fully paid.',
        'auto_amount'   => 'Amount to allocate',
        'auto'          => 'Auto-allocate (FIFO)',
        'auto_help'     => 'Spreads the typed amount across the oldest unpaid sales first. Edit any row after to fine-tune.',
        'auto_leftover' => 'Cannot allocate — over-tender will land as customer credit:',
        'clear'         => 'Clear',
        'pay_in_full'   => 'Pay in full',
        'columns' => [
            'number'  => 'Sale',
            'date'    => 'Sale date',
            'balance' => 'Balance due',
            'amount'  => 'Amount',
            'actions' => 'Actions',
        ],
    ],

    'show' => [
        'title'                     => 'Payment #:id',
        'details'                   => 'Payment details',
        'sibling_allocations'       => 'Other allocations in this payment',
        'sibling_allocations_sub'   => 'Sales settled by the same payment submission.',
    ],

    'actions' => [
        'record'    => 'Record payment',
        'recording' => 'Recording…',
        'discard'   => 'Discard',
    ],

    'flash' => [
        'created' => 'Payment recorded.',
    ],
];
