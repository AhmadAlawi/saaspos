<?php

return [
    'title'        => 'Batches & expiry',
    'sub'          => 'Every batch the inventory module knows about. Filter by store, product, or expiry status.',
    'crumb_parent' => 'Inventory',
    'list_title'   => 'Batches',

    'summary' => [
        'total'         => 'Total batches',
        'expiring_soon' => 'Expiring soon',
        'expired'       => 'Expired',
    ],

    'tabs' => [
        'all'           => 'All',
        'live'          => 'Live',
        'expiring_soon' => 'Expiring soon',
        'expired'       => 'Expired',
        'archived'      => 'Archived',
    ],

    'columns' => [
        'product' => 'Product',
        'store'   => 'Store',
        'batch'   => 'Batch number',
        'mfg'     => 'Manufactured',
        'expiry'  => 'Expiry',
        'on_hand' => 'On hand',
        'status'  => 'Status',
        'actions' => 'Actions',
    ],

    'filter' => [
        'store_all' => 'All stores',
        'window'    => 'Window',
        'days'      => 'Within :n days',
        'search'    => 'Search batch number, product name, or SKU…',
    ],

    'status' => [
        'live'           => 'Live',
        'expiring_soon'  => 'Expiring soon',
        'expired'        => 'Expired',
        'archived'       => 'Archived',
    ],

    'expiry' => [
        'today'             => '(today)',
        'in_n_days'         => '(in :n days)',
        'expired_n_days'    => '(:n days ago)',
    ],

    'empty' => [
        'title' => 'No batches match',
        'sub'   => 'Receive a purchase line with a batch number to populate this list.',
    ],

    'actions' => [
        'export'      => 'Export',
        'export_csv'  => 'CSV (.csv)',
        'export_xlsx' => 'Excel (.xlsx)',
        'archive'     => 'Archive',
        'restore'     => 'Restore',
    ],

    'confirm_archive' => [
        'title'   => 'Archive batch :batch?',
        'message' => 'It will be hidden from this list and from the batch pickers. Its history stays intact on every sale, purchase and ledger entry that used it, and receiving this batch number again will bring it back.',
    ],

    'flash' => [
        'archived'  => 'Batch :batch archived. Find it again under the Archived tab.',
        'restored'  => 'Batch :batch restored.',
        'not_empty' => 'Batch :batch still holds stock, so it can’t be archived. Adjust the stock out first.',
    ],
];
