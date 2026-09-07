<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      dir="{{ in_array(app()->getLocale(), ['ar', 'he', 'ur', 'fa']) ? 'rtl' : 'ltr' }}"
      class="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.google-analytics')
    <title>{{ __('installer.title') }} — {{ config('pos.name') }}</title>

    {{-- Brand favicon. Drop yours at public/brand/favicon.png (or .ico);
         falls back to the shipped public/favicon.ico until you do. --}}
    @php
        $installerFavicon = collect(['brand/favicon.png', 'brand/favicon.ico', 'favicon.ico'])
            ->first(fn ($p) => file_exists(public_path($p))) ?: 'favicon.ico';
    @endphp
    <link rel="icon" href="{{ asset($installerFavicon) }}">
    <link rel="apple-touch-icon" href="{{ asset($installerFavicon) }}">

    <script>
        // Default is light. Honour only the explicit user toggle (persists in localStorage).
        (function () {
            try {
                if (localStorage.getItem('pos_theme') === 'dark') {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {}
        })();
    </script>

    @vite(['resources/css/installer.css', 'resources/js/installer.js'])
</head>
<body class="min-h-screen antialiased text-[14px] installer-body"
      x-data="{ helpOpen: false }">
    <div class="mx-auto flex min-h-screen max-w-[720px] flex-col px-4 py-8 sm:px-6 sm:py-12">

        {{-- Header --}}
        @php
            // Brand logo. Drop yours at public/brand/logo.png (or .svg); add
            // public/brand/logo-dark.png for a dark-mode variant. Until a logo
            // exists we fall back to a letter avatar + the product name.
            $installerLogo = collect(['brand/logo.svg', 'brand/logo.png'])
                ->first(fn ($p) => file_exists(public_path($p)));
            $installerLogoDark = collect(['brand/logo-dark.svg', 'brand/logo-dark.png'])
                ->first(fn ($p) => file_exists(public_path($p))) ?: $installerLogo;
        @endphp
        <header class="mb-10 flex items-center justify-between">
            <div class="flex items-center gap-3">
                @if ($installerLogo)
                    {{-- A logo usually carries the wordmark, so we don't repeat
                         the product name beside it. --}}
                    <img src="{{ asset($installerLogo) }}" alt="{{ config('pos.name') }}"
                         class="installer-logo {{ $installerLogoDark !== $installerLogo ? 'dark:hidden' : '' }}">
                    @if ($installerLogoDark !== $installerLogo)
                        <img src="{{ asset($installerLogoDark) }}" alt="{{ config('pos.name') }}"
                             class="installer-logo hidden dark:block">
                    @endif
                @else
                    <div class="flex h-9 w-9 items-center justify-center rounded-[8px] accent-bg font-semibold text-[15px]">
                        {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(config('pos.name'), 0, 1)) }}
                    </div>
                    <div class="leading-tight">
                        <div class="text-[14px] font-semibold fg-primary">{{ config('pos.name') }}</div>
                        <div class="text-[11px] eyebrow m-0 p-0">{{ __('installer.subtitle') }}</div>
                    </div>
                @endif
            </div>

            <button type="button"
                    onclick="(function(){var d=document.documentElement;d.classList.toggle('dark');try{localStorage.setItem('pos_theme', d.classList.contains('dark')?'dark':'light');}catch(e){}})()"
                    class="icon-btn"
                    aria-label="{{ __('installer.toggle_theme') }}">
                {{-- Sun (visible in dark mode) --}}
                <svg class="h-[18px] w-[18px] hidden dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m4.93 19.07 1.41-1.41"/><path d="m17.66 6.34 1.41-1.41"/></svg>
                {{-- Moon (visible in light mode) --}}
                <svg class="h-[18px] w-[18px] dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </button>
        </header>

        {{-- Progress indicator --}}
        @include('installer.partials.progress', ['currentStep' => $currentStep ?? 1])

        {{-- Main card --}}
        <main class="mt-8 flex-1 card p-7">
            @if (session('success'))
                <div class="alert alert-success mb-6">
                    <span class="alert-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="M20 6 9 17l-5-5"/></svg>
                    </span>
                    <div class="alert-body">
                        <div class="alert-title">{{ session('success') }}</div>
                    </div>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger mb-6">
                    <span class="alert-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.86a2 2 0 0 1 3.4 0l8.39 14.6A2 2 0 0 1 20.39 22H3.61a2 2 0 0 1-1.7-3.54z"/></svg>
                    </span>
                    <div class="alert-body">
                        <div class="alert-title">{{ __('installer.error_heading') }}</div>
                        <ul class="alert-msg list-disc ps-5 mt-1 space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            @yield('content')
        </main>

        {{-- Footer --}}
        <footer class="mt-8 text-center text-[11.5px] fg-tertiary">
            {{ __('installer.footer.need_help') }}
            <button type="button" class="font-medium accent hover:underline underline-offset-2" @click="helpOpen = true">
                {{ __('installer.help.open') }}
            </button>
        </footer>
    </div>

    @include('installer.partials.help-drawer')
</body>
</html>
