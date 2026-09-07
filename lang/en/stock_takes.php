<?php

return [
    'title'        => 'Stock Reconciliation',
    'sub'          => 'Compare physical stock with recorded inventory and keep your stock accurate.',
    'scan' => [
        // Take counts an existing snapshot row rather than adding one.
        'placeholder'    => 'Scan a barcode to count…',
        'empty'          => 'No barcode was provided.',
        'not_found'      => 'No product found for barcode :barcode.',
        'not_in_take'    => ':name isn\'t part of this stock take.',
        'counted'        => ':name — counted :count',
        'locate_prompt'  => ':name — type the counted quantity',
        'mode_label'     => 'On scan',
        'mode_locate'    => 'Enter count',
        'mode_increment' => 'Add 1 each',
    ],
    'crumb_parent' => 'Inventory',
    'list_title'   => 'Stock takes',

    'summary' => [
        'count'  => 'Stock takes',
        'posted' => 'Posted',
        'draft'  => 'Draft',
    ],
    'new'          => 'Start a stock take',
    'edit_title'   => 'Editing :number',

    'create_sub' => 'Pick the store and start counting — every product with a stock level row is added to the count sheet automatically.',

    'columns' => [
        'number'  => 'Number',
        'name'    => 'Name',
        'date'    => 'Date',
        'store'   => 'Store',
        'items'   => 'Items',
        'status'  => 'Status',
        'by'      => 'By',
        'actions' => 'Actions',
    ],

    'filter' => [
        'store_all'  => 'All stores',
        'status_all' => 'All statuses',
        'date_from'  => 'From',
        'date_to'    => 'To',
        'search'     => 'Search number or name…',
    ],

    'status' => [
        'draft'     => 'Draft',
        'posted'    => 'Posted',
        'cancelled' => 'Cancelled',
    ],

    'fields' => [
        'store'              => 'Store',
        'store_help'         => 'Only one store per take. Counts at multiple stores need separate takes.',
        'take_date'          => 'Count date',
        'name'               => 'Name',
        'name_help'          => 'Optional label — useful when running monthly or department-scoped counts.',
        'name_placeholder'   => 'e.g. Monthly cycle — Aisle 3',
        'notes'              => 'Notes',
        'notes_placeholder'  => 'Anything the next person reviewing this should know…',
    ],

    'kpis' => [
        'lines_total'         => 'Lines',
        'lines_counted'       => 'Counted',
        'lines_with_variance' => 'With variance',
        'net_variance'        => 'Net variance',
    ],

    'items_title' => 'Count sheet',

    'items' => [
        'product'           => 'Product',
        'expected'          => 'Expected',
        'counted'           => 'Counted',
        'variance'          => 'Variance',
        'notes'             => 'Notes',
        'notes_placeholder' => 'e.g. found one damaged',
    ],

    'empty_state' => [
        'title' => 'No stock takes yet',
        'sub'   => 'Start a new count to capture variance against your current stock levels.',
    ],
    'empty_lines' => [
        'title' => 'Nothing to count',
        'sub'   => 'This store has no products with stock levels to count. Receive stock or create a manual adjustment first.',
    ],

    'actions' => [
        'export'         => 'Export',
        'export_csv'     => 'CSV (.csv)',
        'export_xlsx'    => 'Excel (.xlsx)',
        'edit'           => 'Edit',
        'view'           => 'View',
        'delete'         => 'Delete draft',
        'cancel'         => 'Cancel',
        'start_count'    => 'Start count',
        'save_draft'     => 'Save progress',
        'post'           => 'Post count',
        'continue_count' => 'Continue count',
    ],

    'confirm_delete' => [
        'title'   => 'Delete stock take :number?',
        'message' => 'The draft and every counted line will be removed. Nothing has been written to the ledger yet, so no stock levels will change.',
        'confirm' => 'Delete draft',
    ],

    'confirm_post' => [
        'title'   => 'Post stock take :number?',
        'message' => 'Every counted line with a variance will write to the ledger and update stock levels. Posted takes cannot be edited — corrections go through a new adjustment.',
    ],

    'flash' => [
        'created'     => 'Stock take :number started — start filling in the counted column.',
        'updated'     => 'Stock take :number saved.',
        'posted'      => 'Stock take :number posted. Variance lines are now in the ledger.',
        'post_failed' => 'Couldn\'t post: :error',
        'deleted'     => 'Stock take :number deleted.',
    ],

    'errors' => [
        'store_required'    => 'Pick a store before starting the count.',
        'date_required'     => 'Pick a count date.',
        'count_too_large'   => 'A counted quantity is too large — that looks like a scanned barcode, not a count. Clear it and enter the real quantity.',
        'count_not_numeric' => 'Counted quantity must be a number.',
        'count_negative'    => 'Counted quantity can\'t be negative.',
    ],

    'posted_meta' => 'Posted on :when by :who.',
];
