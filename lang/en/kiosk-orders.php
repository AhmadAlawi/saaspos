<?php

/**
 * Self-Ordering Kiosk — staff orders queue strings (Slice 2, order mode).
 * The pending `placed` orders awaiting counter payment.
 */

return [

    'title'        => 'Kiosk orders',
    'crumb_parent' => 'Sales',
    'sub'          => 'Orders placed at self-ordering kiosks — take payment, then hand them over.',
    'pending'      => 'Pending',
    'list_title'   => 'Pending orders',
    'walk_in'      => 'Walk-in',
    // The shopper paid at the kiosk by scanning a static UPI QR. Nothing calls
    // back to confirm the transfer, so staff verify it before handing over.
    'claim_badge'  => ':method — claimed, not verified',
    'claim_hint'   => 'The customer says they paid this at the kiosk. Check your UPI app for the transfer before handing over the order.',
    'claim_ref'    => 'Ref',

    'claim' => [
        'title'     => 'Unverified :method payment',
        'body'      => 'The customer scanned the kiosk QR and says they paid. A UPI QR sends no confirmation to this system — find the transfer in your own UPI app before you settle this order.',
        'amount'    => 'Expected amount',
        'reference' => 'Reference in narration',
        'at'        => 'Claimed at',

        'confirm_title' => 'Confirm the :method transfer',
        'confirm_body'  => 'Have you seen :amount arrive in your UPI app, with reference :reference? Settling this order marks it paid and hands over the goods.',
        'confirm_label' => 'Yes, I have seen the transfer',
    ],

    'held_label'          => 'Kiosk :code',
    'reject_default_reason' => 'Rejected from the kiosk orders queue',

    'columns' => [
        'pickup'    => 'Pickup',
        'placed_at' => 'Placed',
        'customer'  => 'Customer',
        'items'     => 'Items',
        'total'     => 'Total',
        'actions'   => 'Actions',
    ],

    'actions' => [
        'accept'  => 'Take payment',
        'pay'     => 'Take payment',
        'reject'  => 'Reject',
        'view'    => 'View sale',
        'collect' => 'Handed over',
    ],

    'tabs' => [
        'pending' => 'Pending payment',
        'collect' => 'Ready for pickup',
        'all'     => 'All orders',
    ],

    // The full kiosk-order history — the two queues above only hold open work,
    // so an order vanishes from them once it's paid, collected or rejected.
    // Date-windowed (defaults to today) and server-paginated.
    'all' => [
        'title'       => 'All kiosk orders',
        'placed_at'   => 'Ordered',
        'status'      => 'Status',
        'status_all'  => 'Any status',
        'date_from'   => 'From',
        'date_to'     => 'To',
        'search'      => 'Search order or pickup code…',
        'uncollected' => 'Not collected',
        'empty_title' => 'No kiosk orders in this range',
        'empty_sub'   => 'Widen the dates or clear the filters to look further back.',
    ],

    // Sales already paid at the kiosk whose goods are still behind the counter.
    'collect' => [
        'title'          => 'Paid at kiosk — awaiting collection',
        'paid'           => 'Paid',
        'paid_at'        => 'Paid',
        'empty_title'    => 'Nothing waiting to be collected',
        'empty_sub'      => 'Orders paid at a self-ordering kiosk appear here until you hand them over.',
        'subtotal'       => 'Subtotal',
        'discount'       => 'Discount',
        'tax'            => 'Tax',
        'total'          => 'Total',
        'tendered'       => 'Paid with',
        'unknown_method' => 'Unknown method',
        'open_sale'      => 'Open full sale',
    ],

    'pay' => [
        'title'       => 'Take payment — :code',
        'items'       => 'Order',
        'subtotal'    => 'Subtotal',
        'tax'         => 'Tax',
        'amount_due'  => 'Amount due',
        'remaining'   => 'Remaining',
        'method'             => 'Payment',
        'method_placeholder' => 'Select a payment method',
        'tendered'    => 'Cash received',
        'change'      => 'Change due',
        'reference'   => 'Reference',
        'upi_scan'    => 'Scan to pay :amount',
        'upi_hint'    => 'The customer pays in their UPI app. Enter the UTR below, then confirm.',
        'add_payment' => 'Add payment',
        'charge_qr'   => 'Charge via QR',
        'qr_scan'     => 'Ask the customer to scan',
        'qr_starting' => 'Starting payment…',
        'qr_hint'     => 'They pick a gateway and pay on their phone. This closes automatically once it clears.',
        'confirm'     => 'Confirm payment',
        'cancel'      => 'Cancel',
    ],

    'errors' => [
        'not_pending' => 'This order has already been paid or rejected.',
        'no_lines'    => 'Keep at least one item, or reject the order instead.',
    ],

    'confirm' => [
        'reject_title' => 'Reject order :code?',
        'reject_body'  => 'The reserved stock is released and the order is voided. This cannot be undone.',
    ],

    'flash' => [
        'paid'      => 'Order :code paid — sale :number completed.',
        'rejected'  => 'Order :code rejected.',
        'collected' => 'Order :code handed over.',
    ],

    'empty' => [
        'title' => 'No pending kiosk orders',
        'sub'   => 'Orders placed at a self-ordering kiosk show up here for payment.',
    ],

    'export' => [
        'button'    => 'Export',
        'csv'       => 'Export CSV',
        'xlsx'      => 'Export Excel',
        'pickup'    => 'Pickup code',
        'number'    => 'Order number',
        'placed_at' => 'Placed at',
        'customer'  => 'Customer',
        'items'     => 'Items',
        'total'     => 'Total',
        'note'      => 'Note',
    ],

    'notify' => [
        'title'   => 'New kiosk order',
        'message' => 'Order :code (:total) is waiting at the kiosk.',
    ],

];
