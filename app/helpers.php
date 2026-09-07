<?php

/**
 * Global hook helpers — WordPress-style action / filter API.
 *
 * Plugin authors write code that reads identically to the WordPress
 * ecosystem they already know:
 *
 *   add_action('category.after_create', fn($cat) => Logger::info($cat->id));
 *   add_filter('category.fields', fn($attrs) => array_merge($attrs, ['flag' => 1]));
 *
 * These thin wrappers resolve the singleton {@see App\Hooks\HookManager}
 * out of the container so the calls stay short and don't require
 * dependency injection at every site.
 *
 * Hook names: see App\Hooks\HookManager docblock for the naming convention.
 */

use App\Hooks\HookManager;

if (!function_exists('hooks')) {
    function hooks(): HookManager
    {
        return app(HookManager::class);
    }
}

if (!function_exists('add_action')) {
    function add_action(string $hook, callable $callback, int $priority = 10): void
    {
        hooks()->addAction($hook, $callback, $priority);
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $hook, callable $callback, int $priority = 10): void
    {
        hooks()->addFilter($hook, $callback, $priority);
    }
}

if (!function_exists('do_action')) {
    function do_action(string $hook, mixed ...$args): void
    {
        hooks()->doAction($hook, ...$args);
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
    {
        return hooks()->applyFilters($hook, $value, ...$args);
    }
}

if (!function_exists('app_regional')) {
    /**
     * The company's regional display settings — time zone + date/time
     * formats (Settings → Regional). Container-cached per request like
     * {@see app_currency()}; falls back to sensible defaults before the
     * install/migration has run. Call `forget_app_regional()` after a
     * change to re-read in-request.
     *
     * @return array{timezone: string, date_format: string, time_format: string}
     */
    function app_regional(): array
    {
        $app = app();
        if ($app->bound('pos.app_regional')) {
            return $app->make('pos.app_regional');
        }

        $resolved = [
            'timezone'    => config('app.timezone', 'UTC') ?: 'UTC',
            'date_format' => 'd M Y',
            'time_format' => 'h:i A',
        ];

        try {
            $row = \Illuminate\Support\Facades\DB::table('company')->first(['timezone', 'date_format', 'time_format']);
            if ($row) {
                $resolved = [
                    'timezone'    => $row->timezone ?: $resolved['timezone'],
                    'date_format' => $row->date_format ?: $resolved['date_format'],
                    'time_format' => $row->time_format ?: $resolved['time_format'],
                ];
            }
        } catch (\Throwable $e) {
            // table not ready yet — keep defaults
        }

        $app->instance('pos.app_regional', $resolved);
        return $resolved;
    }
}

if (!function_exists('forget_app_regional')) {
    function forget_app_regional(): void
    {
        if (app()->bound('pos.app_regional')) {
            app()->forgetInstance('pos.app_regional');
        }
    }
}

if (!function_exists('app_timezone')) {
    /** The configured display time zone (IANA). */
    function app_timezone(): string
    {
        return app_regional()['timezone'];
    }
}

if (!function_exists('format_date')) {
    /**
     * Format a stored (UTC) timestamp as a date in the company's zone +
     * date format. Null-safe. Pass an explicit `$format` to override.
     */
    function format_date(mixed $value, ?string $format = null): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $r = app_regional();

        return \Illuminate\Support\Carbon::parse($value)->timezone($r['timezone'])->format($format ?? $r['date_format']);
    }
}

if (!function_exists('format_time')) {
    /** Format a stored (UTC) timestamp as a time in the company's zone + time format. Null-safe. */
    function format_time(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $r = app_regional();

        return \Illuminate\Support\Carbon::parse($value)->timezone($r['timezone'])->format($r['time_format']);
    }
}

if (!function_exists('format_datetime')) {
    /** Format a stored (UTC) timestamp as date + time in the company's zone. */
    function format_datetime(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $r = app_regional();

        return \Illuminate\Support\Carbon::parse($value)
            ->timezone($r['timezone'])
            ->format($r['date_format'].' '.$r['time_format']);
    }
}

if (!function_exists('app_receipt')) {
    /**
     * The company's receipt-template settings — paper size, what to show,
     * and the editable header/footer/return-policy text. Consumed by the
     * receipt printer (not yet built). Container-cached per request like
     * {@see app_currency()}; falls back to sensible defaults before the
     * install/migration has run. Call `forget_app_receipt()` after a
     * change to re-read in-request.
     *
     * @return array{
     *   paper_size: string, show_logo: bool, show_customer: bool, show_cashier: bool,
     *   show_tax_breakdown: bool, show_barcode: bool, show_qr: bool,
     *   header: ?string, footer: ?string, return_policy: ?string
     * }
     */
    function app_receipt(): array
    {
        $app = app();
        if ($app->bound('pos.app_receipt')) {
            return $app->make('pos.app_receipt');
        }

        $resolved = [
            'paper_size'         => '80mm',
            'show_logo'          => true,
            'show_customer'      => true,
            'show_cashier'       => true,
            'show_tax_breakdown' => true,
            'show_barcode'       => true,
            'show_qr'            => false,
            'header'             => null,
            'footer'             => null,
            'return_policy'      => null,
        ];

        try {
            $row = \Illuminate\Support\Facades\DB::table('company')->first([
                'receipt_paper_size', 'receipt_show_logo', 'receipt_show_customer', 'receipt_show_cashier',
                'receipt_show_tax_breakdown', 'receipt_show_barcode', 'receipt_show_qr',
                'receipt_header', 'receipt_footer', 'receipt_return_policy',
            ]);
            if ($row) {
                $resolved = [
                    'paper_size'         => $row->receipt_paper_size ?: $resolved['paper_size'],
                    'show_logo'          => (bool) $row->receipt_show_logo,
                    'show_customer'      => (bool) $row->receipt_show_customer,
                    'show_cashier'       => (bool) $row->receipt_show_cashier,
                    'show_tax_breakdown' => (bool) $row->receipt_show_tax_breakdown,
                    'show_barcode'       => (bool) $row->receipt_show_barcode,
                    'show_qr'            => (bool) $row->receipt_show_qr,
                    'header'             => $row->receipt_header,
                    'footer'             => $row->receipt_footer,
                    'return_policy'      => $row->receipt_return_policy,
                ];
            }
        } catch (\Throwable $e) {
            // table not ready yet — keep defaults
        }

        $app->instance('pos.app_receipt', $resolved);
        return $resolved;
    }
}

if (!function_exists('forget_app_receipt')) {
    function forget_app_receipt(): void
    {
        if (app()->bound('pos.app_receipt')) {
            app()->forgetInstance('pos.app_receipt');
        }
    }
}

if (!function_exists('paginate_per_page')) {
    /**
     * Resolve the `?per_page=N` query string against the system-wide
     * allowed sizes (same set the client-side data-table offers). Falls
     * back to the supplied default when missing or invalid, so every
     * paginated controller across the admin can do:
     *
     *   ->paginate(paginate_per_page())
     *
     * and pick up the user's choice without per-controller validation.
     *
     * Allowed sizes are kept in sync with the dropdown in
     * `resources/views/vendor/pagination/system.blade.php` AND the
     * client-side `composeDataTable` defaults.
     */
    function paginate_per_page(int $default = 25, array $allowed = [10, 25, 50, 100]): int
    {
        $requested = (int) request()?->query('per_page');
        return in_array($requested, $allowed, true) ? $requested : $default;
    }
}

if (!function_exists('pos_is_demo')) {
    /**
     * Whether this install is running as the public demo. Environment-driven
     * (POS_DEMO_MODE → config('pos.demo.enabled')) so the same code runs as
     * either the live demo or a real customer install.
     *
     * Demo installs are read-only for sensitive config (payment gateway keys,
     * SMTP credentials, license re-checks) and their data is wiped back to a
     * clean baseline nightly — see App\Actions\Demo\ResetDemoData.
     */
    function pos_is_demo(): bool
    {
        return (bool) config('pos.demo.enabled', false);
    }
}

if (!function_exists('mask_secret')) {
    /**
     * Mask a secret for display — keep the last few characters, bullet the
     * rest. Used to show payment/SMTP credentials on the demo without leaking
     * them (the same last-4 convention the License page already uses).
     */
    function mask_secret(?string $value, int $visible = 4): string
    {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }

        $len = strlen($value);
        if ($len <= $visible) {
            return str_repeat('•', $len);
        }

        return str_repeat('•', max(4, $len - $visible)).substr($value, -$visible);
    }
}

if (!function_exists('demo_mask_phone')) {
    /**
     * Mask a phone number for display on the public demo so visitors can't
     * harvest real-looking contact data from the seeded records. Keeps a
     * leading "+" and the last two digits, bullets the rest. Returns the
     * number unchanged on real installs (pos_is_demo() === false).
     */
    function demo_mask_phone(?string $phone): string
    {
        $phone = (string) $phone;
        if (! pos_is_demo() || $phone === '') {
            return $phone;
        }

        $plus   = str_starts_with(trim($phone), '+') ? '+' : '';
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if ($digits === '') {
            return $phone;
        }
        if (strlen($digits) <= 3) {
            return $plus.str_repeat('•', strlen($digits));
        }

        return $plus.str_repeat('•', max(4, strlen($digits) - 2)).substr($digits, -2);
    }
}

if (!function_exists('demo_mask_email')) {
    /**
     * Mask an email address for display on the public demo so visitors can't
     * harvest real-looking contact data from the seeded records. Keeps the
     * first character of the local part and the whole domain (so the address
     * still reads as an email and stays useful as demo data), bullets the
     * rest. Returns the address unchanged on real installs.
     *
     * Display only — never mask on a write path. Uniqueness validation, mail
     * recipients and login lookups must always see the real value, which is
     * why this is a separate `display_email` accessor and not an override of
     * the `email` attribute itself.
     */
    function demo_mask_email(?string $email): string
    {
        $email = (string) $email;
        if (! pos_is_demo() || $email === '') {
            return $email;
        }

        $at = strrpos($email, '@');
        if ($at === false || $at === 0) {
            // Not an address shape — bullet it wholesale rather than leak it.
            return str_repeat('•', max(4, mb_strlen($email)));
        }

        $local  = mb_substr($email, 0, $at);
        $domain = mb_substr($email, $at); // includes the "@"

        return mb_substr($local, 0, 1).str_repeat('•', max(4, mb_strlen($local) - 1)).$domain;
    }
}

if (!function_exists('app_backup')) {
    /**
     * The company's backup settings — automatic backup cadence + where
     * backups land + how long they're kept. Read by the scheduler
     * (`routes/console.php`) on every minute tick.
     *
     * @return array{enabled: bool, frequency: string, retention_days: int, target_disk: string, include_uploads: bool}
     */
    function app_backup(): array
    {
        $app = app();
        if ($app->bound('pos.app_backup')) {
            return $app->make('pos.app_backup');
        }

        $resolved = [
            'enabled'         => false,
            'frequency'       => 'weekly',
            'retention_days'  => 30,
            'target_disk'     => 'local',
            'include_uploads' => true,
            'email_enabled'   => false,
        ];

        try {
            $row = \Illuminate\Support\Facades\DB::table('company')->first([
                'backup_enabled', 'backup_frequency', 'backup_retention_days',
                'backup_target_disk', 'backup_include_uploads', 'backup_email_enabled',
            ]);
            if ($row) {
                $resolved = [
                    'enabled'         => (bool) $row->backup_enabled,
                    'frequency'       => $row->backup_frequency ?: $resolved['frequency'],
                    'retention_days'  => (int) ($row->backup_retention_days ?: $resolved['retention_days']),
                    'target_disk'     => $row->backup_target_disk ?: $resolved['target_disk'],
                    'include_uploads' => (bool) $row->backup_include_uploads,
                    'email_enabled'   => (bool) ($row->backup_email_enabled ?? false),
                ];
            }
        } catch (\Throwable $e) {
            // table not ready yet — keep defaults
        }

        $app->instance('pos.app_backup', $resolved);
        return $resolved;
    }
}

if (!function_exists('forget_app_backup')) {
    function forget_app_backup(): void
    {
        if (app()->bound('pos.app_backup')) {
            app()->forgetInstance('pos.app_backup');
        }
    }
}

if (!function_exists('app_updates')) {
    /**
     * The company's updater preferences — read by the scheduler
     * (`routes/console.php`) on every minute tick to decide whether the
     * daily feed check should run, and by the Updates settings page.
     *
     * @return array{auto_check: bool, auto_install: bool, channel: string}
     */
    function app_updates(): array
    {
        $app = app();
        if ($app->bound('pos.app_updates')) {
            return $app->make('pos.app_updates');
        }

        $resolved = [
            'auto_check'   => true,
            'auto_install' => false,
            'channel'      => (string) config('pos.updater.channel', 'stable'),
        ];

        try {
            $row = \Illuminate\Support\Facades\DB::table('company')->first([
                'update_auto_check', 'update_auto_install', 'update_channel',
            ]);
            if ($row) {
                $resolved = [
                    'auto_check'   => (bool) $row->update_auto_check,
                    'auto_install' => (bool) $row->update_auto_install,
                    'channel'      => $row->update_channel ?: $resolved['channel'],
                ];
            }
        } catch (\Throwable $e) {
            // table/columns not ready yet — keep defaults
        }

        $app->instance('pos.app_updates', $resolved);
        return $resolved;
    }
}

if (!function_exists('forget_app_updates')) {
    function forget_app_updates(): void
    {
        if (app()->bound('pos.app_updates')) {
            app()->forgetInstance('pos.app_updates');
        }
    }
}

if (!function_exists('app_license')) {
    /**
     * Cached license state, read from the company row (see installer §6).
     * Mirrors app_updates(): resolved once per request, defaults to an
     * "unverified" license when the columns/table aren't ready yet. The
     * license KEY is never returned here — it lives in .env (LICENSE_KEY).
     *
     * @return array{status: string, type: ?string, buyer_name: ?string, buyer_email: ?string, expires_at: ?string, support_until: ?string, last_checked_at: ?string, last_error: ?string}
     */
    function app_license(): array
    {
        $app = app();
        if ($app->bound('pos.app_license')) {
            return $app->make('pos.app_license');
        }

        $resolved = [
            'status'          => 'unverified',
            'type'            => null,
            'buyer_name'      => null,
            'buyer_email'     => null,
            'expires_at'      => null,
            'support_until'   => null,
            'last_checked_at' => null,
            'last_error'      => null,
        ];

        try {
            $row = \Illuminate\Support\Facades\DB::table('company')->first([
                'license_status', 'license_type', 'license_buyer_name', 'license_buyer_email',
                'license_expires_at', 'license_support_until', 'license_last_checked_at', 'license_last_error',
            ]);
            if ($row) {
                $resolved = [
                    'status'          => $row->license_status ?: 'unverified',
                    'type'            => $row->license_type,
                    'buyer_name'      => $row->license_buyer_name,
                    'buyer_email'     => $row->license_buyer_email,
                    'expires_at'      => $row->license_expires_at,
                    'support_until'   => $row->license_support_until,
                    'last_checked_at' => $row->license_last_checked_at,
                    'last_error'      => $row->license_last_error,
                ];
            }
        } catch (\Throwable $e) {
            // table/columns not ready yet — keep defaults
        }

        $app->instance('pos.app_license', $resolved);
        return $resolved;
    }
}

if (!function_exists('app_icon_url')) {
    /**
     * A square PNG of the company's app logo at `$size`, for the browser's
     * apple-touch-icon. Returns the generic shipped icon when a branded one
     * can't be rendered (no logo uploaded, GD missing, corrupt upload).
     *
     * Resolved once per request; the generator only touches the disk when the
     * icon isn't already cached under `storage/app/public/pwa-icons`.
     */
    function app_icon_url(int $size = 192): string
    {
        $app = app();

        if (! $app->bound('pos.app_icons')) {
            $urls = null;
            try {
                $urls = $app->make(\App\Services\Pwa\AppIconGenerator::class)
                    ->urlsFor(\App\Models\Company::current());
            } catch (\Throwable $e) {
                // Un-migrated / unreadable install — fall back to the placeholder.
            }
            $app->instance('pos.app_icons', $urls ?? []);
        }

        $urls = $app->make('pos.app_icons');

        return $urls['any'][$size] ?? "/icons/icon-{$size}.png";
    }
}

if (!function_exists('forget_app_license')) {
    function forget_app_license(): void
    {
        if (app()->bound('pos.app_license')) {
            app()->forgetInstance('pos.app_license');
        }
    }
}

if (!function_exists('forget_app_features')) {
    /** Bump the feature-flag cache version — call whenever a license re-check persists new entitlements. */
    function forget_app_features(): void
    {
        \Illuminate\Support\Facades\Cache::forever('features.version', (int) \Illuminate\Support\Facades\Cache::get('features.version', 1) + 1);
    }
}

if (!function_exists('company_features')) {
    /**
     * The current company's SaaS feature-flag map, cached the same
     * version-counter way {@see App\Models\Concerns\HasPermissions} caches
     * permissions — shared-hosting friendly, no cache tags.
     *
     * @return array<string,bool>
     */
    function company_features(): array
    {
        $version = (int) \Illuminate\Support\Facades\Cache::get('features.version', 1);

        return \Illuminate\Support\Facades\Cache::remember(
            "features:v{$version}",
            now()->addMinutes(5),
            fn () => (array) (\App\Models\Company::current()?->features ?? []),
        );
    }
}

if (!function_exists('feature_enabled')) {
    /**
     * Is the given plan-gated feature enabled for this company? Missing
     * from the map (legacy install with no SaaS entitlements persisted
     * yet, or a self-hosted non-SaaS build) defaults to enabled, so
     * feature gating only ever restricts once a license server actively
     * says otherwise — it never locks an existing self-hosted customer out.
     */
    function feature_enabled(string $key): bool
    {
        return (bool) (company_features()[$key] ?? true);
    }
}

if (!function_exists('current_store_id')) {
    /**
     * The store the current request is operating against.
     *
     * Driven purely by `session('active_store_id')`, which the
     * {@see App\Http\Middleware\EnsureStoreSelected} middleware sets on
     * every admin request, and which the store switcher updates.
     *
     * Returns null when there is no started session (seeding, console
     * commands, the installer) — that's the signal the StoreScoped trait
     * uses to skip its global filter so global tooling sees every row.
     */
    function current_store_id(): ?int
    {
        if (! app()->bound('session') || ! app('session')->isStarted()) {
            return null;
        }

        $id = session('active_store_id');

        return $id ? (int) $id : null;
    }
}

if (!function_exists('default_store_id')) {
    /**
     * The company's default store id — the single store flagged
     * `is_default`. Used as the fallback for price resolution when a
     * product has no store-specific price, and as a sane landing store.
     * Null only before any store exists.
     */
    function default_store_id(): ?int
    {
        return \App\Models\Store::query()->where('is_default', true)->value('id');
    }
}

if (!function_exists('current_store')) {
    /**
     * The active Store model, request-cached. Null when no store is
     * selected (see {@see current_store_id()}).
     */
    function current_store(): ?\App\Models\Store
    {
        $id = current_store_id();
        if (! $id) {
            return null;
        }

        $app = app();
        $key = 'pos.current_store';
        if ($app->bound($key)) {
            $cached = $app->make($key);
            if ($cached && $cached->id === $id) {
                return $cached;
            }
        }

        $store = \App\Models\Store::find($id);
        $app->instance($key, $store);

        return $store;
    }
}

if (!function_exists('enforce_store_access')) {
    /**
     * Clamp a store-filter value to what the signed-in user may actually
     * see. Central guard for every list/report screen that accepts a
     * `?store_id=` filter, so a restricted user can neither default into
     * nor hand-craft a URL to another store's data.
     *
     *   - Super admins (and unauthenticated console callers): trusted —
     *     the value passes through unchanged, so a null keeps the
     *     cross-store "all stores" aggregate they're entitled to.
     *   - Everyone else: an explicit, accessible pick is honoured (a
     *     multi-store user can switch via the filter); anything else —
     *     null, or a store they can't reach — falls back to the active
     *     store, never "all".
     */
    function enforce_store_access(?int $storeId): ?int
    {
        $user = auth()->user();
        if (! $user || $user->is_super_admin) {
            return $storeId;
        }

        if ($storeId && $user->canAccessStore($storeId)) {
            return $storeId;
        }

        return current_store_id() ?: ($user->accessibleStoreIds()[0] ?? null);
    }
}

if (!function_exists('accessible_stores')) {
    /**
     * The stores the signed-in user may pick in a filter dropdown — their
     * assigned stores, or every active store for a super admin. Empty for
     * guests. Pair with {@see enforce_store_access()} which guards the
     * actual query.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Store>
     */
    function accessible_stores(): \Illuminate\Support\Collection
    {
        return auth()->user()?->accessibleStores() ?? collect();
    }
}

if (!function_exists('current_terminal_id')) {
    /**
     * The checkout terminal the current request is bound to, taken from
     * the long-lived `pos_terminal_id` cookie. Null when no terminal has
     * been chosen yet — the hardware-config slice surfaces a picker on
     * the cashier screen to set it.
     *
     * The id is only honoured when the terminal exists, is active, and
     * belongs to the active store, so a stale cookie (store switched,
     * terminal deleted) silently falls back to "none".
     */
    function current_terminal_id(): ?int
    {
        return current_terminal()?->id;
    }
}

if (!function_exists('current_terminal')) {
    /**
     * The active Terminal model, request-cached. Null when no valid
     * terminal is selected for the current store (see
     * {@see current_terminal_id()}).
     */
    function current_terminal(): ?\App\Models\Terminal
    {
        if (! request()->hasCookie('pos_terminal_id')) {
            return null;
        }

        $id      = (int) request()->cookie('pos_terminal_id');
        $storeId = current_store_id();
        if (! $id || ! $storeId) {
            return null;
        }

        $app = app();
        $key = 'pos.current_terminal';
        if ($app->bound($key)) {
            $cached = $app->make($key);
            if ($cached && $cached->id === $id) {
                return $cached;
            }
        }

        $terminal = \App\Models\Terminal::query()
            ->whereKey($id)
            ->where('store_id', $storeId)
            ->where('is_active', true)
            ->first();

        $app->instance($key, $terminal);

        return $terminal;
    }
}

if (!function_exists('app_currency')) {
    /**
     * Resolve the company's base currency + its display format from the
     * `currencies` row pointed at by `company.base_currency_code`.
     *
     * Returns:
     *   [
     *     'code'                => 'INR',
     *     'symbol'              => '₹',
     *     'symbol_first'        => true,    // placement: before vs after
     *     'decimals'            => 2,
     *     'thousands_separator' => ',',
     *     'decimal_separator'   => '.',
     *   ]
     *
     * Cached on the container (so it's recomputed per request and reset
     * cleanly between tests — a function `static` would leak across the
     * whole PHP process). Falls back to a USD-style default when the
     * installer hasn't run yet or the row is missing, so pages keep
     * rendering instead of crashing the formatter. Call
     * `forget_app_currency()` after changing the setting in-request.
     */
    function app_currency(): array
    {
        $app = app();
        if ($app->bound('pos.app_currency')) {
            return $app->make('pos.app_currency');
        }

        $default = [
            'code'                => 'USD',
            'symbol'              => '$',
            'symbol_first'        => true,
            'decimals'            => 2,
            'thousands_separator' => ',',
            'decimal_separator'   => '.',
        ];

        $resolved = $default;
        try {
            $code = \Illuminate\Support\Facades\DB::table('company')->value('base_currency_code');
            if ($code) {
                $row = \Illuminate\Support\Facades\DB::table('currencies')
                    ->where('code', $code)
                    ->first(['code', 'symbol', 'symbol_first', 'decimals', 'thousands_separator', 'decimal_separator']);
                if ($row) {
                    $resolved = [
                        'code'                => (string) $row->code,
                        'symbol'              => (string) $row->symbol,
                        'symbol_first'        => (bool) $row->symbol_first,
                        'decimals'            => (int) $row->decimals,
                        'thousands_separator' => (string) $row->thousands_separator,
                        'decimal_separator'   => (string) ($row->decimal_separator ?: '.'),
                    ];
                }
            }
        } catch (\Throwable $e) {
            $resolved = $default;
        }

        $app->instance('pos.app_currency', $resolved);
        return $resolved;
    }
}

if (!function_exists('forget_app_currency')) {
    /** Drop the cached currency so the next app_currency() re-reads it. */
    function forget_app_currency(): void
    {
        if (app()->bound('pos.app_currency')) {
            app()->forgetInstance('pos.app_currency');
        }
    }
}

if (!function_exists('currency_symbol')) {
    /** Convenience wrapper — just the symbol glyph (₹, $, €, …). */
    function currency_symbol(): string
    {
        return app_currency()['symbol'];
    }
}

if (!function_exists('currency_meta')) {
    /**
     * Look up display metadata for any currency code from the
     * `currencies` table. Same shape as {@see app_currency()} but
     * for an arbitrary code — used by purchases / sales pages that
     * need to render in the document's currency, not the system one.
     *
     * Per-request cached. Falls back to `app_currency()` when the
     * code is unknown so a stale or junk code still renders rather
     * than crashing the formatter.
     */
    function currency_meta(string $code): array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return app_currency();
        }
        $app = app();
        $key = 'pos.currency.'.$code;
        if ($app->bound($key)) {
            return $app->make($key);
        }
        try {
            $row = \Illuminate\Support\Facades\DB::table('currencies')
                ->where('code', $code)
                ->first(['code', 'symbol', 'symbol_first', 'decimals', 'thousands_separator', 'decimal_separator']);
            if ($row) {
                $resolved = [
                    'code'                => (string) $row->code,
                    'symbol'              => (string) $row->symbol,
                    'symbol_first'        => (bool) $row->symbol_first,
                    'decimals'            => (int) $row->decimals,
                    'thousands_separator' => (string) $row->thousands_separator,
                    'decimal_separator'   => (string) ($row->decimal_separator ?: '.'),
                ];
                $app->instance($key, $resolved);
                return $resolved;
            }
        } catch (\Throwable $e) {
            // Fall through to app_currency()
        }
        return app_currency();
    }
}

if (!function_exists('format_money')) {
    /**
     * Format a numeric amount with the company's currency, honoring its
     * configured decimals, thousands/decimal separators, and symbol
     * placement:
     *   `$1,234.56`  ·  `1.234,56 €`  ·  `₹1,23,456.00`*  ·  `¥1235`
     *
     * (*grouping style beyond simple 3-digit isn't modelled yet — see
     * the deferred currency notes.)
     *
     * `$decimals` overrides the currency's default precision when passed
     * (e.g. unit-cost displays that want 4 places). The `DECIMAL(15,4)`
     * columns keep 4 places for arithmetic; display usually rounds.
     *
     * `$currencyCode` overrides the system currency — used by
     * purchase/sale documents that carry their own `currency_code`
     * and should render in it instead of the company default.
     */
    function format_money(int|float|string|null $amount, ?int $decimals = null, ?string $currencyCode = null): string
    {
        $c   = $currencyCode ? currency_meta($currencyCode) : app_currency();
        $dec = $decimals ?? $c['decimals'];
        $n   = number_format(
            (float) ($amount ?? 0),
            $dec,
            $c['decimal_separator'] ?: '.',
            $c['thousands_separator'],
        );
        return $c['symbol_first'] ? $c['symbol'].$n : $n.' '.$c['symbol'];
    }
}

if (!function_exists('notify_admins')) {
    /**
     * Send a database notification to the relevant audience — the topbar bell.
     *
     * Super-admins ALWAYS receive it (they see everything). When a `$permission`
     * is given, every active user who holds that permission in any store also
     * receives it — so e.g. a low-stock bell reaches stock keepers, not just
     * the owner. Recipients are de-duplicated. Safe to call from anywhere; a
     * no-op when nobody qualifies.
     */
    function notify_admins(string $key, string $title, string $message, string $icon = 'bell', ?string $url = null, ?string $permission = null): void
    {
        $query = \App\Models\User::query()->where('is_active', true);

        if ($permission !== null && $permission !== '') {
            $permitted = \Illuminate\Support\Facades\DB::table('store_user')
                ->join('role_permission', 'role_permission.role_id', '=', 'store_user.role_id')
                ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
                ->where('permissions.key', $permission)
                ->pluck('store_user.user_id');

            $query->where(fn ($q) => $q->where('is_super_admin', true)->orWhereIn('id', $permitted));
        } else {
            $query->where('is_super_admin', true);
        }

        $recipients = $query->get();
        if ($recipients->isEmpty()) {
            return;
        }

        \Illuminate\Support\Facades\Notification::send($recipients, new \App\Notifications\SystemNotification([
            'key'     => $key,
            'title'   => $title,
            'message' => $message,
            'icon'    => $icon,
            'url'     => $url,
        ]));
    }
}

if (!function_exists('admins_have_unread_notification')) {
    /**
     * Whether an UNREAD notification with this payload key already exists —
     * used to dedupe recurring triggers (a daily update check or low-stock
     * sweep shouldn't pile up identical bells).
     */
    function admins_have_unread_notification(string $key): bool
    {
        try {
            // No LIKE-escaping: our keys contain no % or \, and an underscore
            // matches itself as a single-char wildcard — which keeps this
            // portable across MySQL and SQLite (the latter has no default
            // backslash ESCAPE). Keys are app-controlled, so the wildcard
            // can't cause a false dedupe in practice.
            return \Illuminate\Notifications\DatabaseNotification::query()
                ->whereNull('read_at')
                ->where('data', 'like', '%"key":"'.$key.'"%')
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
