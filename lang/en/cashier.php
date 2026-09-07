<?php

return [
    'title' => 'Cashier',
    'exit'  => 'Exit to admin',

    'topbar' => [
        'store' => 'Store',
    ],

    'categories' => [
        'all' => 'All',
    ],

    'search' => [
        'placeholder' => 'Search products by name / SKU…',
        'hint'        => 'Press Enter to add',
        'searching'   => 'Searching…',
        'clear'       => 'Clear search',
    ],

    'scan' => [
        'placeholder'  => 'Scan barcode…',
        'shortcut'     => 'F8',
        'camera'       => 'Scan with camera',
        'camera_title' => 'Camera scanner',
        'camera_sub'   => 'Point the camera at a barcode to add the item.',
    ],

    'fullscreen' => [
        'enter' => 'Enter fullscreen',
        'exit'  => 'Exit fullscreen',
    ],

    'tile' => [
        'has_variants'    => 'Sizes',
        'options_suffix'  => 'opts',
        'from_prefix'     => 'from',
        'kit'             => 'KIT',
    ],

    'customer' => [
        'walk_in'            => 'Walk-in customer',
        'add'                => 'Add customer',
        'sub_default'        => 'Earn loyalty, attach to order',
        'attached'           => 'Attached to order',
        'change'             => 'Change customer',
        'pick_title'         => 'Pick customer',
        'pick_placeholder'   => 'Search by name, code, phone…',
        'pick_empty'         => 'No customers match — try a different query.',
        'add_new'            => 'Add new customer',
        'add_title'          => 'Quick-add customer',
        'add_name'           => 'Full name',
        'add_phone'          => 'Phone (optional)',
        'add_email'          => 'Email (optional)',
        'add_cancel'         => 'Cancel',
        'add_save'           => 'Save & assign',
        'reset'              => 'Reset to walk-in',
        'outstanding_short'  => 'due',
    ],

    'overflow' => [
        'open'           => 'More',
        'clear_cart'     => 'Clear cart',
        'reprint_last'   => 'Reprint last receipt',
        'no_recent'      => 'No completed sales in this store yet.',
        'open_shift'     => 'Open shift',
        'close_shift'    => 'Close shift',
        'recents'        => 'Recent sales',
        'shortcut_help'  => 'Keyboard shortcuts',
        'soon_suffix'    => 'Coming soon',
        'refund'         => 'Refund / Return',
        'refund_by_scan' => 'Refund by scan (no invoice)',
        'customer_display' => 'Open customer display',
        'drawer_no_sale' => 'Open drawer (no sale)',
    ],

    'recents' => [
        'drawer_title' => 'Recent sales',
        'drawer_sub'   => '20 most recent completed sales in this store.',
        'loading'      => 'Loading recent sales…',
        'empty'        => 'No completed sales yet — ring one up to see it here.',
        'view_receipt' => 'View receipt',
    ],

    'shortcuts' => [
        'title'    => 'Keyboard shortcuts',
        'search'   => 'Focus search',
        'customer' => 'Pick customer',
        'discount' => 'Discount',
        'hold'     => 'Hold order',
        'scan'     => 'Focus scan input',
        'checkout' => 'Checkout',
    ],

    'hold' => [
        'label'             => 'Hold',
        'prompt_title'      => 'Hold this order',
        'prompt_sub'        => 'Optional label — table number, customer name, anything you\'ll recognise.',
        'prompt_placeholder' => 'e.g. Table 5 · Mrs. Patel · Pickup order',
        'prompt_cancel'     => 'Cancel',
        'prompt_save'       => 'Hold order',
        'prompt_saving'     => 'Holding…',
    ],

    'drawer_no_sale' => [
        'prompt_title'       => 'Open drawer (no sale)',
        'prompt_sub'         => 'Recorded on this shift — say why the drawer needs opening.',
        'prompt_placeholder' => 'e.g. Change for a nearby shop, making change for a customer',
        'prompt_cancel'      => 'Cancel',
        'prompt_save'        => 'Open drawer',
        'prompt_saving'      => 'Opening…',
    ],

    'held' => [
        'drawer_title' => 'Held orders',
        'drawer_sub'   => 'Resume to load back into the cart, or delete to discard.',
        'empty'        => 'No held orders right now.',
        'resume'       => 'Resume',
        'delete'       => 'Delete',
        'items'        => 'items',
        'no_label'     => 'No label',
        'overflow_open' => 'Held orders',
    ],

    'checkout' => [
        'label' => 'Checkout',
    ],

    'layout' => [
        'aria'          => 'Cashier layout',
        'beam'          => 'Beam — cart on the right',
        'beam_short'    => 'Beam',
        'lane'          => 'Lane — cart on the left',
        'lane_short'    => 'Lane',
        'counter'       => 'Counter — sidebar + dense list',
        'counter_short' => 'Counter',
        'focus'         => 'Focus — big scanned-item list, action rail, collapsible catalog',
        'focus_short'   => 'Focus',
    ],

    'action_rail' => [
        'discount' => 'Discount',
        'customer' => 'Customer',
        'check_price' => 'Check price',
        'held' => 'Held sales',
        'catalog_expand' => 'Show catalog',
        'catalog_collapse' => 'Hide catalog',
        'cart_expand' => 'Show cart',
        'cart_collapse' => 'Hide cart',
    ],

    'price_check' => [
        'not_found' => 'No product found for that barcode.',
    ],

    'listrow' => [
        'plu_header'     => 'PLU / SKU',
        'item_header'    => 'Item',
        'price_header'   => 'Price',
        'category_label' => 'Category',
        'add_aria'       => 'Add :name to cart',
        'items_suffix'   => 'items',
        'sort_name'      => 'Sort: Name',
        'show_all'       => 'Show: All',
    ],

    'stock' => [
        'low_label'     => 'Low:',
        'out'           => 'Out of stock',
        'not_available' => 'Not available',
        'not_listed'    => 'Stock not listed yet',
    ],

    'grid' => [
        'empty_title' => 'No matches',
        'empty_sub'   => 'Try a different search or category.',
        'load_more'   => 'Load more',
    ],

    'results' => [
        'on_hand' => 'On hand',
    ],

    'empty' => [
        'title' => 'No items yet',
        'sub'   => 'Scan a barcode or type to find a product.',
    ],

    'cart' => [
        'title'         => 'Cart',
        'items'         => 'items',
        'empty'         => 'Scan, search, or tap an item to begin ringing it up.',
        'empty_title'   => 'No items yet',
        'order_eyebrow' => 'Order',
        'draft'         => 'Draft',
        'new'           => 'New',
        'clear'         => 'Clear',
        'discount'      => 'Discount',
    ],

    'totals' => [
        'items'                => 'items',
        'units'                => 'units',
        'subtotal'             => 'Subtotal',
        'discount'             => 'Discount',
        'add_discount'         => 'Add discount',
        'tax'                  => 'Tax',
        'tax_at_checkout'      => 'calculated at checkout',
        'tax_incl'             => '(incl.)',
        'tax_already_included' => 'Tax (already included)',
        'tax_included_note'    => ':amount is already included in item prices.',
        'grand'                => 'Total',
        'grand_due'            => 'Total due',
    ],

    'line' => [
        'each'             => 'each',
        'remove'           => 'Remove',
        'discount'         => 'Discount this line',
        'add_note'         => 'Add a note',
        'edit_note'        => 'Edit note',
        'note_title'       => 'Line note',
        'note_placeholder' => "e.g. customer wants no ice · gift wrap · ring up separately",
        'note_save'        => 'Save note',
        'over_stock'       => 'Only :available in stock — reduce qty to continue.',
        'tax'              => 'Tax',
    ],

    'discount' => [
        'title'       => 'Order-level discount',
        'percent'     => 'Percent',
        'amount'      => 'Amount',
        'percent_off' => 'Percent off',
        'amount_off'  => 'Amount off',
        'new_total'   => 'New total',
        'remove'      => 'Remove discount',
        'apply'       => 'Apply',
        'reason_category' => 'Reason',
        'reason_none' => '— No reason —',
        'reason'      => 'Note (optional)',
        'reason_ph'   => 'e.g. price match with competitor',
        'reason_categories' => [
            'loyalty'      => 'Loyalty / regular',
            'price_match'  => 'Price match',
            'damaged'      => 'Damaged / clearance',
            'staff'        => 'Staff purchase',
            'manager_comp' => 'Manager comp',
            'promo'        => 'Promotion',
            'other'        => 'Other',
        ],
    ],

    'line_discount' => [
        'title' => 'Line discount',
    ],

    'discount_approval' => [
        'title'          => 'Manager approval needed',
        'sub'            => 'This __PCT__% discount is above the store limit. A manager must enter their PIN to approve it.',
        // Under the threshold, any staff PIN is accepted — this just
        // records who applied the discount, not whether they're allowed to.
        'title_identify' => 'Who\'s applying this discount?',
        'sub_identify'   => 'Enter your PIN to confirm who\'s applying this __PCT__% discount.',
        'approve'        => 'Approve discount',
        'approving'      => 'Approving…',
        'invalid'        => 'Incorrect PIN.',
    ],

    'pin_pad' => [
        'clear'      => 'Clear',
        'backspace'  => 'Delete last digit',
        'too_many'   => 'Too many attempts — wait a moment and try again.',
    ],

    'variant' => [
        'pick_sub' => 'Pick a variant to add to the cart.',
        'in_stock' => 'in stock',
    ],

    'batch' => [
        'pick_sub'        => 'Pick a batch — earliest expiry first.',
        'batch_prefix'    => 'Batch',
        'exp_prefix'      => 'Exp',
        'mrp_prefix'      => 'MRP',
        'days_left'       => 'expires in :n days',
        'expires_today'   => 'expires today',
        'expired'         => 'expired',
        'empty_title'     => 'No live batches',
        'empty_sub'       => 'Receive stock with a batch number first.',
        'blocked_tooltip' => 'Expired — cannot sell (admin override required).',
    ],

    'weight' => [
        'sub'        => 'Enter the weighed amount.',
        'label'      => 'Weight',
        'line_total' => 'Line total',
        'add'        => 'Add to cart',
    ],

    'actions' => [
        'clear' => 'Clear',
        'pay'   => 'Pay',
    ],

    'pay' => [
        'title'                 => 'Take payment',
        'method_label'          => 'Payment method',
        'method_sub' => [
            'cash'     => 'With change',
            'ref'      => 'With reference',
            'terminal' => 'Chip · Contactless',
        ],
        'cash_given'            => 'Cash given',
        'tendered'              => 'Amount tendered',
        'reference'             => 'Reference',
        'reference_placeholder' => 'Card last 4 / UPI txn / cheque #…',
        'ref_hint'              => 'Enter the transaction reference so it lands on the receipt.',
        'upi_scan_title'        => 'Scan to pay with UPI',
        'upi_scan_hint'         => 'Customer scans this with any UPI app (GPay, PhonePe, Paytm, etc.). After they pay, type the UTR into the reference field below.',
        'change_due'            => 'Change due',
        'credit_balance'        => 'Goes on account',
        'put_on_account'        => 'Put on account',
        'cancel'                => 'Cancel',
        'complete'              => 'Complete sale',
        'completing'            => 'Completing…',
        'accept_cash'           => 'Accept cash',
        'take_payment'          => 'Take payment',
        'send_terminal'         => 'Send to terminal',
        'terminal_title'        => 'Insert, tap, or swipe',
        'terminal_sub'          => 'Waiting on the terminal · Amount sent',

        // Split tender
        'split_so_far'          => 'Paid so far',
        'add_payment'           => 'Add payment',
        'remove_payment_aria'   => 'Remove this payment',
        'amount_charged'        => 'Amount on this method',

        // Stripe gateway (Slice — Payments)
        'stripe_title'          => 'Stripe — scan to pay',
        'stripe_starting'       => 'Creating session…',
        'stripe_pending'        => 'Waiting for customer to pay',
        'stripe_paid'           => 'Paid — completing sale',
        'stripe_failed'         => 'Payment failed or cancelled',
        'stripe_idle'           => 'Ready',
        'stripe_open_link'      => 'Open payment link',
        'stripe_cancel'         => 'Cancel session',

        // QR-chooser flow — provider-agnostic
        'qr_tile_title'         => 'Charge via QR',
        'qr_tile_sub'           => 'Customer picks the gateway',
        'qr_tile_offline'       => 'Needs internet — unavailable offline',

        // Wallet buttons — both go straight to Stripe Checkout; the
        // wallet itself (Apple Pay vs Google Pay) is whatever the
        // customer's own device offers once they open the link.
        'apple_pay_title'       => 'Apple Pay',
        'google_pay_title'      => 'Google Pay',
        'wallet_sub'            => 'Scan on customer display',
        'qr_idle'               => 'Ready',
        'qr_starting'           => 'Creating session…',
        'qr_waiting'            => 'Waiting for customer to scan…',
        'qr_selected'           => 'Customer picked a method — waiting for payment',
        'qr_paid'               => 'Paid — completing sale',
        'qr_failed'             => 'Payment failed',
        'qr_expired'            => 'Session expired',
        'qr_cancelled'          => 'Session cancelled',
        'qr_instructions'       => 'Ask the customer to scan with their phone. They will choose how to pay — UPI, card, wallet, or any other configured method.',
        'qr_share_link'         => 'Or share this link',
        'qr_copy'               => 'Copy link',
        'qr_open'               => 'Open link',
        'qr_cancel'             => 'Cancel payment',
        'qr_expires_in'         => 'Expires in',
        'qr_dead_hint'          => 'This QR link is no longer active. Generate a new one to take the payment.',
        'qr_regenerate'         => 'Generate new QR',
    ],

    'success' => [
        'title'  => 'Sale complete',
        'change' => 'Change',
        'view'   => 'View receipt',
        'print'  => 'Print receipt',
        'new'    => 'New sale',
    ],

    'conn' => [
        'title'         => 'Connectivity',
        'status'        => 'Status',
        'last_synced'   => 'Last synced',
        'queue'         => 'Sync queue',
        'queue_empty'   => 'empty',
        'queue_pending' => 'pending',
        'refresh'       => 'Refresh data now',
        'refreshing'    => 'Refreshing…',
        'install'       => 'Install POS app',
        'update'        => 'Update now',
    ],

    'refund' => [
        'lookup_title'       => 'Refund a sale',
        'lookup_sub'         => 'Scan the receipt or type the sale number — recent sales show by default.',
        'lookup_placeholder' => 'Sale number, customer name, or scan…',
        'lookup_empty'       => 'No sales match — recent completed sales appear here.',
        'partial_tag'        => 'Partial refund',

        'title'               => 'Process refund',
        'sub'                 => 'Pick the lines and quantities being returned. Defaults to a full refund.',
        'col_item'            => 'Item',
        'col_qty'             => 'Refund qty',
        'col_unit_price'      => 'Unit price',
        'col_line_total'      => 'Line total',
        'remaining_n'         => 'Up to :n returnable',
        'reason'              => 'Reason',
        'reason_placeholder'  => 'Pick a return reason…',
        'method'              => 'Refund to',
        'method_placeholder'  => 'Cash (default)',
        'restock'             => 'Restock these items',
        'notes'               => 'Notes',
        'notes_placeholder'   => 'Anything the next cashier should know about this refund…',
        'totals_subtotal'     => 'Subtotal',
        'totals_tax'          => 'Tax',
        'totals_grand'        => 'Refund total',
        'submit'              => 'Process refund',
        'submitting'          => 'Processing…',

        'success_title'       => 'Refund processed',
        'success_sale_prefix' => 'For sale',
        'success_done'        => 'Done',
    ],

    'blind_refund' => [
        'title'             => 'Refund by scan',
        'sub'               => 'No invoice needed — scan each item to refund it. A manager approves the total before it submits.',
        'scan_placeholder'  => 'Scan or type a barcode…',
        'empty'             => 'Scan an item to start.',
        'col_item'          => 'Item',
        'col_qty'           => 'Qty',
        'col_unit_price'    => 'Unit price',
        'col_line_total'    => 'Line total',
        'unknown_barcode'   => 'No product for ":code".',
        'reason'            => 'Reason',
        'reason_placeholder'=> 'Pick a return reason…',
        'method'            => 'Refund to',
        'method_placeholder'=> 'Cash (default)',
        'method_gateway_hint' => 'Card/gateway methods aren\'t available for a no-invoice refund — pick cash or a manual method.',
        'restock'           => 'Restock these items',
        'notes'             => 'Notes',
        'notes_placeholder' => 'Anything the next cashier should know about this refund…',
        'totals_grand'      => 'Refund total',
        'submit'            => 'Approve & refund',
        'submitting'        => 'Processing…',
        'success_title'     => 'Refund processed',
        'success_done'      => 'Done',
    ],

    'refund_approval' => [
        'title'   => 'Manager approval needed',
        'sub'     => 'This refund needs a manager\'s PIN before it submits.',
        'approve' => 'Approve refund',
        'approving' => 'Approving…',
    ],

    // Client-side strings for resources/js/cashier/cashier-page.js — every
    // call site there falls back to the ORIGINAL English text inline
    // (`this.labels.<key> || '<text>'`), so a missing key here never blanks
    // the cashier UI. Two strings that already existed verbatim elsewhere
    // are reused instead of duplicated: 'no_permission_discount' is
    // sales.errors.discount_not_allowed, and 'no_product_for_barcode' is
    // this file's blind_refund.unknown_barcode — see SaleController::cashier().
    // Keys are ordered to match their first appearance in cashier-page.js.
    'js' => [
        'pwa_update_ready'            => 'A new version is ready. Tap the connectivity pill → Update.',
        'catalog_refreshed'           => 'Catalog refreshed.',
        'catalog_refresh_failed'     => 'Couldn\'t reach the server — using cached data.',
        'sync_never'                  => 'never',
        // ':n' is replaced client-side with the elapsed count — kept
        // adjacent to the unit letter (no space) to match the original
        // `${ago}s ago` / `${..}m ago` / `${..}h ago` template literals.
        'sync_seconds_ago'            => ':ns ago',
        'sync_minutes_ago'            => ':nm ago',
        'sync_hours_ago'              => ':nh ago',
        'drawer_no_sale_recorded'      => 'Drawer open recorded.',
        'drawer_no_sale_open_manually' => 'Couldn\'t reach the printer — open the drawer manually.',
        'drawer_no_sale_failed'        => 'Could not record the drawer open.',
        'no_product_for_query'        => 'No product for ":query"',
        'stock_not_listed'            => '":name" — stock not listed yet. Receive stock first.',
        'out_of_stock_cant_add'       => '":name" is out of stock — can\'t add to cart.',
        'out_of_stock_oversell'       => '":name" is out of stock — selling below stock (inventory will go negative).',
        'no_camera'                   => 'No camera available on this device.',
        'camera_permission_denied'    => 'Camera permission denied. Use a USB scanner or allow camera access.',
        'camera_start_failed'         => 'Could not start the camera.',
        'weight_read_failed'          => 'Couldn\'t read a weight from ":code".',
        'variant_out_of_stock'        => '":name" is out of stock.',
        'batch_expired'               => '":name" expired on :date — cannot sell.',
        'weight_required'             => 'Enter a weight greater than zero.',
        'clear_order_title'           => 'Clear the current order?',
        'clear_order_message'         => 'Every item, the assigned customer, and any discount will be removed. This cannot be undone.',
        'clear_order_confirm'         => 'Clear order',
        'confirm_keep'                => 'Keep',
        'customer_name_required'      => 'Customer name is required.',
        'customer_email_invalid'      => 'Email address looks invalid.',
        'customer_phone_invalid'      => 'Phone number looks invalid.',
        'customer_queued_offline'     => 'Customer queued — will sync when online.',
        'customer_queue_failed'       => 'Couldn\'t queue customer: ',
        'unknown_error'               => 'unknown error',
        'discount_percent_max'        => 'Percent discount can\'t be greater than 100%.',
        'discount_amount_max_order'   => 'Discount amount can\'t be greater than the order total.',
        'discount_amount_max_line'    => 'Discount can\'t be greater than the line total.',
        'discount_approved'           => 'Discount approved.',
        'approval_failed'             => 'Approval failed.',
        'order_held_named'            => 'Order :number held.',
        'order_held'                  => 'Order held.',
        'hold_failed'                 => 'Couldn\'t hold the order.',
        'resume_failed'               => 'Couldn\'t resume that order.',
        'delete_held_title'           => 'Delete held order?',
        'delete_held_message'         => 'Order :label will be permanently removed.',
        'delete_held_confirm'         => 'Delete order',
        'held_order_deleted'          => 'Order :label deleted.',
        'refunds_need_internet'       => 'Refunds need an internet connection — try again when you\'re back online.',
        'refund_open_failed'          => 'Couldn\'t open this sale for refund.',
        'refund_failed'               => 'Refund failed.',
        'refund_approved'             => 'Refund approved.',
        'stock_exceeds_named'         => '":name" exceeds available stock — reduce qty to continue.',
        'stock_exceeds_generic'       => 'Some items exceed available stock — reduce qty to continue.',
        'qr_payment_needs_internet'   => 'QR payment needs an internet connection. Take cash, or retry when you\'re back online.',
        'payment_session_start_failed' => 'Couldn\'t start payment session.',
        'payment_session_expired'     => 'Payment session expired.',
        'payment_cancelled'           => 'Payment cancelled.',
        'payment_failed'              => 'Payment failed.',
        'link_copied'                 => 'Link copied.',
        'link_copy_failed'            => 'Couldn\'t copy — long-press the link to copy.',
        'sale_queued_offline'         => 'Sale queued — will sync when online.',
        'sale_queue_failed'           => 'Couldn\'t queue the sale: ',
        'complete_sale_failed'        => 'Something went wrong completing the sale.',
        'receipt_load_failed'         => 'Could not load the receipt to print.',
        'print_failed_queued'         => 'Print failed — added to the print queue to retry.',
        'print_failed_check'          => 'Print failed. Check the printer.',
        'refund_receipt_load_failed'  => 'Could not load the refund receipt to print.',
        'offline_print_failed'        => 'Couldn\'t print the receipt. Check the printer, or view it and print from there.',
        'popup_blocked'               => 'Allow pop-ups to view the receipt, or use Print instead.',
        'kit_includes'                => 'Includes :list',
    ],
];
