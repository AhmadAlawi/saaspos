{{--
    Public marketing landing page — rendered at "/" ONLY when demo mode
    is on (POS_DEMO_MODE=true). Self-contained document (its own <html>),
    so it doesn't pull in the admin chrome. Branding + accent color come
    from the company singleton via LandingController; theme (light/dark)
    is driven by the shared $store.ui store from app.js.
--}}
@php
    $isRtl    = in_array(app()->getLocale(), ['ar', 'he', 'ur', 'fa']);
    $logo     = $company?->app_logo_url;
    $logoDark = $company?->app_logo_dark_url;
    $favicon  = $company?->favicon_url;
    $brand    = $company?->brand_color;
    $brandFg  = $company?->brand_text_color;

    // When the visitor is already signed in, every "sign in / try demo"
    // action should take them into the app — pointing at /login would just
    // bounce off its `guest` middleware back to this page. Guests go to login.
    $isAuthed = auth()->check();
    $appUrl   = $isAuthed ? route('admin.dashboard') : route('login');

    // Decorative host shown in the faux browser address bar of the
    // screenshot frames (e.g. "hyper-pos-dev.infinitietech.in").
    $mockHost = parse_url(config('app.url'), PHP_URL_HOST) ?: 'your-store.com';

    // The screenshot shown inside the hero laptop frame: prefer a purpose-made
    // hero shot (images/landing/hero/monitor.*), else the cashier screenshot.
    // Either way it's framed as a real device — not shown raw.
    $heroShot = $heroMonitor ?: $cashierShot;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="index, follow">
    <meta name="description" content="{{ __('landing.meta.description') }}">
    <meta name="pos-theme-default" content="{{ $company?->theme_default ?: 'light' }}">
    @if ($favicon)
        <link rel="icon" href="{{ $favicon }}">
    @endif
    <title>{{ $appName }} — {{ __('landing.meta.title') }}</title>

    {{-- Google Analytics — self-gates on demo mode + a configured GA4 ID,
         so it never loads on a real customer install. --}}
    @include('partials.google-analytics')

    @vite(['resources/css/landing.css', 'resources/js/app.js', 'resources/js/landing.js'])

    {{-- Runtime brand-color theming — the same CSS-variable override layer
         the auth + admin shells use so a customer's accent color carries
         onto the demo landing page. --}}
    @if ($brand || $brandFg)
        <style>
            :root {
                @if ($brand)
                --accent: {{ $brand }};
                --accent-hover: color-mix(in srgb, {{ $brand }} 86%, #000);
                --accent-soft: color-mix(in srgb, {{ $brand }} 12%, transparent);
                --accent-ring: color-mix(in srgb, {{ $brand }} 30%, transparent);
                @endif
                --accent-fg: {{ $brandFg ?: '#fff' }};
            }
        </style>
    @endif
</head>
<body class="landing font-sans antialiased" x-data x-init="$store.ui.init()">
    <div class="landing-bg"></div>

    {{-- ── Top navigation ─────────────────────────────────── --}}
    <header class="landing-shell">
        <nav class="landing-nav">
            <a href="{{ route('landing') }}" class="landing-brand">
                @if ($logo && $logoDark)
                    {{-- both variants: swap via CSS on the dark class --}}
                    <img src="{{ $logo }}"     alt="{{ $appName }}" class="landing-brand-logo landing-brand-logo-light">
                    <img src="{{ $logoDark }}" alt="{{ $appName }}" class="landing-brand-logo landing-brand-logo-dark">
                @elseif ($logo || $logoDark)
                    {{-- single logo: always visible in both themes --}}
                    <img src="{{ $logo ?: $logoDark }}" alt="{{ $appName }}" class="landing-brand-logo">
                @else
                    <span class="landing-brand-mark">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($appName, 0, 1)) }}</span>
                    <span>{{ $appName }}</span>
                @endif
            </a>

            <div class="landing-nav-links">
                <a href="#features" class="landing-nav-link">{{ __('landing.nav.features') }}</a>
                <a href="#industries" class="landing-nav-link">{{ __('landing.nav.industries') }}</a>
                <a href="{{ route('documentation') }}" class="landing-nav-link" target="_blank" rel="noopener">{{ __('landing.nav.docs') }}</a>
            </div>

            <div class="landing-nav-actions">
                <button type="button"
                        class="landing-theme-btn"
                        aria-label="{{ __('landing.nav.toggle_theme') }}"
                        @click="$store.ui.toggle()">
                    <span class="dark:hidden"><x-icon name="moon" class="w-[18px] h-[18px]" /></span>
                    <span class="hidden dark:inline-flex"><x-icon name="sun" class="w-[18px] h-[18px]" /></span>
                </button>
                <a href="{{ $appUrl }}" target="_blank" rel="noopener" class="landing-btn landing-btn-ghost landing-btn-sm">
                    {{ $isAuthed ? __('landing.nav.dashboard') : __('landing.nav.sign_in') }}
                </a>
                @if ($purchaseUrl)
                    <a href="{{ $purchaseUrl }}" target="_blank" rel="noopener"
                       class="landing-btn landing-btn-primary landing-btn-sm">
                        {{ __('landing.nav.buy') }}
                    </a>
                @endif
            </div>
        </nav>
    </header>

    <main>
        {{-- ── Hero ───────────────────────────────────────── --}}
        <section class="landing-shell landing-hero">
            <div class="landing-hero-copy">
                <span class="landing-eyebrow">
                    <span class="landing-eyebrow-dot"></span>
                    {{ __('landing.hero.eyebrow') }}
                </span>

                <h1 class="landing-hero-title">{{ __('landing.hero.title') }}</h1>
                <p class="landing-hero-sub">{{ __('landing.hero.subtitle') }}</p>

                <div class="landing-hero-cta">
                    <a href="{{ $appUrl }}" target="_blank" rel="noopener" class="landing-btn landing-btn-primary landing-btn-lg">
                        {{ $isAuthed ? __('landing.go_to_dashboard') : __('landing.hero.cta_demo') }}
                        <x-icon name="arrow-right" class="w-[18px] h-[18px]" />
                    </a>
                    <a href="#features" class="landing-btn landing-btn-ghost landing-btn-lg">
                        {{ __('landing.hero.cta_learn') }}
                    </a>
                </div>

                <div class="landing-hero-trust">
                    @foreach (['trust_own', 'trust_source', 'trust_nofees'] as $t)
                        <span class="landing-trust-item">
                            <x-icon name="check" class="w-[16px] h-[16px]" />
                            {{ __('landing.hero.' . $t) }}
                        </span>
                    @endforeach
                </div>

                @php $heroPayLogos = array_values(array_filter($paymentMethods, fn ($p) => $p['logo'])); @endphp
                @if (count($heroPayLogos))
                    <div class="landing-hero-pays">
                        <span class="landing-hero-pays-label">{{ __('landing.hero.pays_label') }}</span>
                        <div class="landing-hero-pays-logos">
                            @foreach ($heroPayLogos as $pm)
                                <img src="{{ $pm['logo'] }}" alt="{{ $pm['label'] }}" class="landing-hero-pay" loading="lazy">
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Product preview. Prefers a monitor + tablet device mockup
                 (images/landing/hero/monitor|tablet.*); falls back to a
                 browser-framed cashier screenshot, then to a CSS mockup, so
                 the page is never broken on a fresh install. aria-hidden. --}}
            <div class="landing-hero-visual" aria-hidden="true">
                @if ($heroMain)
                    {{-- Preview mode: a single supplied image replaces the
                         whole monitor + tablet composition. --}}
                    <img src="{{ $heroMain }}" alt="" class="landing-hero-main" loading="lazy">
                @else
                @if ($heroShot)
                    {{-- Screenshot shown on a CSS desktop monitor (bezel +
                         stand + foot) so it reads as the POS on a real screen. --}}
                    <div class="landing-monitor">
                        <div class="landing-monitor-screen">
                            <img src="{{ $heroShot }}" alt="" class="landing-monitor-img" loading="lazy">
                        </div>
                        <div class="landing-monitor-stand"></div>
                    </div>
                @else
                    <div class="landing-mock">
                        <div class="landing-mock-bar">
                            <span class="landing-mock-dot"></span>
                            <span class="landing-mock-dot"></span>
                            <span class="landing-mock-dot"></span>
                            <span class="landing-mock-omni">
                                <x-icon name="lock" class="w-[11px] h-[11px]" />
                                {{ $mockHost }}
                            </span>
                        </div>
                        <div class="landing-mock-body">
                            <div class="landing-mock-grid">
                                <div class="landing-mock-search">
                                    <x-icon name="search" class="w-[13px] h-[13px]" />
                                    {{ __('landing.mock.search') }}
                                </div>
                                <div class="landing-mock-tiles">
                                    @for ($t = 0; $t < 6; $t++)
                                        <div class="landing-mock-tile">
                                            <div class="landing-mock-thumb"></div>
                                            <div class="landing-mock-line"></div>
                                            <div class="landing-mock-line short"></div>
                                        </div>
                                    @endfor
                                </div>
                            </div>
                            <div class="landing-mock-cart">
                                @for ($r = 0; $r < 3; $r++)
                                    <div class="landing-mock-cart-row">
                                        <span class="landing-mock-chip"></span>
                                        <span class="landing-mock-line"></span>
                                    </div>
                                @endfor
                                <div class="landing-mock-total">
                                    <span>{{ __('landing.mock.total') }}</span>
                                    <b>{{ __('landing.mock.amount') }}</b>
                                </div>
                                <div class="landing-mock-pay">{{ __('landing.mock.pay') }}</div>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($heroTablet)
                    <div class="landing-tablet">
                        <img src="{{ $heroTablet }}" alt="" class="landing-tablet-img" loading="lazy">
                    </div>
                @endif
                @endif {{-- /heroMain --}}

                @if ($heroBarcode)
                    <span class="landing-float-card landing-float-barcode">
                        <img src="{{ $heroBarcode }}" alt="" loading="lazy">
                    </span>
                @endif
                @if ($heroReceipt)
                    <span class="landing-float-card landing-float-receipt">
                        <img src="{{ $heroReceipt }}" alt="" loading="lazy">
                    </span>
                @endif
                {{-- Floating key-feature badges around the hero visual. --}}
                @foreach ([
                    ['icon' => 'refresh', 'key' => 'badge_offline',   'pos' => 1],
                    ['icon' => 'card',    'key' => 'badge_payment',   'pos' => 2],
                    ['icon' => 'store',   'key' => 'badge_branch',    'pos' => 3],
                    ['icon' => 'box',     'key' => 'badge_inventory', 'pos' => 4],
                ] as $b)
                    <span class="landing-feat-badge is-pos-{{ $b['pos'] }}">
                        <x-icon :name="$b['icon']" class="w-[15px] h-[15px]" />
                        {{ __('landing.hero.' . $b['key']) }}
                    </span>
                @endforeach
            </div>
        </section>

        {{-- ── Stat strip ─────────────────────────────────── --}}
        <section class="landing-shell">
            <div class="landing-stats" data-reveal>
                @foreach ([
                    'gateways', 'stores', 'inventory',
                    'offline', 'languages', 'monthly_fees',
                ] as $stat)
                    <div class="landing-stat">
                        <div class="landing-stat-value" data-countup>{{ __('landing.stats.' . $stat . '_val') }}</div>
                        <div class="landing-stat-label">{{ __('landing.stats.' . $stat) }}</div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ── Features ───────────────────────────────────── --}}
        <section id="features" class="landing-shell landing-section">
            <div class="landing-section-head" data-reveal>
                <span class="landing-eyebrow-text">{{ __('landing.features.eyebrow') }}</span>
                <h2 class="landing-section-title">{{ __('landing.features.title') }}</h2>
                <p class="landing-section-sub">{{ __('landing.features.subtitle') }}</p>
            </div>

            <div class="landing-features">
                @foreach ([
                    ['icon' => 'refresh',     'key' => 'offline'],
                    ['icon' => 'store',       'key' => 'multi_industry'],
                    ['icon' => 'bell',        'key' => 'realtime'],
                    ['icon' => 'database',    'key' => 'self_hosted'],
                    ['icon' => 'link',        'key' => 'extensible'],
                    ['icon' => 'box',         'key' => 'inventory'],
                    ['icon' => 'card',        'key' => 'payments'],
                    ['icon' => 'shield',      'key' => 'compliance'],
                ] as $f)
                    <article class="landing-card" data-reveal>
                        <span class="landing-card-icon"><x-icon :name="$f['icon']" class="w-[22px] h-[22px]" /></span>
                        <h3 class="landing-card-title">{{ __('landing.features.' . $f['key'] . '.title') }}</h3>
                        <p class="landing-card-body">{{ __('landing.features.' . $f['key'] . '.body') }}</p>
                    </article>
                @endforeach
            </div>

            <div class="landing-features-more" data-reveal>
                <a href="#everything" class="landing-text-link">
                    {{ __('landing.features.explore') }}
                    <x-icon name="chevron" class="w-[16px] h-[16px]" />
                </a>
            </div>
        </section>

        {{-- ── Everything included (full feature grid) ─────── --}}
        <section id="everything" class="landing-shell landing-section">
            <div class="landing-section-head" data-reveal>
                <span class="landing-eyebrow-text">{{ __('landing.grid.eyebrow') }}</span>
                <h2 class="landing-section-title">{{ __('landing.grid.title') }}</h2>
                <p class="landing-section-sub">{{ __('landing.grid.subtitle') }}</p>
            </div>

            @php
                // 24 features split into 3 rows. Each row scrolls as an
                // infinite marquee (rows alternate direction); the items are
                // rendered twice so the loop is seamless — the second set is
                // aria-hidden + data-clone so it's ignored by AT and hidden
                // when motion is reduced.
                $featureRows = array_chunk([
                    ['icon' => 'refresh',    'key' => 'offline'],
                    ['icon' => 'barcode',    'key' => 'barcode'],
                    ['icon' => 'store',      'key' => 'multistore'],
                    ['icon' => 'qr-code',    'key' => 'gateways'],
                    ['icon' => 'card',       'key' => 'split'],
                    ['icon' => 'refund',     'key' => 'refunds'],
                    ['icon' => 'cash',       'key' => 'shifts'],
                    ['icon' => 'calculator', 'key' => 'weight'],
                    ['icon' => 'tag',        'key' => 'discounts'],
                    ['icon' => 'customers',  'key' => 'customers'],
                    ['icon' => 'receipt',    'key' => 'credit'],
                    ['icon' => 'grid',       'key' => 'variants'],
                    ['icon' => 'box',        'key' => 'kits'],
                    ['icon' => 'archive',    'key' => 'batches'],
                    ['icon' => 'truck',      'key' => 'transfers'],
                    ['icon' => 'download',   'key' => 'purchases'],
                    ['icon' => 'check-all',  'key' => 'counts'],
                    ['icon' => 'alert',      'key' => 'lowstock'],
                    ['icon' => 'pie',        'key' => 'tax'],
                    ['icon' => 'lock',       'key' => 'roles'],
                    ['icon' => 'dashboard',  'key' => 'dashboard'],
                    ['icon' => 'upload',     'key' => 'importexport'],
                    ['icon' => 'globe',      'key' => 'languages'],
                    ['icon' => 'database',   'key' => 'backups'],
                ], 8);
            @endphp

            <div class="landing-marquees" data-reveal>
                @foreach ($featureRows as $ri => $row)
                    <div class="landing-marquee {{ $ri % 2 ? 'is-reverse' : '' }}">
                        <div class="landing-marquee-track">
                            @foreach (array_merge($row, $row) as $i => $f)
                                <span class="landing-feat-chip" @if ($i >= count($row)) aria-hidden="true" data-clone @endif>
                                    <x-icon :name="$f['icon']" class="w-[24px] h-[24px]" />
                                    <span>{{ __('landing.grid.items.' . $f['key']) }}</span>
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="landing-grid-more">{{ __('landing.grid.more') }}</p>
        </section>

        {{-- ── Sell anywhere (payments + languages) ───────── --}}
        <section class="landing-shell landing-section">
            <div class="landing-global" data-reveal>
                <div class="landing-global-col">
                    <span class="landing-eyebrow-text">{{ __('landing.global.payments_eyebrow') }}</span>
                    <h2 class="landing-global-title">{{ __('landing.global.payments_title') }}</h2>
                    <p class="landing-global-body">{{ __('landing.global.payments_body') }}</p>
                    <div class="landing-chips">
                        @foreach ($paymentMethods as $pm)
                            @if ($pm['logo'])
                                <span class="landing-chip landing-chip-logo">
                                    <img src="{{ $pm['logo'] }}" alt="{{ $pm['label'] }}" loading="lazy">
                                </span>
                            @else
                                <span class="landing-chip">{{ $pm['label'] }}</span>
                            @endif
                        @endforeach
                    </div>
                </div>
                <div class="landing-global-col">
                    <span class="landing-eyebrow-text">{{ __('landing.global.world_eyebrow') }}</span>
                    <h2 class="landing-global-title">{{ __('landing.global.world_title') }}</h2>
                    <p class="landing-global-body">{{ __('landing.global.world_body') }}</p>
                    <div class="landing-chips">
                        @foreach ([
                            ['icon' => 'globe', 'key' => 'chip_languages'],
                            ['icon' => 'list',  'key' => 'chip_rtl'],
                            ['icon' => 'clock', 'key' => 'chip_timezones'],
                        ] as $w)
                            <span class="landing-chip">
                                <x-icon :name="$w['icon']" class="w-[15px] h-[15px]" />
                                {{ __('landing.global.' . $w['key']) }}
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- ── Dashboard showcase (only when a screenshot exists) ─ --}}
        @if ($dashboardShot)
            <section class="landing-shell landing-section">
                <div class="landing-section-head" data-reveal>
                    <span class="landing-eyebrow-text">{{ __('landing.showcase.eyebrow') }}</span>
                    <h2 class="landing-section-title">{{ __('landing.showcase.title') }}</h2>
                    <p class="landing-section-sub">{{ __('landing.showcase.body') }}</p>
                </div>
                <figure class="landing-showcase" data-reveal>
                    <div class="landing-mock-bar">
                        <span class="landing-mock-dot"></span>
                        <span class="landing-mock-dot"></span>
                        <span class="landing-mock-dot"></span>
                        <span class="landing-mock-omni">
                            <x-icon name="lock" class="w-[11px] h-[11px]" />
                            {{ $mockHost }}
                        </span>
                    </div>
                    <img src="{{ $dashboardShot }}" alt="{{ __('landing.showcase.alt') }}" class="landing-shot-img" loading="lazy">
                </figure>
            </section>
        @endif

        {{-- ── Screenshot gallery (auto-filled from /slider) ─ --}}
        @if (! empty($galleryShots))
            <section class="landing-shell landing-section">
                <div class="landing-section-head" data-reveal>
                    <span class="landing-eyebrow-text">{{ __('landing.gallery.eyebrow') }}</span>
                    <h2 class="landing-section-title">{{ __('landing.gallery.title') }}</h2>
                    <p class="landing-section-sub">{{ __('landing.gallery.subtitle') }}</p>
                </div>
                <div class="landing-slider" data-slider data-reveal>
                    <button type="button" class="landing-slider-btn landing-slider-prev"
                            data-slider-prev aria-label="{{ __('landing.gallery.prev') }}">
                        <x-icon name="back" class="w-[18px] h-[18px]" />
                    </button>

                    <div class="landing-slider-track" data-slider-track>
                        @foreach ($galleryShots as $shot)
                            <figure class="landing-slide">
                                <span class="landing-slide-frame">
                                    <img src="{{ $shot['url'] }}" alt="{{ $shot['label'] }}" class="landing-slide-img" loading="lazy">
                                </span>
                                <figcaption class="landing-slide-cap">{{ $shot['label'] }}</figcaption>
                            </figure>
                        @endforeach
                    </div>

                    <button type="button" class="landing-slider-btn landing-slider-next"
                            data-slider-next aria-label="{{ __('landing.gallery.next') }}">
                        <x-icon name="arrow-right" class="w-[18px] h-[18px]" />
                    </button>
                </div>
            </section>

            {{-- Lightbox — opened by clicking any slide; JS reads the slide
                 images, so no per-image wiring is needed. --}}
            <div class="landing-lightbox" data-lightbox hidden>
                <button type="button" class="landing-lightbox-close" data-lb-close aria-label="{{ __('landing.gallery.close') }}">
                    <x-icon name="x" class="w-[22px] h-[22px]" />
                </button>
                <button type="button" class="landing-lightbox-nav landing-lightbox-prev" data-lb-prev aria-label="{{ __('landing.gallery.prev') }}">
                    <x-icon name="back" class="w-[22px] h-[22px]" />
                </button>
                <figure class="landing-lightbox-stage">
                    <img data-lb-img src="" alt="">
                    <figcaption class="landing-lightbox-cap" data-lb-cap></figcaption>
                </figure>
                <button type="button" class="landing-lightbox-nav landing-lightbox-next" data-lb-next aria-label="{{ __('landing.gallery.next') }}">
                    <x-icon name="arrow-right" class="w-[22px] h-[22px]" />
                </button>
            </div>
        @endif

        {{-- ── How the demo works ─────────────────────────── --}}
        <section class="landing-shell landing-section">
            <div class="landing-section-head" data-reveal>
                <span class="landing-eyebrow-text">{{ __('landing.steps.eyebrow') }}</span>
                <h2 class="landing-section-title">{{ __('landing.steps.title') }}</h2>
                <p class="landing-section-sub">{{ __('landing.steps.subtitle') }}</p>
            </div>

            <div class="landing-steps">
                @foreach (['signin', 'explore', 'reset'] as $n => $key)
                    <article class="landing-step" data-reveal>
                        <span class="landing-step-num">{{ $n + 1 }}</span>
                        <h3 class="landing-step-title">{{ __('landing.steps.' . $key . '.title') }}</h3>
                        <p class="landing-step-body">{{ __('landing.steps.' . $key . '.body') }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        {{-- ── Industries ─────────────────────────────────── --}}
        <section id="industries" class="landing-shell landing-section">
            <div class="landing-section-head" data-reveal>
                <span class="landing-eyebrow-text">{{ __('landing.industries.eyebrow') }}</span>
                <h2 class="landing-section-title">{{ __('landing.industries.title') }}</h2>
                <p class="landing-section-sub">{{ __('landing.industries.subtitle') }}</p>
            </div>

            <div class="landing-industries">
                @foreach ([
                    ['icon' => 'tag',        'key' => 'retail'],
                    ['icon' => 'cart',       'key' => 'supermarket'],
                    ['icon' => 'shield',     'key' => 'pharmacy'],
                ] as $i)
                    <article class="landing-industry" data-reveal>
                        <span class="landing-industry-icon"><x-icon :name="$i['icon']" class="w-[24px] h-[24px]" /></span>
                        <h3 class="landing-industry-title">{{ __('landing.industries.' . $i['key'] . '.title') }}</h3>
                        <p class="landing-industry-body">{{ __('landing.industries.' . $i['key'] . '.body') }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        {{-- ── Built with (tech stack) ────────────────────── --}}
        <section class="landing-shell landing-section">
            <div class="landing-section-head" data-reveal>
                <span class="landing-eyebrow-text">{{ __('landing.tech.eyebrow') }}</span>
                <h2 class="landing-section-title">{{ __('landing.tech.title') }}</h2>
                <p class="landing-section-sub">{{ __('landing.tech.subtitle') }}</p>
            </div>

            <div class="landing-tech-grid" data-reveal>
                @foreach ($techStack as $tech)
                    <div class="landing-tech-card" title="{{ $tech['label'] }}">
                        @if ($tech['logo'])
                            <img src="{{ $tech['logo'] }}" alt="{{ $tech['label'] }}" class="landing-tech-logo" loading="lazy">
                        @else
                            <span class="landing-tech-badge is-{{ $tech['slug'] }}">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($tech['label'], 0, 1)) }}</span>
                        @endif
                        <span class="landing-tech-name">{{ $tech['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ── Pricing / license comparison ────────────────── --}}
        <section id="pricing" class="landing-shell landing-section">
            <div class="landing-section-head" data-reveal>
                <span class="landing-eyebrow-text">{{ __('landing.pricing.eyebrow') }}</span>
                <h2 class="landing-section-title">{{ __('landing.pricing.title') }}</h2>
                <p class="landing-section-sub">{{ __('landing.pricing.subtitle') }}</p>
            </div>

            <div class="landing-pricing" data-reveal>
                <div class="landing-pricing-scroll">
                    <table class="landing-pricing-table">
                        <thead>
                            <tr>
                                <th>{{ __('landing.pricing.col_feature') }}</th>
                                <th class="col-mark">{{ __('landing.pricing.col_regular') }}</th>
                                <th class="col-mark is-rec">
                                    {{ __('landing.pricing.col_extended') }}
                                    <span class="landing-pricing-badge">{{ __('landing.pricing.recommended') }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ([
                                ['key' => 'lifetime',   'r' => true,  'e' => true],
                                ['key' => 'domain',     'r' => true,  'e' => true],
                                ['key' => 'support',    'r' => true,  'e' => true],
                                ['key' => 'premium',    'r' => true,  'e' => true],
                                ['key' => 'updates',    'r' => true,  'e' => true],
                                ['key' => 'source',     'r' => true,  'e' => true],
                                ['key' => 'personal',   'r' => true,  'e' => true],
                                ['key' => 'install',    'r' => false, 'e' => true],
                                ['key' => 'remote',     'r' => false, 'e' => true],
                                ['key' => 'priority',   'r' => false, 'e' => true],
                                ['key' => 'branding',   'r' => false, 'e' => true],
                                ['key' => 'commercial', 'r' => false, 'e' => true],
                            ] as $row)
                                <tr>
                                    <td>{{ __('landing.pricing.rows.' . $row['key']) }}</td>
                                    <td class="col-mark">
                                        <span class="landing-mark {{ $row['r'] ? 'landing-mark-yes' : 'landing-mark-no' }}">
                                            <x-icon :name="$row['r'] ? 'check' : 'x'" class="w-[14px] h-[14px]" />
                                        </span>
                                    </td>
                                    <td class="col-mark is-rec">
                                        <span class="landing-mark {{ $row['e'] ? 'landing-mark-yes' : 'landing-mark-no' }}">
                                            <x-icon :name="$row['e'] ? 'check' : 'x'" class="w-[14px] h-[14px]" />
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($extendedUrl)
                    <div class="landing-pricing-band">
                        <span class="landing-pricing-band-text">{{ __('landing.pricing.band_text') }}</span>
                        <a href="{{ $extendedUrl }}" target="_blank" rel="noopener" class="landing-btn landing-btn-primary">
                            {{ __('landing.pricing.band_cta') }}
                            <x-icon name="arrow-right" class="w-[18px] h-[18px]" />
                        </a>
                    </div>
                @endif
            </div>
        </section>

        {{-- ── CTA band — drives the purchase first, demo second ─ --}}
        <section class="landing-shell landing-section">
            <div class="landing-cta" data-reveal>
                <h2 class="landing-cta-title">{{ __('landing.cta.title') }}</h2>
                <p class="landing-cta-sub">{{ __('landing.cta.subtitle') }}</p>
                <div class="landing-cta-actions">
                    @if ($purchaseUrl)
                        <a href="{{ $purchaseUrl }}" target="_blank" rel="noopener"
                           class="landing-btn landing-btn-primary landing-btn-lg">
                            {{ __('landing.cta.buy') }}
                            <x-icon name="arrow-right" class="w-[18px] h-[18px]" />
                        </a>
                    @endif
                    <a href="{{ $appUrl }}" target="_blank" rel="noopener"
                       class="landing-btn {{ $purchaseUrl ? 'landing-btn-ghost' : 'landing-btn-primary' }} landing-btn-lg">
                        {{ $isAuthed ? __('landing.go_to_dashboard') : __('landing.cta.button') }}
                        @unless ($purchaseUrl)<x-icon name="arrow-right" class="w-[18px] h-[18px]" />@endunless
                    </a>
                </div>
                @if ($purchaseUrl)
                    <p class="landing-cta-note">{{ __('landing.cta.note') }}</p>
                @endif
            </div>
        </section>

        {{-- ── Customization / setup help (WhatsApp) ───────── --}}
        @if ($whatsappUrl)
            <section class="landing-shell landing-section">
                <div class="landing-help" data-reveal>
                    <h2 class="landing-help-title">{{ __('landing.help.title') }}</h2>
                    <p class="landing-help-body">{{ __('landing.help.body') }}</p>
                    <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener"
                       class="landing-btn landing-btn-primary landing-btn-lg">
                        {{ __('landing.help.cta') }}
                        <x-icon name="arrow-right" class="w-[18px] h-[18px]" />
                    </a>
                </div>
            </section>
        @endif
    </main>

    {{-- ── Footer ─────────────────────────────────────────── --}}
    <footer class="landing-footer">
        <div class="landing-shell landing-footer-inner">
            <div class="landing-footer-brand">
                @if ($logo && $logoDark)
                    <img src="{{ $logo }}"     alt="{{ $appName }}" class="landing-brand-logo landing-brand-logo-light">
                    <img src="{{ $logoDark }}" alt="{{ $appName }}" class="landing-brand-logo landing-brand-logo-dark">
                @elseif ($logo || $logoDark)
                    <img src="{{ $logo ?: $logoDark }}" alt="{{ $appName }}" class="landing-brand-logo">
                @else
                    <span class="landing-brand-mark">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($appName, 0, 1)) }}</span>
                    <span>{{ $appName }}</span>
                @endif
            </div>
            <p class="landing-footer-tagline">{{ __('landing.footer.tagline') }}</p>

            <div class="landing-footer-links">
                <a href="{{ route('documentation') }}" class="landing-footer-link" target="_blank" rel="noopener">
                    {{ __('landing.footer.docs') }}
                </a>
                @if ($purchaseUrl)
                    <a href="{{ $purchaseUrl }}" class="landing-footer-link" target="_blank" rel="noopener">
                        {{ __('landing.footer.buy') }}
                    </a>
                @endif
                @if ($websiteUrl)
                    <a href="{{ $websiteUrl }}" class="landing-footer-link" target="_blank" rel="noopener">
                        {{ __('landing.footer.product') }}
                    </a>
                @endif
                @if ($company && trim((string) $company->privacy_policy) !== '')
                    <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener" class="landing-footer-link">{{ __('landing.footer.privacy') }}</a>
                @endif
                @if ($company && trim((string) $company->terms_of_service) !== '')
                    <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener" class="landing-footer-link">{{ __('landing.footer.terms') }}</a>
                @endif
            </div>

            <p class="landing-footer-copy">
                &copy; {{ now()->year }} {{ $appName }}. {{ __('landing.footer.rights') }}
            </p>
            <p class="landing-footer-made">
                {!! __('landing.footer.made_by', [
                    'heart'   => '<span class="landing-footer-heart">&hearts;</span>',
                    'company' => '<a href="https://infinitietech.com/" target="_blank" rel="noopener" class="landing-footer-link">Infinitie Technologies</a>',
                ]) !!}
            </p>
        </div>
    </footer>

    {{-- Floating "Buy now" CTA — mirrors the admin demo panel's floating
         button so the demo always has a one-tap path to purchase. Only shown
         when a purchase URL is configured. --}}
    @if ($purchaseUrl)
        <a href="{{ $purchaseUrl }}" target="_blank" rel="noopener"
           class="landing-buy-now" aria-label="{{ __('landing.buy_now') }}">
            <x-icon name="cart" class="w-[18px] h-[18px]" />
            <span class="landing-buy-now-label">{{ __('landing.buy_now') }}</span>
        </a>
    @endif

    {{-- Back-to-top — JS toggles .is-visible past a scroll threshold. --}}
    <button type="button" class="landing-totop" data-totop aria-label="{{ __('landing.totop') }}">
        <x-icon name="chevron" class="w-[20px] h-[20px] landing-icon-up" />
    </button>
</body>
</html>
