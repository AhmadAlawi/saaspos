<?php

return [
    'title'        => 'Hardware',
    'sub'          => 'Check your receipt printer, cash drawer, and scanners, and run a test.',
    'crumb_parent' => 'Settings',

    'modes' => [
        'webusb'        => 'Thermal (WebUSB)',
        'browser_print' => 'Browser print',
        'network'       => 'Network',
        'none'          => 'Disabled',
        'unconfigured'  => 'Not configured',
    ],

    'drawer' => [
        'via_printer'  => 'Via receipt printer',
        'unconfigured' => 'Not configured',
    ],

    'no_terminal_title' => 'No terminal selected',
    'no_terminal_sub'   => 'Hardware is configured per terminal. <a href=":url">Set up a terminal</a> to enable printer and drawer settings.',

    'devices' => [
        'title'           => 'Devices',
        'sub'             => 'Peripheral status for this browser.',
        'for_terminal'    => 'Status for terminal “:name”.',
        'receipt_printer' => 'Receipt printer',
        'cash_drawer'     => 'Cash drawer',
        'scanner'         => 'Barcode scanner',
        'scanner_hint'    => 'USB scanners type like a keyboard — scan into any field to test.',
        'scanner_status'  => 'Plug & scan',
        'camera'          => 'Camera scanner',
        'camera_hint'     => 'Used on the cashier screen for tablets without a USB scanner.',
        'available'       => 'Available',
        'unavailable'     => 'Not available',
    ],

    'actions' => [
        'test_print'  => 'Test print',
        'test_drawer' => 'Test drawer kick',
    ],

    'recent' => [
        'title'   => 'Recent prints (last 7 days)',
        'total'   => 'Total',
        'success' => 'Succeeded',
        'failed'  => 'Failed / queued',
        'last'    => 'Last print',
    ],

    'test' => [
        'receipt_title' => 'TEST PRINT',
        'receipt_body'  => 'If you can read this, your printer is working.',
    ],
];
