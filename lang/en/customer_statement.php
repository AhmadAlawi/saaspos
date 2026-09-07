<?php

return [
    'title'   => 'Statement — :name',
    'heading' => 'Account statement',
    'sub'     => 'Dated event log + running balance for :from → :to. Print or email a copy from the toolbar.',
    'crumb'   => 'Statement',

    'list_title' => 'Events',

    'filter' => [
        'from' => 'From',
        'to'   => 'To',
    ],

    'kpis' => [
        'opening' => 'Opening balance',
        'debits'  => 'Debits (sales)',
        'credits' => 'Credits (payments + refunds)',
        'closing' => 'Closing balance',
    ],

    'columns' => [
        'date'      => 'Date',
        'type'      => 'Type',
        'reference' => 'Reference',
        'debit'     => 'Debit',
        'credit'    => 'Credit',
        'running'   => 'Balance',
    ],

    'types' => [
        'opening' => 'Opening',
        'sale'    => 'Sale',
        'payment' => 'Payment',
        'refund'  => 'Refund',
        'void'    => 'Void',
    ],

    'empty' => [
        'title' => 'No activity in this period',
        'sub'   => 'Try widening the date range, or this customer has had no transactions yet.',
    ],

    'actions' => [
        'print'  => 'Print',
        'email'  => 'Email statement',
        'cancel' => 'Cancel',
        'close'  => 'Close',
    ],

    'confirm_email' => [
        'title'   => 'Email statement to :name?',
        'message' => 'A copy will be sent to :email. The customer sees exactly what you see in print preview.',
    ],

    'errors' => [
        'no_email'    => 'This customer has no email on file. Add one to their profile first.',
        'send_failed' => 'Email failed: :error',
    ],

    'flash' => [
        'sent' => 'Statement emailed to :email.',
    ],

    'email' => [
        'subject' => 'Account statement: :from → :to',
    ],

    'print' => [
        'billed_to'    => 'Billed to',
        'issued'       => 'Issued',
        'period'       => 'Period',
        'closing_label' => 'Closing balance',
        'please_pay'   => 'Amount due: :amount. Please remit at your earliest convenience.',
        'footer_note'  => 'For any questions about this statement, please contact us.',
    ],
];
