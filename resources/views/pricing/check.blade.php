<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#f4f5f7" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#090c10" media="(prefers-color-scheme: dark)">
    @include('partials.google-analytics')
    <title>{{ $shopName }} — {{ __('pricing.title') }}</title>
    @if ($logoUrl)
        <link rel="icon" href="{{ $logoUrl }}">
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/js/pricing.js'])
    <style>
        :root {
            --pc-canvas: #f4f5f7;
            --pc-card: #ffffff;
            --pc-inset: #ebecf0;
            --pc-text: #091e42;
            --pc-text-2: #44546f;
            --pc-text-3: #626f86;
            --pc-border: #dcdfe4;
            --pc-accent: #ffb020;
            --pc-accent-strong: #ff9e00;
            --pc-on-accent: #091e42;
            --pc-dark-btn: #0f1626;
            --pc-on-dark-btn: #ffffff;
            --pc-price: #006b47;
            --pc-scan: #0052cc;
            --pc-radius: 1rem;
            --pc-radius-lg: 1.5rem;
        }
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) {
                --pc-canvas: #090c10;
                --pc-card: #161b22;
                --pc-inset: #0d1117;
                --pc-text: #f0f6fc;
                --pc-text-2: #8b949e;
                --pc-text-3: #6e7681;
                --pc-border: #30363d;
                --pc-accent: #ffb84d;
                --pc-accent-strong: #ffa930;
                --pc-on-accent: #091e42;
                --pc-dark-btn: #f0f6fc;
                --pc-on-dark-btn: #091e42;
                --pc-price: #35d29a;
                --pc-scan: #388bfd;
            }
        }
        :root[data-theme="dark"] {
            --pc-canvas: #090c10;
            --pc-card: #161b22;
            --pc-inset: #0d1117;
            --pc-text: #f0f6fc;
            --pc-text-2: #8b949e;
            --pc-text-3: #6e7681;
            --pc-border: #30363d;
            --pc-accent: #ffb84d;
            --pc-accent-strong: #ffa930;
            --pc-on-accent: #091e42;
            --pc-dark-btn: #f0f6fc;
            --pc-on-dark-btn: #091e42;
            --pc-price: #35d29a;
            --pc-scan: #388bfd;
        }

        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            background: var(--pc-canvas);
            color: var(--pc-text);
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
            min-height: 100dvh;
        }
        [hidden] { display: none !important; }

        .pc-shell {
            width: 100%;
            max-width: 30rem;
            margin: 0 auto;
            padding: 1.25rem 1rem calc(2rem + env(safe-area-inset-bottom));
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .pc-top {
            display: flex;
            align-items: center;
            gap: .75rem;
        }
        .pc-brand { display: flex; align-items: center; gap: .625rem; min-width: 0; }
        .pc-logo {
            width: 2.5rem; height: 2.5rem; flex: none;
            border-radius: .75rem;
            background: var(--pc-accent);
            display: flex; align-items: center; justify-content: center;
            color: var(--pc-on-accent);
            overflow: hidden;
        }
        .pc-logo img { width: 100%; height: 100%; object-fit: cover; }
        .pc-brand-text { min-width: 0; }
        .pc-shop {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700; font-size: 1rem; line-height: 1.2;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .pc-shop-sub {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 600; font-size: .6875rem; letter-spacing: .08em;
            text-transform: uppercase; color: var(--pc-text-3);
        }
        .pc-theme-toggle {
            margin-left: auto; flex: none;
            width: 2.5rem; height: 2.5rem;
            border-radius: 999px;
            border: 1px solid var(--pc-border);
            background: var(--pc-card);
            color: var(--pc-text-2);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
        }
        .pc-theme-toggle:active { transform: scale(.95); }
        .pc-theme-toggle svg { width: 1.125rem; height: 1.125rem; }

        .pc-head { text-align: center; }
        .pc-title {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700; letter-spacing: -.02em;
            font-size: 2.25rem; line-height: 1.1; margin: 0;
        }
        .pc-sub { margin: .375rem 0 0; color: var(--pc-text-2); font-size: 1rem; }

        .pc-scan-btn {
            width: 100%;
            min-height: 3.75rem;
            border: none;
            border-radius: var(--pc-radius);
            background: var(--pc-accent);
            color: var(--pc-on-accent);
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700; font-size: 1.125rem;
            display: flex; align-items: center; justify-content: center; gap: .625rem;
            cursor: pointer;
            box-shadow: 0 10px 24px -8px rgba(255, 158, 0, .55);
            transition: transform .06s ease, background .15s ease;
        }
        .pc-scan-btn:hover { background: var(--pc-accent-strong); }
        .pc-scan-btn:active { transform: scale(.98); }
        .pc-scan-btn svg { width: 1.375rem; height: 1.375rem; }

        .pc-stop-btn {
            width: 100%;
            min-height: 3rem;
            border: 1px solid var(--pc-border);
            border-radius: var(--pc-radius);
            background: var(--pc-inset);
            color: var(--pc-text);
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 600; font-size: 1rem;
            display: flex; align-items: center; justify-content: center; gap: .5rem;
            cursor: pointer;
        }
        .pc-stop-btn:active { transform: scale(.98); }

        .pc-caption {
            text-align: center; margin: -.5rem 0 0;
            color: var(--pc-text-3); font-size: .8125rem;
        }

        .pc-video-wrap {
            position: relative;
            border-radius: var(--pc-radius);
            overflow: hidden;
            background: #000;
            aspect-ratio: 4 / 3;
        }
        .pc-video { width: 100%; height: 100%; object-fit: cover; display: block; }
        .pc-video-frame {
            position: absolute; inset: 12% 14%;
            pointer-events: none;
        }
        .pc-video-frame::before, .pc-video-frame::after,
        .pc-video-frame > span::before, .pc-video-frame > span::after {
            content: ""; position: absolute;
            width: 1.75rem; height: 1.75rem;
            border: 3px solid var(--pc-scan);
        }
        .pc-video-frame::before  { top: 0; left: 0; border-right: none; border-bottom: none; border-top-left-radius: .5rem; }
        .pc-video-frame::after   { top: 0; right: 0; border-left: none; border-bottom: none; border-top-right-radius: .5rem; }
        .pc-video-frame > span::before { bottom: 0; left: 0; border-right: none; border-top: none; border-bottom-left-radius: .5rem; }
        .pc-video-frame > span::after  { bottom: 0; right: 0; border-left: none; border-top: none; border-bottom-right-radius: .5rem; }
        .pc-video-hint {
            position: absolute; left: 0; right: 0; bottom: .75rem;
            text-align: center; color: #fff; font-size: .8125rem;
            text-shadow: 0 1px 4px rgba(0,0,0,.6);
        }

        .pc-divider {
            display: flex; align-items: center; gap: .875rem;
            color: var(--pc-text-3);
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 600; font-size: .75rem; letter-spacing: .08em;
            text-transform: uppercase;
        }
        .pc-divider::before, .pc-divider::after {
            content: ""; flex: 1; height: 1px; background: var(--pc-border);
        }

        .pc-manual { display: flex; gap: .625rem; }
        .pc-input {
            flex: 1; min-width: 0;
            min-height: 3.25rem;
            border: 2px solid var(--pc-border);
            border-radius: .875rem;
            background: var(--pc-card);
            color: var(--pc-text);
            padding: 0 1rem;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.0625rem; letter-spacing: .02em;
            outline: none;
            transition: border-color .15s ease;
        }
        .pc-input::placeholder { color: var(--pc-text-3); letter-spacing: normal; font-weight: 500; }
        .pc-input:focus { border-color: var(--pc-scan); }
        .pc-check-btn {
            flex: none;
            min-height: 3.25rem;
            padding: 0 1.375rem;
            border: none;
            border-radius: .875rem;
            background: var(--pc-dark-btn);
            color: var(--pc-on-dark-btn);
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700; font-size: 1rem;
            cursor: pointer;
        }
        .pc-check-btn:active { transform: scale(.98); }

        .pc-panel {
            border-radius: var(--pc-radius);
            padding: 1.5rem 1.25rem;
            text-align: center;
        }
        .pc-result {
            background: var(--pc-card);
            border: 1px solid var(--pc-border);
            box-shadow: 0 1px 3px rgba(9,30,66,.08), 0 8px 24px -8px rgba(9,30,66,.08);
        }
        .pc-result-name {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 600; font-size: 1.25rem; line-height: 1.3;
            color: var(--pc-text);
        }
        .pc-result-price {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700; letter-spacing: -.03em;
            font-size: 2.75rem; line-height: 1.1; margin-top: .5rem;
            color: var(--pc-price);
            font-variant-numeric: tabular-nums;
        }
        .pc-scan-again {
            margin-top: 1.125rem;
            min-height: 3rem; padding: 0 1.25rem;
            border: 1px solid var(--pc-border);
            border-radius: .875rem;
            background: var(--pc-inset);
            color: var(--pc-text);
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 600; font-size: .9375rem;
            cursor: pointer;
        }
        .pc-not-found {
            background: var(--pc-inset);
            border: 1px dashed var(--pc-border);
            color: var(--pc-text-2);
            font-size: .9375rem;
        }
        .pc-camera-error {
            background: #fdecec;
            border: 1px solid #f5c2c2;
            color: #93000a;
            font-size: .875rem;
        }
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) .pc-camera-error { background: rgba(186,26,26,.15); border-color: rgba(186,26,26,.4); color: #ffb4ab; }
        }
        :root[data-theme="dark"] .pc-camera-error { background: rgba(186,26,26,.15); border-color: rgba(186,26,26,.4); color: #ffb4ab; }

        .pc-idle {
            border: 1px dashed var(--pc-border);
            border-radius: var(--pc-radius);
            padding: 2rem 1.25rem;
            text-align: center;
        }
        .pc-idle-icon {
            width: 3rem; height: 3rem; margin: 0 auto .875rem;
            border-radius: 999px;
            background: color-mix(in srgb, var(--pc-accent) 22%, transparent);
            display: flex; align-items: center; justify-content: center;
            color: var(--pc-accent-strong);
        }
        .pc-idle-icon svg { width: 1.375rem; height: 1.375rem; }
        .pc-idle-title {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 600; font-size: 1.0625rem; color: var(--pc-text);
        }
        .pc-idle-sub { margin-top: .375rem; color: var(--pc-text-2); font-size: .9375rem; }
    </style>
</head>
<body>
    <main class="pc-shell" data-pricing-root data-lookup-url="{{ route('pricing.lookup') }}">

        <div class="pc-top">
            <div class="pc-brand">
                <span class="pc-logo">
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ $shopName }}">
                    @else
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M7 12h10"/></svg>
                    @endif
                </span>
                <span class="pc-brand-text">
                    <span class="pc-shop">{{ $shopName }}</span>
                    <span class="pc-shop-sub">{{ __('pricing.title') }}</span>
                </span>
            </div>
            <button type="button" class="pc-theme-toggle" data-theme-toggle aria-label="Toggle theme">
                <svg data-icon-moon viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                <svg data-icon-sun hidden viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
            </button>
        </div>

        <div class="pc-head">
            <h1 class="pc-title">{{ __('pricing.title') }}</h1>
            <p class="pc-sub">{{ __('pricing.subtitle') }}</p>
        </div>

        <div>
            <button type="button" class="pc-scan-btn" data-start-scan>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                {{ __('pricing.scan_button') }}
            </button>
            <button type="button" class="pc-stop-btn" data-stop-scan hidden>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                {{ __('pricing.stop_button') }}
            </button>
            <p class="pc-caption">{{ __('pricing.scanning_hint') }}</p>
        </div>

        <div class="pc-video-wrap" data-video-wrap hidden>
            <video class="pc-video" data-video playsinline muted></video>
            <div class="pc-video-frame"><span></span></div>
            <div class="pc-video-hint">{{ __('pricing.scanning_hint') }}</div>
        </div>

        <p class="pc-panel pc-camera-error" data-camera-error hidden
           data-unsupported="{{ __('pricing.camera_unsupported') }}"
           data-denied="{{ __('pricing.camera_denied') }}"></p>

        <div class="pc-divider">{{ __('pricing.or_type') }}</div>

        <form class="pc-manual" data-manual-form>
            <input type="text" name="code" class="pc-input" inputmode="numeric"
                   placeholder="{{ __('pricing.manual_placeholder') }}" autocomplete="off">
            <button type="submit" class="pc-check-btn">{{ __('pricing.check_button') }}</button>
        </form>

        <div class="pc-panel pc-result" data-result hidden>
            <div class="pc-result-name" data-result-name></div>
            <div class="pc-result-price" data-result-price></div>
            <button type="button" class="pc-scan-again" data-scan-again>{{ __('pricing.scan_again') }}</button>
        </div>

        <div class="pc-panel pc-not-found" data-not-found hidden>
            {{ __('pricing.not_found') }}
        </div>

        <div class="pc-idle" data-idle>
            <span class="pc-idle-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
            </span>
            <div class="pc-idle-title">{{ __('pricing.title') }}</div>
            <div class="pc-idle-sub">{{ __('pricing.subtitle') }}</div>
        </div>

    </main>

    <script>
        // Theme toggle — remembers the viewer's pick; defaults to their OS setting.
        (function () {
            var root = document.documentElement;
            var moon = document.querySelector('[data-icon-moon]');
            var sun  = document.querySelector('[data-icon-sun]');
            try {
                var saved = localStorage.getItem('pc-theme');
                if (saved === 'dark' || saved === 'light') root.setAttribute('data-theme', saved);
            } catch (e) {}
            function sync() {
                var dark = root.getAttribute('data-theme') === 'dark'
                    || (!root.getAttribute('data-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches);
                if (moon) moon.hidden = dark;
                if (sun)  sun.hidden = !dark;
            }
            sync();
            var btn = document.querySelector('[data-theme-toggle]');
            if (btn) btn.addEventListener('click', function () {
                var dark = root.getAttribute('data-theme') === 'dark'
                    || (!root.getAttribute('data-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches);
                var next = dark ? 'light' : 'dark';
                root.setAttribute('data-theme', next);
                try { localStorage.setItem('pc-theme', next); } catch (e) {}
                sync();
            });
        })();

        // Hide the idle hint whenever a result / not-found / error / scan panel is showing.
        (function () {
            var root = document.querySelector('[data-pricing-root]');
            var idle = document.querySelector('[data-idle]');
            if (!root || !idle) return;
            var watched = ['[data-result]', '[data-not-found]', '[data-camera-error]', '[data-video-wrap]']
                .map(function (s) { return root.querySelector(s); }).filter(Boolean);
            var mo = new MutationObserver(function () {
                idle.hidden = watched.some(function (el) { return !el.hidden; });
            });
            watched.forEach(function (el) { mo.observe(el, { attributes: true, attributeFilter: ['hidden'] }); });
        })();
    </script>
</body>
</html>
