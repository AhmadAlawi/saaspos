<?php

/**
 * Customer-Facing Display (CFD) — Slice 1 strings.
 *
 * These are rendered on the shopper-facing second screen. They're passed
 * to the display's JS as pre-translated labels (the CFD makes no __() calls
 * of its own) so it keeps full locale + RTL parity.
 * See docs/features/customer-display.md.
 */

return [

    'title' => 'Customer display',
    'toggle_theme' => 'Switch light / dark',

    'status' => [
        'open' => 'Open now',
    ],

    'idle' => [
        'welcome' => 'Welcome to :store',
        'tagline' => 'Thanks for stopping by — we\'ll be right with you.',
        'offers'  => "Today's offers",
        // Value-strip chips (edit freely to match your store).
        'h1_title' => 'Fast checkout',
        'h1_sub'   => 'Quick, friendly service',
        'h2_title' => 'Digital receipts',
        'h2_sub'   => 'On paper or your phone',
        'h3_title' => 'Here to help',
        'h3_sub'   => 'Just ask our team',
    ],

    'sale' => [
        'your_order' => 'Your order',
        'item'       => 'item',
        'items'      => 'items',
        'col_item'   => 'Item',
        'col_qty'    => 'Qty',
        'col_amount' => 'Amount',
        'subtotal'   => 'Subtotal',
        'discount'   => 'Discount',
        'tax'        => 'Tax',
        'total'      => 'Total',
        'added'      => 'Added to order',
        'scanning'   => 'Please continue — the cashier is scanning your items',
        'loyalty'    => 'Loyalty member',
        'points'     => 'points',
        'each'       => 'each',
    ],

    'payment' => [
        'amount_due'  => 'Amount due',
        'tendered'    => 'Tendered',
        'change_due'  => 'Change due',
        'scan_to_pay' => 'Scan to pay',
        'scan_hint'   => 'Point your camera at the code, then choose your payment app',
        'scan_upi'      => 'Scan to pay with UPI',
        'scan_upi_hint' => 'Open any UPI app and scan to pay',
        'scan_apple_pay'   => 'Scan to pay with Apple Pay',
        'scan_google_pay'  => 'Scan to pay with Google Pay',
        'scan_wallet_hint' => 'Point your camera at the code to open the payment page',
    ],

    'thankyou' => [
        'title'        => 'Thank you!',
        'change'       => 'Change',
        'receipt_scan' => 'Scan for your receipt',
        'receipt_hint' => 'A digital copy, no paper needed',
    ],

];
