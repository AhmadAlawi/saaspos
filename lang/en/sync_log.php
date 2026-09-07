<?php

return [
    'title'        => 'Sync log',
    'sub'          => 'Every cashier-complete round-trip with payload + result. Offline-completed sales show up here once they sync.',
    'crumb_parent' => 'Insights',

    'summary' => [
        'total'    => 'Entries',
        'success'  => 'Success',
        'conflict' => 'Conflict',
        'failed'   => 'Failed',
    ],
    'list_title'   => 'Entries',

    'tabs' => [
        'all'      => 'All',
        'success'  => 'Successful',
        'failed'   => 'Failed',
        'conflict' => 'Conflicts',
    ],

    'columns' => [
        'synced_at'  => 'Synced at',
        'entity'     => 'Entity',
        'local_uuid' => 'Local UUID',
        'result'     => 'Result',
        'user'       => 'Cashier',
        'message'    => 'Message',
    ],

    'status' => [
        'success'  => 'Success',
        'failed'   => 'Failed',
        'conflict' => 'Conflict',
    ],

    'filter' => [
        'from'   => 'From',
        'to'     => 'To',
        'search' => 'Search UUID or message…',
    ],

    'fields' => [
        'entity'      => 'Entity',
        'user'        => 'Cashier',
        'sale'        => 'Sale',
        'grand_total' => 'Grand total',
        'message'     => 'Message',
        'payload'     => 'Original payload',
    ],

    'empty' => [
        'title' => 'No sync events yet',
        'sub'   => 'Cashier completions land here as soon as a sale syncs (online or after an offline outage).',
    ],
];
