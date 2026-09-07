<?php

return [
    'panel' => [
        'title' => 'Cash drawer',
        'sub'   => 'Record cash added or removed mid-shift, or log a no-sale drawer opening.',
    ],

    'entries' => [
        'title' => 'Drawer movements',
        'sub'   => 'Every pay-in, pay-out, and no-sale drawer open against this shift.',
    ],

    'types' => [
        'pay_in'              => 'Pay-in',
        'pay_out'             => 'Pay-out',
        'pay_supplier'        => 'Pay supplier',
        'drawer_open_no_sale' => 'No-sale open',
    ],

    'fields' => [
        'amount' => 'Amount',
        'reason' => 'Reason',
    ],

    'help' => [
        'pay_in_amount'      => 'Cash being added to the drawer right now.',
        'pay_in_reason'      => 'e.g. "Float top-up", "Manager refill from safe".',
        'pay_out_amount'     => 'Cash being removed from the drawer right now.',
        'pay_out_reason'     => 'e.g. "Petty expense — stationery", "Bank deposit".',
        'drawer_open_reason' => 'e.g. "Customer needed change". Logged for audit; no money movement.',
        'pay_supplier'       => 'Pay a supplier against their unpaid invoices. A cash payment is taken from this till and reduces expected cash; other methods don\'t touch the drawer.',
    ],

    'actions' => [
        'record_pay_in'       => 'Record pay-in',
        'record_pay_out'      => 'Record pay-out',
        'record_drawer_open'  => 'Open drawer',
        'pay_supplier'        => 'Pay supplier',
    ],

    'columns' => [
        'when'   => 'When',
        'type'   => 'Type',
        'reason' => 'Reason',
        'by'     => 'By',
        'amount' => 'Amount',
    ],

    'flash' => [
        'pay_in_recorded'         => 'Pay-in recorded.',
        'pay_out_recorded'        => 'Pay-out recorded.',
        'drawer_opened_no_sale'   => 'No-sale drawer open recorded.',
        'recorded'                => 'Drawer entry recorded.',
    ],

    'errors' => [
        'amount_must_be_positive' => 'Amount must be greater than zero.',
    ],
];
