<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application version
    |--------------------------------------------------------------------------
    |
    | The canonical version of this install. Everything that needs to know
    | "what version am I?" reads `config('pos.version')` — the updater's
    | feed comparison, the backup manifest, the footer, support diagnostics.
    |
    | This ships inside config/pos.php (which the updater REPLACES on every
    | update), so a new release naturally carries its own new version number.
    | Bump it here when cutting a release. Format: semver (MAJOR.MINOR.PATCH).
    |
    */

    'version' => '1.0.4',

    /*
    |--------------------------------------------------------------------------
    | Product name
    |--------------------------------------------------------------------------
    |
    | The product's own display name — used where we must brand as the product
    | itself rather than the customer's company, most notably the installer
    | (which runs before any company exists, so `config('app.name')` is still
    | the framework default "Laravel" there). One place to change when the
    | final name is locked. The running app shows the company name instead, via
    | ApplyCompanySettings.
    |
    */

    'name' => 'POS',

    /*
    |--------------------------------------------------------------------------
    | Demo mode
    |--------------------------------------------------------------------------
    |
    | When enabled, the login page shows a small notice with a link out to the
    | configured marketing site — so visitors who land on the live demo know
    | they're looking at a demo and can find their way back to the product
    | website. The toggle is environment-driven (.env) so the same code can
    | run as either the live demo or a real install without code changes.
    |
    */

    'demo' => [

        // Master switch. Set POS_DEMO_MODE=true in .env on the public demo
        // install; leave it false (the default) on customer installs.
        'enabled' => env('POS_DEMO_MODE', false),

        // Where the "click here" link in the demo banner points.
        'website_url' => env('POS_DEMO_WEBSITE_URL', 'https://tillora.sphereofthesun.com'),

        // Google Analytics 4 Measurement ID (e.g. "G-XXXXXXXXXX") for the
        // public demo. Left empty by default and ONLY ever loaded when demo
        // mode is on — a real customer install never phones home to GA, which
        // keeps the "self-hosted, no callbacks" promise intact. Set
        // POS_DEMO_GA_ID in .env on the demo install to enable tracking.
        'analytics_id' => env('POS_DEMO_GA_ID', ''),

        // Where the landing page's buy/subscribe buttons point. Tillora is
        // subscription SaaS (Stripe Checkout via the signup wizard), not a
        // one-time marketplace purchase — this is the whole reason the demo
        // exists, so it's first-class config. Override for forks via .env.
        'purchase_url' => env('POS_DEMO_PURCHASE_URL', 'https://tillora.sphereofthesun.com/pricing'),

        // Falls back to purchase_url when left unset — Tillora has no
        // separate "extended license" tier, so this just points at the same
        // signup wizard.
        'extended_url' => env('POS_DEMO_EXTENDED_URL', 'https://tillora.sphereofthesun.com/pricing'),

        // "Talk to our expert" button on the landing page's customization
        // band. A wa.me link opens WhatsApp; swap for any contact URL.
        'support_whatsapp' => env('POS_DEMO_SUPPORT_WHATSAPP', ''),

        // Pre-fillable demo credentials shown on the login page when demo mode
        // is enabled. Each entry renders as a card with a "Copy & Fill" button
        // that pastes the email + password into the login form. Empty entries
        // (no email OR no password) are skipped at render time, so the demo
        // operator can light up just the roles they actually seeded by leaving
        // the others blank in .env.
        'credentials' => [
            [
                'role'     => 'admin',
                'email'    => env('POS_DEMO_ADMIN_EMAIL', ''),
                'password' => env('POS_DEMO_ADMIN_PASSWORD', ''),
            ],
            [
                // The demo cashier is a fixed test account seeded by
                // DemoCashierUserSeeder, so it ships with working defaults
                // (shown on the login page whenever demo mode is on). The
                // admin above stays env-only because it's the operator's own
                // install admin. Override these via .env if you reseed.
                'role'     => 'cashier',
                'email'    => env('POS_DEMO_CASHIER_EMAIL', 'cashier@demo.test'),
                'password' => env('POS_DEMO_CASHIER_PASSWORD', 'cashier1234'),
            ],

            // Manager/Stock Keeper/Accountant demo logins — seeded by
            // DemoRoleUsersSeeder (generalizes DemoCashierUserSeeder's
            // pattern to every role, not just Cashier), same env-driven
            // shape. Blank by default; set the env vars on the demo install
            // to light these up.
            [
                'role'     => 'manager',
                'email'    => env('POS_DEMO_MANAGER_EMAIL', 'manager@demo.test'),
                'password' => env('POS_DEMO_MANAGER_PASSWORD', 'manager1234'),
            ],
            [
                'role'     => 'stock_keeper',
                'email'    => env('POS_DEMO_STOCK_KEEPER_EMAIL', 'stockkeeper@demo.test'),
                'password' => env('POS_DEMO_STOCK_KEEPER_PASSWORD', 'stock1234'),
            ],
            [
                'role'     => 'accountant',
                'email'    => env('POS_DEMO_ACCOUNTANT_EMAIL', 'accountant@demo.test'),
                'password' => env('POS_DEMO_ACCOUNTANT_PASSWORD', 'account1234'),
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-updater
    |--------------------------------------------------------------------------
    |
    | See docs/features/updater-hooks.md. The feed is a static JSON endpoint
    | we control; the public key verifies the Ed25519 signature on each
    | release zip so a compromised feed or a man-in-the-middle can't push a
    | malicious update. Per-install preferences (channel, auto-check, pinned
    | version, skipped versions) live in the database — NOT here — so they
    | survive an update.
    |
    */

    'updater' => [

        // The update feed. env-overridable for forks / staging.
        // TODO (update-push system, not yet built): this endpoint doesn't
        // exist yet — was previously defaulting to the old vendor's dead
        // updates.infinitietech.com, silently failing every check. Point
        // this at a real Tillora/SMT-controlled feed once that system is
        // built; until then leave POS_UPDATE_FEED_URL unset in production
        // rather than pointing at a domain nobody here controls.
        'feed_url' => env('POS_UPDATE_FEED_URL', ''),

        // Base64-encoded Ed25519 public key used to verify release signatures.
        // Populated at release time; empty here until the signing key exists,
        // and the updater treats an empty key as "signature checks disabled"
        // only in non-production (production refuses to install unsigned).
        'public_key' => env('POS_UPDATE_PUBLIC_KEY', ''),

        // Default channel for a fresh install. The per-install choice is a
        // database setting that overrides this.
        'channel' => env('POS_UPDATE_CHANNEL', 'stable'),

        // Seconds to wait on the feed endpoint before giving up. A slow or
        // unreachable feed must never block the app — see the graceful
        // failure handling in the update client.
        'feed_timeout' => 15,

        // Seconds allowed for streaming a release zip down (much larger than
        // the feed JSON — tens of megabytes).
        'download_timeout' => 300,

    ],

    /*
    |--------------------------------------------------------------------------
    | Licensing
    |--------------------------------------------------------------------------
    |
    | See docs/features/installer.md §3 + §6. The license is validated once at
    | install (Step 3) against our license server, then re-checked in the
    | background every ~30 days. A failed re-check NEVER locks the app — it only
    | surfaces a banner. The license KEY lives in .env (LICENSE_KEY); the
    | validated DETAILS (buyer, type, support window) are cached in the DB.
    |
    | `public_key` verifies the offline-activation blob (Ed25519) for customers
    | behind firewalls who can't reach the license server during install.
    |
    */

    'license' => [

        // Master switch for license enforcement. When false the installer skips
        // the license-key step entirely and the background re-check no-ops —
        // useful for free / internal builds or offline development.
        //
        // ON: the installer asks for a license key (issued by
        // SmtLicenseServer on signup) and validates it against `check_url`
        // below. Set POS_LICENSE_REQUIRED=false in .env to disable on a
        // fork / internal build.
        'required' => env('POS_LICENSE_REQUIRED', true),

        // Developer bypass: when true, the installer's license validation
        // returns a stub "valid" response WITHOUT hitting the validator
        // server. Use for offline development against a live validator
        // URL; never enable in production. OFF by default — set
        // `POS_LICENSE_DEV_BYPASS=true` in `.env` to opt in.
        'dev_bypass' => env('POS_LICENSE_DEV_BYPASS', false),

        // Panel-side license verification.
        //
        // `required` above governs the INSTALLER's one-time license-key step.
        // This flag governs everything the RUNNING app does with that code:
        //   - the background re-check (and its "Re-check now" button),
        //   - the Settings → License screen,
        //   - the nag banner across the admin shell.
        //
        // Historically OFF by default: re-verifying inside the panel surfaced a
        // permanent "invalid license" banner on fresh Envato-style one-time-
        // validate installs. For a SaaS build this must be ON — seat/plan/
        // feature entitlements only ever refresh via this re-check, so a SaaS
        // instance with it off would never see plan changes or seat-limit
        // updates pushed from the license server. Set POS_LICENSE_RECHECK=false
        // in .env to restore the old self-hosted one-time-validate behavior.
        'recheck' => env('POS_LICENSE_RECHECK', true),

        // The activated license key + its install fingerprint, written to .env
        // by the installer. Surfaced through config so the background re-check
        // reads them even when the config is cached (env() outside config
        // returns null once `config:cache` runs).
        'key'         => env('LICENSE_KEY', ''),
        'fingerprint' => env('LICENSE_FINGERPRINT', ''),

        // Per-instance HMAC secret, minted once by the license server at
        // provisioning (POST /api/v1/instances/register) and written to .env
        // alongside LICENSE_KEY. Signs every phone-home request (see
        // App\Services\Licensing\LicenseSigner) — never re-transmitted, never
        // logged. A missing secret is treated as "not configured", the same
        // as a missing key: the check is skipped rather than sent unsigned.
        'hmac_secret' => env('LICENSE_HMAC_SECRET', ''),

        // The license server endpoint. env-overridable for forks / staging.
        //   POST <check_url>
        //   { license_key, domain_url, fingerprint, version, usage: {...} }
        //   Headers: X-License-Key, X-Instance-Fingerprint, X-Timestamp, X-Signature
        // The server replies with `{status: 'valid'|'invalid'|'suspended'|
        // 'past_due', plan: {...}, seats: {...}, features: {...}, ...}`.
        // Confirmed live (2026-09-12): this was defaulting to the old
        // Envato/CodeCanyon-era validator (validator.infinitietech.com),
        // which SmtLicenseServer (the actual license server every SaaS
        // tenant should be checking against) never provisioned an override
        // for — meaning every tenant's license phone-home has been hitting
        // a dead endpoint since Phase 7. Masked by the "never lock the
        // app" policy (an unreachable check just preserves last-known
        // status), so it never surfaced as a visible failure. Now defaults
        // to the real endpoint; ProvisionInstance also sets
        // POS_LICENSE_CHECK_URL explicitly per tenant so this default is
        // only ever a fallback for local dev.
        'check_url' => env('POS_LICENSE_CHECK_URL', 'https://tillora.sphereofthesun.com/api/v1/instances/validate'),

        // Seconds to wait on the license server before giving up. Offline
        // activation has been removed in this build; an unreachable server
        // surfaces a "couldn't reach license server" error and the
        // customer must retry once connectivity is restored.
        'timeout' => 15,

    ],

];
