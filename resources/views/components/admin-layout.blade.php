<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      dir="{{ ($currentLanguage->direction ?? null) ?: (in_array(app()->getLocale(), ['ar', 'he', 'ur', 'fa']) ? 'rtl' : 'ltr') }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.google-analytics')
    {{-- Server-side command-palette index (read by admin.js to populate $store.cmd). --}}
    <meta name="pos-cmd-index" content="{{ $commandIndex() }}">
    {{-- Current-user permission set for Alpine's $user.can() (UI gating only). --}}
    <meta name="pos-user" content="{{ $userContext() }}">
    {{-- Company default theme — read by admin.js when the user has no saved choice. --}}
    <meta name="pos-theme-default" content="{{ $themeDefault }}">
    {{-- Default country for phone inputs (intl-tel-input). Falls back to 'in' (lowercase as the lib expects). --}}
    <meta name="pos-default-country" content="{{ strtolower(\App\Models\Company::current()?->country_code ?: \App\Models\Store::query()->value('country_code') ?: 'in') }}">
    {{-- Currency registry — fed to `resources/js/lib/format-money.js`
         so JS-side amount displays match the same rules as PHP's
         `format_money()` (symbol position, decimals, separators). --}}
    @php
        $__posCurrencies = \Illuminate\Support\Facades\DB::table('currencies')
            ->where('is_active', true)
            ->get(['code','symbol','symbol_first','decimals','thousands_separator','decimal_separator'])
            ->map(fn ($c) => [
                'code'                => $c->code,
                'symbol'              => $c->symbol,
                'symbol_first'        => (bool) $c->symbol_first,
                'decimals'            => (int) $c->decimals,
                'thousands_separator' => (string) $c->thousands_separator,
                'decimal_separator'   => (string) ($c->decimal_separator ?: '.'),
            ])->values();
    @endphp
    <meta name="pos-currencies" content="{{ $__posCurrencies->toJson() }}">
    <meta name="pos-base-currency" content="{{ app_currency()['code'] }}">
    {{-- Active terminal's receipt-printer config (mode/descriptor/drawer-pin)
         for the hardware print bridge + drawer kick. {} when no terminal
         is selected or it has no printer configured yet. --}}
    <meta name="pos-terminal-printer" content="{{ json_encode(data_get(current_terminal()?->default_printer_config, 'receipt_printer') ?: new \stdClass) }}">
    @if ($faviconUrl)
        <link rel="icon" href="{{ $faviconUrl }}">
    @endif
    {{-- PWA — makes the back office installable. The service worker (scope '/')
         is registered by admin.js (startPwa); it's the same SW the cashier
         uses. The Install button lives in the topbar (x-data="pwaInstall"). --}}
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="{{ $brandColor ?: '#3b82f6' }}">
    {{-- The store's own logo when we could render one, else the shipped icon. --}}
    <link rel="apple-touch-icon" href="{{ app_icon_url(192) }}">
    <title>{{ $title ?: __('admin.app_title') }} — {{ $appName }}</title>

    @vite(['resources/css/admin.css', 'resources/js/admin.js'])

    @if ($brandColor || $brandTextColor)
        {{-- Runtime brand-color override — recolors the accent system from one
             configured hex (plus the on-accent text color). Inline by necessity
             (per-install data, not a static sheet). Placed AFTER @vite so the
             cascade lets it win over the default :root tokens in tokens.css. --}}
        <style>
            :root {
                @if ($brandColor)
                --accent: {{ $brandColor }};
                --accent-hover: color-mix(in srgb, {{ $brandColor }} 86%, #000);
                --accent-soft: color-mix(in srgb, {{ $brandColor }} 12%, transparent);
                --accent-ring: color-mix(in srgb, {{ $brandColor }} 30%, transparent);
                @endif
                --accent-fg: {{ $brandTextColor ?: '#fff' }};
            }
        </style>
    @endif

    {{--
        Server-side flash → client toasts. Anything in session('success'),
        session('error'), session('warning'), session('info') or the
        validator errors bag is pushed into the $store.toasts list on
        Alpine init.
    --}}
    @php
        $posFlash = [];
        foreach (['success', 'error', 'warning', 'info'] as $level) {
            if (session()->has($level)) {
                $posFlash[] = ['type' => $level, 'message' => (string) session($level)];
            }
        }
        if ($errors->any()) {
            // Toast every validation error, not just the first — when
            // a form has multiple required fields and the user submits
            // it empty, they need to see all the gaps in one glance.
            // `unique` because hasMany-via-Form-Requests can sometimes
            // surface duplicate messages across rule combinations.
            $allErrors = collect($errors->all())->unique()->values()->all();
            $posFlash[] = [
                'type'     => 'error',
                'title'    => __('admin.toast.validation_title'),
                'messages' => $allErrors,
            ];
        }
    @endphp
    @if (!empty($posFlash))
        <script>window.POS_FLASH = {!! json_encode($posFlash, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!};</script>
    @endif
</head>
<body class="font-sans antialiased h-screen overflow-hidden text-[14px] fg-primary"
      x-data
      x-init="$store.ui.init(); $store.sidebar.init()"
      x-cloak>

    <div class="admin-layout">
        <x-admin.sidebar
            :nav="$nav"
            :app-logo="$appLogoUrl"
            :app-logo-dark="$appLogoDarkUrl"
            :app-logo-half="$appLogoHalfUrl"
            :app-logo-half-dark="$appLogoHalfDarkUrl"
            :app-name="$appName" />

        <div class="admin-main">
            <x-admin.topbar :crumbs="$crumbs" />

            <main class="page">
                <x-admin.license-banner />
                {{ $slot }}
            </main>

            {{-- Rendered unconditionally: the Documentation link must always be
                 reachable, so only the company's optional footer text is
                 conditional. --}}
            <footer class="admin-footer">
                @if ($footerText)
                    <span class="admin-footer-text">{!! nl2br(e($footerText)) !!}</span>
                @endif
                <a href="{{ route('documentation') }}" class="admin-footer-link" target="_blank" rel="noopener">
                    <x-icon name="book-open" class="w-3.5 h-3.5" />
                    {{ __('admin.shell.documentation') }}
                </a>
            </footer>
        </div>
    </div>

    <div class="sidebar-scrim" x-show="$store.sidebar.mobileOpen" x-transition.opacity @click="$store.sidebar.closeMobile()"></div>

    <x-admin.command-palette />
    <x-admin.toasts />
    <x-admin.confirm />
    <x-admin.demo-buy-now />
</body>
</html>
