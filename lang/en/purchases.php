<?php

return [
    'title'         => 'Purchases',
    'sub'           => 'Purchase orders to your suppliers — capture what you bought, what you owe, what arrived.',
    'crumb_parent'  => 'Supply',
    'new'           => 'New purchase',

    'list' => [
        'title' => 'All purchases (:count)',
    ],

    'summary' => [
        'total'       => 'Total purchases',
        'count'       => 'Purchases',
        'paid'        => 'Paid',
        'outstanding' => 'Outstanding',
    ],

    'columns' => [
        'number'        => 'Number',
        'supplier'      => 'Supplier',
        'store'         => 'Receiving store',
        'date'          => 'Date',
        'due_date'      => 'Due',
        'grand_total'   => 'Total',
        'balance'       => 'Balance',
        'status'        => 'Status',
        'actions'       => 'Actions',
        'product'       => 'Product',
        'qty'           => 'Qty',
        'unit_cost'     => 'Unit cost',
        'discount'      => 'Disc %',
        'tax_group'     => 'Tax',
        'line_total'    => 'Line total',
    ],

    // Friendly field names for validation messages (replace raw
    // `supplier_id` / `items.0.unit_cost` in "The :attribute field …").
    'validation_attributes' => [
        'supplier'      => 'supplier',
        'store'         => 'store',
        'purchase_date' => 'purchase date',
        'due_date'      => 'due date',
        'currency'      => 'currency',
        'product'       => 'product',
        'quantity'      => 'quantity',
        'unit_cost'     => 'unit cost',
        'discount'      => 'discount',
        'tax'           => 'tax',
    ],

    'status' => [
        'draft'           => 'Draft',
        'submitted'       => 'Submitted',
        'received'        => 'Received',
        'partially_paid'  => 'Partially paid',
        'paid'            => 'Paid',
        'cancelled'       => 'Cancelled',
    ],

    'filter' => [
        'status_all'   => 'All statuses',
        'supplier_all' => 'All suppliers',
        'from'         => 'From',
        'to'           => 'To',
        'search'       => 'Search number, invoice no, supplier…',
    ],

    'empty_state' => [
        'title' => 'No purchases yet',
        'sub'   => 'Capture your first purchase to start tracking what you owe and what arrived.',
    ],

    'sections' => [
        'header'      => 'Purchase header',
        'header_sub'  => 'Supplier, dates, currency, and invoice reference.',
        'items'       => 'Items',
        'items_sub'   => 'Lines on this purchase. Add a product to begin.',
        'totals'      => 'Totals',
        'notes'       => 'Notes',
        'attachment'  => 'Attachment',
    ],

    'attachment' => [
        'label'        => 'Invoice attachment',
        'help'         => 'PDF, image, or document — up to 10 MB.',
        'choose'       => 'Choose file',
        'current'      => 'Current file',
        'download'     => 'Download',
        'remove'       => 'Remove',
        'replace_hint' => 'Uploading a new file replaces the current one.',
        'none'         => 'No attachment.',
        'uploaded_by'  => 'Uploaded by :name',
    ],

    'fields' => [
        'supplier'              => 'Supplier',
        'store'                 => 'Receiving store',
        'purchase_date'         => 'Purchase date',
        'due_date'              => 'Due date',
        'due_date_help'         => 'Auto-filled from supplier’s payment terms; editable.',
        'supplier_invoice'      => 'Supplier invoice number',
        'currency'              => 'Currency',
        'add_product'           => 'Add product',
        'add_product_placeholder' => 'Search by name, SKU, or barcode…',
        'notes'                 => 'Notes',
    ],

    'picker' => [
        'searching' => 'Searching…',
        'empty'     => 'No products match. Try a different query.',
    ],

    'scan' => [
        'placeholder'  => 'Scan barcode to add…',
        'camera'       => 'Scan with camera',
        'camera_title' => 'Scan barcode',
        'camera_close' => 'Close scanner',
        'camera_hint'  => 'Point the camera at a barcode. Items are added to the order as they\'re read.',
        'empty'        => 'No barcode was provided.',
        'not_found'    => 'No product found for barcode :barcode.',
    ],

    'line' => [
        'unnamed_product' => '(no product picked)',
        'batch_chip'              => 'Batch',
        'batch_number'            => 'Batch number',
        'batch_number_placeholder' => 'e.g. LOT-2026-04A',
        'manufacture_date'        => 'Manufacture date',
        'expiry_date'             => 'Expiry date',
        'mfg_short'               => 'Mfg',
        'exp_short'               => 'Exp',

        // Read-only price context — see docs/features/suppliers-purchases.md §5.7.
        'sells'          => 'Sells',
        'mrp'            => 'MRP',
        'target_markup'  => 'Target :percent%',
        'markup_title'   => 'Markup at this cost',
        'markup_below'   => 'Below the :percent% target markup',
        'markup_loss'    => 'This cost is at or above the selling price',
        'price_unset'    => '—',
        // Shown instead of the price block when the PO is in a foreign
        // currency: cost and selling price are then in different currencies
        // and no exchange rate is captured on this form, so a markup figure
        // would be meaningless rather than merely imprecise.
        'price_fx'       => 'Prices hidden — PO is in :currency, prices are in :base',
    ],

    'totals' => [
        'subtotal'       => 'Subtotal',
        'discount'       => 'Discount',
        'tax'            => 'Tax',
        'grand'          => 'Grand total',
        'paid'           => 'Paid',
        'balance'        => 'Balance due',
        'preview_note'   => 'Server recomputes from line items on save — this is a live preview.',
    ],

    'items_empty' => 'No items yet. Pick a product above to add the first line.',

    'actions' => [
        'discard'        => 'Discard',
        'save'           => 'Save changes',
        'save_draft'     => 'Save as draft',
        'view'           => 'View purchase',
        'edit'           => 'Edit',
        'delete'         => 'Delete',
        'receive'        => 'Receive',
        'cancel'         => 'Cancel',
        'record_payment' => 'Record payment',
        'duplicate_line' => 'Duplicate line',
        'remove_line'    => 'Remove line',
        'export'         => 'Export',
        'export_csv'     => 'CSV',
        'export_xlsx'    => 'Excel (XLSX)',
        'create_return'  => 'Create return',
    ],

    'flash' => [
        'created'   => 'Purchase :number created (draft).',
        'updated'   => 'Purchase :number updated.',
        'deleted'   => 'Purchase :number deleted.',
        'received'  => 'Purchase :number received. Stock and supplier balance updated.',
        'cancelled' => 'Purchase :number cancelled.',
    ],

    'price_changes' => [
        'title' => 'Selling prices auto-updated (:count)',
        'sub'   => 'The "auto-apply markup on receive" setting bumped these SKUs to keep their margin steady. Sanity-check before the next ring-up.',
    ],

    'errors' => [
        'not_editable'             => 'Purchase is :status and can no longer be edited. Use the receive / cancel flow instead.',
        'not_deletable'            => 'Purchase is :status and can no longer be deleted.',
        'not_receivable'           => 'Purchase is :status and can no longer be received.',
        'not_cancellable'          => 'Purchase is :status — cancel via the return flow instead.',
        'variant_product_mismatch' => 'The selected variant does not belong to the chosen product on this line.',
    ],

    'confirm_delete' => [
        'title'   => 'Delete purchase :number?',
        'message' => 'This soft-deletes the draft. It can be restored from the database if needed.',
        'confirm' => 'Delete',
    ],

    'confirm_receive' => [
        'title'   => 'Receive purchase :number?',
        'message' => 'This records stock and increases the supplier’s outstanding balance. Cannot be undone — to reverse, use the Return flow once the goods are back.',
        'confirm' => 'Receive goods',
    ],

    'receive' => [
        'apply_markup_checkbox' => 'Update selling prices using markup',
    ],

    'oversold' => [
        // :count items on this PO are oversold; receiving nets the backorder off first.
        'warning' => ':count item(s) on this order are currently oversold — receiving will cover the oversold quantity first, so available stock rises by less than you receive:',
    ],

    'confirm_cancel' => [
        'title'   => 'Cancel purchase :number?',
        'message' => 'This marks the draft as cancelled. No stock or balance change. The record stays for audit.',
        'confirm' => 'Cancel purchase',
    ],

    'returns' => [
        'title' => 'Purchase returns',
        'sub'   => 'Goods returned to suppliers — stock and outstanding balance are reversed automatically.',
        'new'   => 'New return',
        'list_title' => 'Returns (:count)',

        'status' => [
            'draft'  => 'Draft',
            'posted' => 'Posted',
        ],

        'filter' => [
            'status_all' => 'All statuses',
            'from'       => 'From',
            'to'         => 'To',
            'search'     => 'Search return #, purchase #, supplier…',
        ],

        'columns' => [
            'number'       => 'Return number',
            'purchase'     => 'Purchase',
            'supplier'     => 'Supplier',
            'date'         => 'Date',
            'total'        => 'Total',
            'status'       => 'Status',
            'received_qty' => 'Received qty',
        ],

        'fields' => [
            'return_date'       => 'Return date',
            'notes'             => 'Notes',
            'notes_placeholder' => 'Reason for return, courier reference, etc.',
            'quantity'          => 'Return qty',
            'restock'           => 'Deduct stock',
        ],

        'sections' => [
            'header'    => 'Return details',
            'items'     => 'Items to return',
            'items_sub' => 'Set the quantity for each line. Leave at 0 to skip.',
            'totals'    => 'Totals',
        ],

        'totals' => [
            'subtotal'   => 'Subtotal',
            'tax'        => 'Tax',
            'tax_note'   => 'Tax',
            'tax_server' => 'Computed on save',
            'grand'      => 'Grand total',
            'refund'     => 'Refund amount',
        ],

        'actions' => [
            'create'        => 'Create return',
            'discard'       => 'Discard',
            'view'          => 'View return',
            'view_purchase' => 'View purchase',
        ],

        'restock_yes' => 'Deducted',
        'restock_no'  => 'Kept',

        'flash' => [
            'created' => 'Return :number created. Stock and supplier balance updated.',
        ],

        'errors' => [
            'not_returnable' => 'Purchase :number cannot be returned (status: :status).',
            'no_qty_entered' => 'Enter a return quantity for at least one line.',
        ],

        'empty_state' => [
            'title' => 'No returns yet',
            'sub'   => 'Returns appear here when goods are sent back to a supplier.',
        ],
    ],
];
