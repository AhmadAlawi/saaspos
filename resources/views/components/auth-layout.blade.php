<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      dir="{{ in_array(app()->getLocale(), ['ar', 'he', 'ur', 'fa']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.google-analytics')
    @if ($faviconUrl)
        <link rel="icon" href="{{ $faviconUrl }}">
    @endif
    <title>{{ $title ?: __('auth.app_title') }} — {{ $appName }}</title>

    @vite(['resources/css/auth.css', 'resources/js/app.js'])

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
</head>
<body class="font-sans antialiased fg-primary"
      x-data
      x-init="$store.ui.init()">

    <div class="auth-shell">
        <header class="auth-topbar">
            <button type="button"
                    class="icon-btn"
                    aria-label="{{ __('auth.toggle_theme') }}"
                    @click="$store.ui.toggle()">
                <span class="dark:hidden"><x-icon name="moon" class="w-[18px] h-[18px]" /></span>
                <span class="hidden dark:inline-flex"><x-icon name="sun" class="w-[18px] h-[18px]" /></span>
            </button>
        </header>

        <main class="auth-main">
            {{ $slot }}
        </main>

        @if ($footerText)
            <footer class="auth-footer">
                <span>{!! nl2br(e($footerText)) !!}</span>
            </footer>
        @endif
    </div>
</body>
</html>
