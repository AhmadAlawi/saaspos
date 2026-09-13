<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      dir="{{ ($currentLanguage->direction ?? null) ?: (in_array(app()->getLocale(), ['ar', 'he', 'ur', 'fa']) ? 'rtl' : 'ltr') }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.google-analytics')
    <meta name="pos-user" content="{{ $userContext() }}">
    <meta name="pos-theme-default" content="{{ $themeDefault }}">
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

    {{-- Slice 3 PWA + Service Worker —
         pwa-installer.js registers /service-worker.js once the cashier
         page boots, enabling: page-loads-while-offline, "Add to home
         screen" install flow, and SW update prompts. --}}
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="{{ $brandColor ?: '#214944' }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap" rel="stylesheet">

    @if ($faviconUrl)
        <link rel="icon" href="{{ $faviconUrl }}">
    @endif
    <title>{{ $title ?: __('cashier.title') }} — {{ $appName }}</title>

    @vite(['resources/css/admin.css', 'resources/js/admin.js'])

    @if ($brandColor || $brandTextColor)
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

    {{-- Server-side flash → client toasts (same as admin-layout). --}}
    @php
        $posFlash = [];
        foreach (['success', 'error', 'warning', 'info'] as $level) {
            if (session()->has($level)) {
                $posFlash[] = ['type' => $level, 'message' => (string) session($level)];
            }
        }
        if ($errors->any()) {
            $allErrors = collect($errors->all())->unique()->values()->all();
            $posFlash[] = ['type' => 'error', 'title' => __('admin.toast.validation_title'), 'messages' => $allErrors];
        }
    @endphp
    @if (!empty($posFlash))
        <script>window.POS_FLASH = {!! json_encode($posFlash, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!};</script>
    @endif
</head>
<body class="font-sans antialiased fg-primary cashier-body"
      x-data
      x-init="$store.ui.init()"
      x-cloak>

    <div class="cashier-shell">
        {{-- Brand strip — thin, focused-task chrome. Brand + store name on
             the left, cashier menu on the right. Nothing else. --}}
        <header class="cashier-topbar">
            <a href="{{ route('admin.dashboard') }}" class="cashier-brand" aria-label="{{ $appName }}">
                @if ($appLogoUrl || $appLogoDarkUrl)
                    @if ($appLogoUrl)
                        <img src="{{ $appLogoUrl }}" alt="" class="cashier-brand-logo{{ $appLogoDarkUrl ? ' dark:hidden' : '' }}">
                    @endif
                    @if ($appLogoDarkUrl)
                        <img src="{{ $appLogoDarkUrl }}" alt="" class="cashier-brand-logo hidden dark:block">
                    @endif
                @else
                    <span class="cashier-brand-mark">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($appName, 0, 1)) }}</span>
                    <span class="cashier-brand-name">{{ $appName }}</span>
                @endif
            </a>

            <div class="cashier-topbar-end">
                {{-- Same component the admin topbar uses. Self-resolves
                     the active store from session; switching POSTs to
                     /admin/stores/{store}/switch and bounces back. --}}
                <x-admin.store-switcher />

                <x-lang-toggle button-class="cashier-icon-btn" />

                <button type="button" class="cashier-icon-btn" aria-label="{{ __('admin.shell.toggle_theme') ?? 'Theme' }}" @click="$store.ui.toggle()">
                    <span class="dark:hidden"><x-icon name="moon" class="w-[18px] h-[18px]" /></span>
                    <span class="hidden dark:inline-flex"><x-icon name="sun" class="w-[18px] h-[18px]" /></span>
                </button>

                <a href="{{ route('admin.dashboard') }}" class="cashier-icon-btn" aria-label="{{ __('cashier.exit') }}" title="{{ __('cashier.exit') }}">
                    <x-icon name="back" class="w-[18px] h-[18px]" />
                </a>

                <span class="cashier-user">
                    <span class="cashier-user-name">{{ auth()->user()?->name ?? '—' }}</span>
                </span>

                {{-- Warn before logging out with an open shift — the shift
                     stays open server-side and a manager would have to
                     force-close it otherwise. --}}
                @php
                    $__openShift = auth()->check()
                        ? \App\Models\Shift::openForCashier((int) (current_store_id() ?: 0), (int) auth()->id())
                        : null;
                @endphp
                <form method="POST" action="{{ route('logout') }}" class="contents">
                    @csrf
                    <button type="submit" class="cashier-icon-btn" aria-label="{{ __('admin.shell.logout') ?? 'Logout' }}" title="{{ __('admin.shell.logout') ?? 'Logout' }}"
                        @if ($__openShift)
                        @click.prevent="
                            const form = $el.closest('form');
                            $store.confirm.show({
                                title: @js(__('shifts.logout_warning.title')),
                                message: @js(__('shifts.logout_warning.message', ['number' => '#'.$__openShift->id])),
                                intent: 'warning',
                                confirmLabel: @js(__('shifts.logout_warning.logout_anyway')),
                                cancelLabel: @js(__('shifts.actions.cancel')),
                                onConfirm: () => form.submit(),
                            })
                        "
                        @endif>
                        <x-icon name="logout" class="w-[18px] h-[18px]" />
                    </button>
                </form>
            </div>
        </header>

        {{-- Main working area — the slot takes the rest of the viewport. --}}
        <main class="cashier-main">
            {{ $slot }}
        </main>
    </div>

    {{-- Toast / confirm stores are mounted by admin.js's boot path. --}}
    <x-admin.toasts />
    <x-admin.confirm />
</body>
</html>
