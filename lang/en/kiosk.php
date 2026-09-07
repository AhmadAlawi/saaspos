<?php

/**
 * Self-Ordering Kiosk — Slice 1 strings.
 *
 * Rendered on the customer-operated kiosk. Most are passed to the kiosk JS
 * as pre-translated labels (the kiosk makes no __() calls of its own) so it
 * keeps full locale + RTL parity. See docs/features/kiosk-self-ordering.md.
 */

return [

    'title' => 'Self-order',

    'status' => [
        'open' => 'Open · Self-order here',
    ],

    'head' => [
        'subtitle' => 'Self-order',
    ],

    'idle' => [
        'welcome' => 'Order in a few taps',
        'tagline' => 'Browse the aisles, build your bag, pay your way.',
        'start'   => 'Touch anywhere to start',
    ],

    'browse' => [
        'search'        => 'Search products, or scan a barcode',
        'all'           => 'All',
        'add'           => 'Add',
        'from'           => 'from',
        'options'        => ':count options',
        'choose_option'  => 'Choose an option',
        'combo'          => 'Combo',
        'whats_included' => 'What’s included',
        'add_combo'      => 'Add combo',
        'view_cart'     => 'View cart',
        'empty_search'  => 'No products match your search.',
        'loading'       => 'Loading the menu…',
        'offline'       => 'The catalog isn’t available yet. Please ask a staff member.',
    ],

    'cart' => [
        'keep_shopping' => 'Keep shopping',
        'your_order'    => 'Your order',
        'each'          => 'each',
        'empty'         => 'Your cart is empty',
        'start_shopping' => 'Start shopping',
        'note'          => 'Add a note (optional)',
        'note_hint'     => 'e.g. no plastic bag',
        'subtotal'      => 'Subtotal',
        'tax'           => 'Tax',
        'total'         => 'Total',
        'pay_now'       => 'Pay now',
        'pay_counter'   => 'Order & pay at counter',
        'pay_choice'    => 'How would you like to pay?',
        'coming_soon'   => 'Checkout is coming soon.',
        'placing'       => 'Placing your order…',
    ],

    'pay' => [
        'amount_due'  => 'Amount due',
        'scan_to_pay' => 'Scan to pay',
        'scan_hint'   => 'Scan this code with your phone to pay.',
        'waiting'     => 'Waiting for payment…',
        'cancel'      => 'Cancel',
        'failed'      => 'Payment didn’t go through. Please try again.',
        'starting'    => 'Starting payment…',
        'help'        => 'Payment received — please ask a staff member for your receipt.',
    ],

    // Static UPI QR — the customer pays from their own app at the machine.
    // There is no callback, so staff confirm the transfer at the counter.
    // Deliberately never says "pay now" or "paid" — the transfer is unverified
    // until a human confirms it, and the copy must not promise otherwise.
    'upi' => [
        'pay_with'    => 'Scan & pay with :method',
        'scan'        => 'Scan to pay',
        'hint'        => 'Open any UPI app, scan this code, and pay the amount shown. Then tap below — staff will confirm the transfer before handing over your order.',
        'i_have_paid' => 'I’ve sent the payment',
        'back'        => 'Back',
    ],

    'thankyou' => [
        'title'        => 'Thank you!',
        'order_placed' => 'Order placed',
        'pickup_label' => 'Your pickup number',
        'sub'          => 'Please pay at the counter to collect your order.',
        'done'         => 'Done',
        'paid'         => 'Payment received',
        'collect'      => 'Show this number at the counter to collect your order.',
        'receipt_scan' => 'Scan for your receipt',
        'receipt_hint' => 'Scan this code to view or save your receipt.',
        'offline'      => 'Order saved',
        'offline_sub'  => 'We’re offline right now — your order is saved and will reach the counter shortly. Please pay at the counter.',
        'upi_claimed'     => 'Order placed — payment to confirm',
        'upi_claimed_sub' => 'Show this number at the counter. Staff will check your UPI transfer, then hand over your order.',
    ],

    'errors' => [
        'not_configured'   => 'This station isn’t set up as a kiosk. Bind it to a kiosk terminal first, or enable the kiosk in Settings → Hardware.',
        'place_failed'     => 'Sorry, we couldn’t place your order. Please ask a staff member.',
        'stock_limit'      => 'Sorry, only :count left in stock.',
        'out_of_stock'     => 'Sorry, :name just went out of stock.',
        'checkout_unpaid'  => 'This payment hasn’t completed yet.',
        'checkout_expired' => 'This payment session has expired. Please try again.',
    ],

    'customer' => [
        'title'    => 'Your details',
        'name'     => 'Name',
        'phone'    => 'Phone (optional)',
        'required' => 'Please add your name or phone to continue.',
    ],

    'exit' => [
        'title'    => 'Exit kiosk mode?',
        'body'     => 'This returns to the staff cashier.',
        'stay'     => 'Stay',
        'leave'    => 'Exit',
        'email'    => 'Supervisor email',
        'password' => 'Password',
        'denied'   => 'Those credentials can’t exit the kiosk.',
    ],

];
