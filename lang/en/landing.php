<?php

/*
|--------------------------------------------------------------------------
| Landing page strings
|--------------------------------------------------------------------------
| Public marketing page rendered at "/" when demo mode is ON
| (POS_DEMO_MODE=true). Customer installs never see this page — they go
| straight to login / dashboard. See LandingController + routes/web.php.
|
| Copy is written for non-technical shop owners — plain, friendly language,
| no jargon. Keep it that way when editing.
*/

return [

    'meta' => [
        'title'       => 'Simple point of sale for shops, supermarkets & pharmacies',
        'description' => 'A fast, easy point of sale you own outright — works offline, runs on your own hosting, and never charges a monthly fee.',
    ],

    'nav' => [
        'features'    => 'Features',
        'industries'  => 'Who it\'s for',
        'docs'        => 'Documentation',
        'demo'        => 'Live demo',
        'sign_in'     => 'Sign in',
        'dashboard'   => 'Dashboard',
        'buy'         => 'Get it',
        'toggle_theme'=> 'Switch light / dark',
    ],

    // Shown on the primary buttons when the visitor is already signed in.
    'go_to_dashboard' => 'Go to your dashboard',

    'totop' => 'Back to top',

    'buy_now' => 'Buy now',

    'demo_badge' => 'Live demo',

    'hero' => [
        'eyebrow'          => 'Yours to own · Works offline · No monthly fees',
        'title'            => 'The point of sale your shop actually owns.',
        'subtitle'         => 'An easy, fast way to sell and run your shop, supermarket, or pharmacy. Pay once, own the software, set it up on your own hosting — and keep selling even when the internet drops.',
        'cta_demo'         => 'Try the live demo',
        'cta_learn'        => 'See what it does',
        'note'             => 'This is a live demo. Sign in with the sample accounts to look around every screen.',
        'pays_label'       => 'Accept payments with',
        'badge_offline'    => 'Offline Orders Sync',
        'badge_payment'    => 'Multi-Payment Support',
        'badge_branch'     => 'Multi-Branch Ready',
        'badge_inventory'  => 'Inventory Management',
        'trust_own'        => 'Pay once, own it forever',
        'trust_source'     => 'Full source code included',
        'trust_nofees'     => 'No monthly fees',
        'trust_offline'    => 'Keeps working offline',
        'trust_selfhosted' => 'Runs on your own hosting',
    ],

    'global' => [
        'payments_eyebrow' => 'Get paid',
        'payments_title'   => 'Take payment any way your customers like.',
        'payments_body'    => 'Cash, cards, part-cash part-card, and quick QR payments — with popular online gateways built in and ready to switch on.',

        'world_eyebrow'    => 'Made for everyone',
        'world_title'      => 'Ready for shops anywhere in the world.',
        'world_body'       => 'Use it in your own language and currency, set your time zone, and run it comfortably in right-to-left languages too.',
        'chip_languages'   => 'Any language',
        'chip_rtl'         => 'Right-to-left ready',
        'chip_timezones'   => 'Your time zone',
    ],

    'mock' => [
        'search' => 'Search or scan a product…',
        'total'  => 'Total',
        'amount' => '$148.50',
        'pay'    => 'Charge',
        'badge'  => 'Works offline',
    ],

    'stats' => [
        'gateways'        => 'Payment gateways',
        'gateways_val'    => '5',
        'stores'          => 'Stores & locations',
        'stores_val'      => 'Multi',
        'inventory'       => 'Stock & inventory',
        'inventory_val'   => 'Live',
        'offline'         => 'Works offline',
        'offline_val'     => '100%',
        'languages'       => 'Languages & RTL',
        'languages_val'   => 'Any',
        'monthly_fees'    => 'Monthly fees',
        'monthly_fees_val'=> 'Zero',
    ],

    'features' => [
        'eyebrow'  => 'Features',
        'title'    => 'Everything you need to make a sale — and run the whole shop.',
        'subtitle' => 'One simple app. Nothing extra to buy, no charge per person, no surprises.',
        'explore'  => 'Explore more features',

        'offline' => [
            'title' => 'Sell even without internet',
            'body'  => 'If your connection drops, you can keep ringing up sales. Everything saves safely and catches up on its own once you\'re back online.',
        ],
        'multi_industry' => [
            'title' => 'Made for all kinds of shops',
            'body'  => 'Whether you run a regular store, a supermarket, or a pharmacy, the app is ready to work the way your business does — right out of the box.',
        ],
        'realtime' => [
            'title' => 'All your counters stay in sync',
            'body'  => 'Sales and stock update across every checkout at the same time, so your numbers are always right — no need to refresh anything.',
        ],
        'self_hosted' => [
            'title' => 'Runs on your own hosting',
            'body'  => 'No costly setup and no tech team needed. A simple one-click installer gets you going on the hosting you already pay for.',
        ],
        'extensible' => [
            'title' => 'Easy to grow later',
            'body'  => 'Need something extra down the road? The app is built so new features and add-ons slot in cleanly — without breaking what already works.',
        ],
        'inventory' => [
            'title' => 'Stock you can trust',
            'body'  => 'See what you have, move stock between locations, fix counts, and keep an eye on expiry dates — all in one place.',
        ],
        'payments' => [
            'title' => 'Take payment any way',
            'body'  => 'Cash, cards, part-cash part-card, and quick QR payments — let customers pay however is easiest for them.',
        ],
        'compliance' => [
            'title' => 'Tax and rules taken care of',
            'body'  => 'Built-in tax handling, expiry tracking, and pharmacy rules help you stay on the right side of the law — no extra tools required.',
        ],
    ],

    'showcase' => [
        'eyebrow' => 'One clear view',
        'title'   => 'See how your shop is doing at a glance.',
        'body'    => 'Today\'s sales, busy hours, low stock, and your best-selling lines — all on one simple dashboard, updated as you sell.',
        'alt'     => 'The dashboard screen',
    ],

    'steps' => [
        'eyebrow'  => 'Try it now',
        'title'    => 'Looking around the demo takes a minute.',
        'subtitle' => 'This is a real, fully set-up shop — feel free to click around as much as you like.',

        'signin' => [
            'title' => 'Sign in with a sample account',
            'body'  => 'Open the demo and use the ready-made owner or cashier login — one tap fills in the details for you.',
        ],
        'explore' => [
            'title' => 'Make a sale and check the reports',
            'body'  => 'Try the checkout screen, add a few products and stock, take a payment, and browse the dashboards and reports.',
        ],
        'reset' => [
            'title' => 'It all resets every night',
            'body'  => 'Change anything you like — the demo tidies itself back to a fresh, fully-stocked shop each night, so nothing you do sticks.',
        ],
    ],

    'industries' => [
        'eyebrow'  => 'Who it\'s for',
        'title'    => 'Set up for the way you sell.',
        'subtitle' => 'Pick your type of shop when you start, and the app adjusts to fit.',

        'retail' => [
            'title' => 'Retail stores',
            'body'  => 'Clothing, electronics, and everyday goods. Handle sizes and colours, scan barcodes, and check customers out quickly.',
        ],
        'supermarket' => [
            'title' => 'Supermarkets',
            'body'  => 'Sell items by weight, read price stickers straight from the scale, and keep big product lists fast and easy to search.',
        ],
        'pharmacy' => [
            'title' => 'Pharmacies',
            'body'  => 'Track batches and expiry dates, follow medicine rules, and always sell the stock that expires soonest first.',
        ],
    ],

    // Comprehensive "everything included" feature grid. Labels are short
    // (2–3 words). Keep this list to features that genuinely ship.
    'grid' => [
        'eyebrow'  => 'Everything included',
        'title'    => 'One app. Every tool your shop needs.',
        'subtitle' => 'No add-ons to buy and nothing locked behind a higher tier — it\'s all in the box.',
        'more'     => '& many more',
        'items' => [
            'offline'      => 'Offline-first checkout',
            'barcode'      => 'Barcode scanning',
            'multistore'   => 'Multi-store & locations',
            'gateways'     => 'Online payment gateways',
            'split'        => 'Split payments',
            'refunds'      => 'Returns & refunds',
            'hold'         => 'Hold & resume sales',
            'shifts'       => 'Cash shifts & X/Z reports',
            'weight'       => 'Sell by weight',
            'discounts'    => 'Order discounts',
            'customers'    => 'Customers & groups',
            'credit'       => 'Store credit & dues',
            'variants'     => 'Product variants',
            'kits'         => 'Kits & bundles',
            'batches'      => 'Batch & expiry tracking',
            'transfers'    => 'Stock transfers',
            'purchases'    => 'Purchases & suppliers',
            'counts'       => 'Stock counts & adjustments',
            'lowstock'     => 'Low-stock alerts',
            'tax'          => 'Flexible tax engine',
            'roles'        => 'Roles & permissions',
            'dashboard'    => 'Dashboard & insights',
            'importexport' => 'CSV / Excel import & export',
            'languages'    => 'Multi-language & RTL',
            'darkmode'     => 'Dark mode',
            'backups'      => 'Backups & updates',
            'pwa'          => 'Install as an app',
            'notifications'=> 'Real-time notifications',
        ],
    ],

    'gallery' => [
        'eyebrow'  => 'A look inside',
        'title'    => 'See the app from every angle.',
        'subtitle' => 'Real screens from the admin — products, stock, purchases, reports, and more.',
        'prev'     => 'Previous screenshot',
        'next'     => 'Next screenshot',
        'close'    => 'Close',
    ],

    'tech' => [
        'eyebrow'  => 'Under the hood',
        'title'    => 'Built with modern, reliable technology.',
        'subtitle' => 'A clean, well-known stack — fast, secure, and easy for any developer to customize.',
    ],

    // CodeCanyon license comparison. IMPORTANT: confirm these rows match
    // your actual Regular vs Extended terms before publishing.
    'pricing' => [
        'eyebrow'   => 'Licensing',
        'title'     => 'Find the right license for your business.',
        'subtitle'  => 'A one-time payment on CodeCanyon — pick the license that fits how you\'ll use it.',
        'col_feature'  => 'What\'s included',
        'col_regular'  => 'Regular License',
        'col_extended' => 'Extended License',
        'recommended'  => 'Best value',
        'band_text'    => 'Building it for a client, or charging your own users? Choose the Extended License.',
        'band_cta'     => 'Get the Extended License',
        'rows' => [
            'lifetime'   => 'Lifetime license validity',
            'domain'     => 'Use on your own install',
            'support'    => '6 months of support',
            'premium'    => 'All features included',
            'updates'    => 'Free lifetime updates',
            'source'     => 'Full source code',
            'personal'   => 'For your own single business',
            'install'    => 'Free one-time installation',
            'remote'     => 'Remote setup help (AnyDesk)',
            'priority'   => '1 year priority support',
            'branding'   => 'Free branding / logo setup',
            'commercial' => 'For client / commercial projects',
        ],
    ],

    'help' => [
        'title' => 'Need help with setup or customization?',
        'body'  => 'Our team can install it for you, set up your branding, configure features, and tailor it to your business — so you can focus on selling.',
        'cta'   => 'Talk to our expert',
    ],

    'cta' => [
        'title'     => 'Like what you see? Make it yours.',
        'subtitle'  => 'Get the full app with a one-time payment — no monthly fees, ever. Or keep exploring the demo first; it resets clean every night.',
        'button'    => 'Keep exploring the demo',
        'buy'       => 'Get it on CodeCanyon',
        'note'      => 'One-time payment · Full source code · Self-hosted and private',
        'secondary' => 'Visit us',
    ],

    'footer' => [
        'tagline'  => 'A point of sale you own outright — no monthly fees, ever.',
        'docs'     => 'Documentation',
        'buy'      => 'Get it on CodeCanyon',
        'product'  => 'Visit us',
        'privacy'  => 'Privacy policy',
        'terms'    => 'Terms of service',
        'rights'   => 'All rights reserved.',
        'made_by'  => 'Made with :heart by :company',
    ],

];
