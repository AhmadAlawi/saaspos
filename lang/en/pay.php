<?php

return [
    'title'                 => 'Pay this bill',
    'subtitle'              => 'Choose how you\'d like to pay.',
    'secure'                => 'Secure payment',
    'you_are_paying'        => 'You are paying',
    'order_summary'         => 'Order summary',
    'subtotal'              => 'Subtotal',
    'discount'              => 'Discount',
    'tax_included'          => 'Tax (included)',
    'line_tax'              => 'Tax :rate% · :amount',
    'total'                 => 'Total',
    'choose_method'         => 'Choose a payment method',
    'already_paid'          => 'This payment has already been completed.',
    'session_expired'       => 'This payment link has expired or been cancelled. Ask the cashier to start a new one.',
    'no_methods_configured' => 'No payment methods are configured yet.',
    'auto_confirm'          => 'After payment, this page will confirm automatically. You may close it once you see the success message.',
    'footer_safe'           => 'You\'ll be redirected to your bank or payment provider to complete payment safely.',
    'hidden_for_currency'   => 'Some payment methods are hidden because they don\'t support :currency: :names.',

    // Wallet buttons (Apple Pay / Google Pay) — minimal Payment Request
    // Button page, see pay/pos_wallet.blade.php.
    'wallet' => [
        'apple_pay_title'  => 'Pay with Apple Pay',
        'google_pay_title' => 'Pay with Google Pay',
        'loading'          => 'Loading payment…',
        'unavailable'      => 'Apple Pay / Google Pay isn\'t set up on this device.',
        'fallback_link'    => 'Pay by card instead',
        'processing'       => 'Confirming your payment…',
        'failed'           => 'Payment failed — please try again.',
    ],

    'capabilities' => [
        'stripe'       => 'Cards · Wallets',
        'razorpay'     => 'UPI · Cards · Netbanking',
        'paystack'     => 'Cards · Bank · USSD',
        'flutterwave'  => 'Cards · Bank · Mobile money',
        'mercado_pago' => 'Cards · Wallets · Bank transfer',
    ],

    'invalid' => [
        'title' => 'Payment link not found',
        'sub'   => 'The link is invalid, has been cancelled, or has expired. Please ask the cashier to start a new one.',
    ],

    'return' => [
        'title'        => 'Payment status',
        'paid_title'   => 'Payment received',
        'paid_sub'     => 'Thanks! You can hand the receipt over and walk away — the cashier already saw the confirmation.',
        'failed_title' => 'Payment didn\'t go through',
        'failed_sub'   => 'Nothing was charged. You can try again from the cashier counter.',
    ],
];
