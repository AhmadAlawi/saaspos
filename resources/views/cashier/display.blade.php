<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      dir="{{ in_array(app()->getLocale(), ['ar', 'he', 'ur', 'fa']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="pos-theme-default" content="{{ $themeDefault }}">
    <title>{{ __('customer-display.title') }} — {{ $appName }}</title>

    {{-- No-flash theme bootstrap. Priority: the display's OWN saved choice
         (`pos_cfd_theme`, set by the on-screen toggle) → the till's theme →
         the company default → light. The display keeps its own key so
         flipping it never changes the cashier/admin theme. --}}
    <script>
        (function () {
            try {
                var t = localStorage.getItem('pos_cfd_theme')
                    || localStorage.getItem('pos_theme')
                    || document.querySelector('meta[name="pos-theme-default"]').content
                    || 'light';
                var dark = t === 'dark'
                    || (t === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
            } catch (e) {}
        })();
    </script>

    @if ($faviconUrl)
        <link rel="icon" href="{{ $faviconUrl }}">
    @endif

    @vite(['resources/css/cashier/customer-display.css', 'resources/js/cashier/customer-display.js'])

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
<body class="font-sans antialiased">
    {{-- No x-init="init()": Alpine already auto-calls the component's
         init(), and adding x-init here ran it twice — starting two poll
         loops (the /state endpoint was fetched twice per tick). --}}
    <div class="cfd"
         x-data="customerDisplay({{ Js::from($bootstrap) }})"
         x-cloak>

        {{-- Brand strip — present in idle / sale / payment, hidden on the
             full-bleed thank-you screen. --}}
        <div class="cfd-top" x-show="state !== 'thankyou'">
            <div class="cfd-brand">
                @if ($appLogoUrl || $appLogoDarkUrl)
                    @if ($appLogoUrl)
                        <img src="{{ $appLogoUrl }}" alt="" class="cfd-logo-img{{ $appLogoDarkUrl ? ' dark:hidden' : '' }}">
                    @endif
                    @if ($appLogoDarkUrl)
                        <img src="{{ $appLogoDarkUrl }}" alt="" class="cfd-logo-img hidden dark:block">
                    @endif
                @else
                    <span class="cfd-logo">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($storeName, 0, 1)) }}</span>
                @endif
                <div>
                    <div class="cfd-store">{{ $storeName }}</div>
                    <div class="cfd-status"><span class="cfd-dot"></span>{{ __('customer-display.status.open') }}</div>
                </div>
            </div>
            <div class="cfd-topbar-end">
                {{-- Light / dark toggle. Uses its own `pos_cfd_theme` key so
                     changing the display never flips the cashier's theme. --}}
                <button type="button" class="cfd-theme-btn" @click="toggleTheme()"
                        aria-label="{{ __('customer-display.toggle_theme') }}"
                        title="{{ __('customer-display.toggle_theme') }}">
                    <span class="dark:hidden"><x-icon name="moon" class="w-5 h-5" /></span>
                    <span class="hidden dark:inline-flex"><x-icon name="sun" class="w-5 h-5" /></span>
                </button>
                <div class="cfd-clock"><span x-text="clock"></span></div>
            </div>
        </div>

        <div class="cfd-stage">
            {{-- ══════════════ IDLE / ATTRACT ══════════════ --}}
            <template x-if="state === 'idle'">
                <div class="cfd-idle">
                    {{-- Welcome band. The store logo already lives in the top
                         strip, so the idle body leads with the greeting for a
                         balanced, less top-heavy composition. --}}
                    <div>
                        <div class="cfd-welcome"><span class="cfd-wave">👋</span><span x-text="welcome"></span></div>
                        <div class="cfd-tagline" x-text="tagline"></div>
                    </div>

                    {{-- Attract-media slideshow — the merchant's uploaded promo
                         images rotate here, in a framed "Today's offers" card. --}}
                    <template x-if="hasAttract">
                        <div class="cfd-promo">
                            <span class="cfd-promo-tab">{{ __('customer-display.idle.offers') }}</span>
                            <div class="cfd-promo-stage">
                                <template x-for="(url, i) in attract.media" :key="i">
                                    <img class="cfd-promo-slide" :src="url" alt=""
                                         :style="`opacity:${i === promoIx ? 1 : 0}`">
                                </template>
                                <div class="cfd-promo-dots" x-show="attract.media.length > 1">
                                    <template x-for="(url, i) in attract.media" :key="'d' + i">
                                        <i :class="i === promoIx && 'on'"></i>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            {{-- ══════════════ ACTIVE SALE ══════════════ --}}
            <template x-if="state === 'sale'">
                <div class="cfd-sale">
                    {{-- left: slim "just added" banner + itemised order card --}}
                    <div class="cfd-main">
                        <template x-if="snap.last_line">
                            <div class="cfd-added">
                                <span class="cfd-thumb cfd-thumb-lg">
                                    <span class="cfd-thumb-ph" x-text="(snap.last_line.name || '?').slice(0,1).toUpperCase()"></span>
                                    <template x-if="snap.last_line.image"><img :src="snap.last_line.image" alt="" loading="lazy" x-on:error="$el.remove()"></template>
                                </span>
                                <span class="cfd-added-tag"><span class="cfd-dot"></span><span x-text="labels.added"></span></span>
                                <span class="cfd-added-name" x-text="snap.last_line.name"></span>
                                <span class="cfd-added-price" x-text="snap.last_line.line_total"></span>
                            </div>
                        </template>

                        <div class="cfd-order">
                            <div class="cfd-order-top">
                                <span class="cfd-order-title" x-text="labels.your_order"></span>
                                <span class="cfd-order-count" x-text="itemCountLabel"></span>
                            </div>
                            <div class="cfd-order-head">
                                <span x-text="labels.col_item"></span>
                                <span class="c" x-text="labels.col_qty"></span>
                                <span class="e" x-text="labels.col_amount"></span>
                            </div>
                            <div class="cfd-list">
                                <template x-for="(l, i) in linesNewestFirst" :key="l.key">
                                    <div class="cfd-row" :class="i === 0 && 'is-new'">
                                        <div class="cfd-row-item">
                                            <span class="cfd-thumb">
                                                <span class="cfd-thumb-ph" x-text="(l.name || '?').slice(0,1).toUpperCase()"></span>
                                                <template x-if="l.image"><img :src="l.image" alt="" loading="lazy" x-on:error="$el.remove()"></template>
                                            </span>
                                            <div class="cfd-row-text">
                                                <div class="t" x-text="l.name"></div>
                                                <div class="m" x-text="l.unit_price + ' ' + labels.each"></div>
                                            </div>
                                        </div>
                                        <div class="cfd-row-qty" x-text="l.quantity"></div>
                                        <div class="cfd-row-amt" x-text="l.line_total"></div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- right: summary card --}}
                    <div class="cfd-totals">
                        <div class="cfd-sum">
                            <template x-if="snap.customer">
                                <div class="cfd-cust">
                                    <div class="av" x-text="snap.customer.initials"></div>
                                    <div>
                                        <div class="nm" x-text="snap.customer.name"></div>
                                        <div class="lp" x-text="labels.loyalty"></div>
                                    </div>
                                    <template x-if="snap.customer.points !== null && snap.customer.points !== undefined">
                                        <div class="pts">
                                            <div class="v" x-text="snap.customer.points"></div>
                                            <div class="u" x-text="labels.points"></div>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <div class="cfd-subrows">
                                <div class="cfd-subrow">
                                    <span class="l" x-text="labels.subtotal"></span>
                                    <span class="v" x-text="snap.totals.subtotal"></span>
                                </div>
                                <template x-if="snap.totals.has_discount">
                                    <div class="cfd-subrow discount">
                                        <span class="l" x-text="labels.discount"></span>
                                        <span class="v" x-text="'−' + snap.totals.discount"></span>
                                    </div>
                                </template>
                                <template x-if="snap.totals.has_tax">
                                    <div class="cfd-subrow">
                                        <span class="l" x-text="labels.tax"></span>
                                        <span class="v" x-text="snap.totals.tax"></span>
                                    </div>
                                </template>
                            </div>

                            <div class="cfd-divider"></div>

                            <div class="cfd-grand">
                                <div class="lbl" x-text="labels.total"></div>
                                <div class="amt" x-text="snap.totals.grand"></div>
                                <div class="hint">
                                    <span class="cfd-dot cfd-dot-accent"></span>
                                    <span x-text="labels.scanning"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            {{-- ══════════════ PAYMENT ══════════════ --}}
            <template x-if="state === 'payment'">
                <div class="cfd-pay" :class="(!payQr && !upiQr) && 'cfd-pay--noqr'">
                    <div class="cfd-pay-amount">
                        <div class="cfd-pay-eyebrow" x-text="labels.amount_due"></div>
                        <div class="cfd-pay-big" x-text="snap.payment.amount_due"></div>
                        <template x-if="snap.payment.has_tendered || snap.payment.has_change">
                            <div class="cfd-pay-cash">
                                <div class="box">
                                    <div class="k" x-text="labels.tendered"></div>
                                    <div class="v" x-text="snap.payment.tendered"></div>
                                </div>
                                <div class="box change">
                                    <div class="k" x-text="labels.change_due"></div>
                                    <div class="v" x-text="snap.payment.change_due"></div>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- QR pane — only when a QR-chooser session is live. The
                         customer scans this off their own screen and picks a
                         gateway on /pay/pos/{uuid}. --}}
                    <template x-if="payQr">
                        <div class="cfd-pay-qr">
                            <div class="cfd-qr-card"><img :src="payQr" alt=""></div>
                            {{-- Same QR/pay_url either way — Apple Pay and
                                 Google Pay just get a wallet-branded label
                                 (see wallet_brand in the payment snapshot). --}}
                            <div class="say" x-show="snap.payment.wallet_brand === 'apple_pay'" x-cloak>
                                <div class="h" x-text="labels.scan_apple_pay"></div>
                                <div class="s" x-text="labels.scan_wallet_hint"></div>
                            </div>
                            <div class="say" x-show="snap.payment.wallet_brand === 'google_pay'" x-cloak>
                                <div class="h" x-text="labels.scan_google_pay"></div>
                                <div class="s" x-text="labels.scan_wallet_hint"></div>
                            </div>
                            <div class="say" x-show="!snap.payment.wallet_brand" x-cloak>
                                <div class="h" x-text="labels.scan_to_pay"></div>
                                <div class="s" x-text="labels.scan_hint"></div>
                            </div>
                        </div>
                    </template>

                    {{-- UPI pane — mirrors the till's `upi://pay` QR when this
                         terminal opts in. The customer scans it from their own
                         screen with any UPI app; the cashier still confirms the
                         UTR on the till (manual-confirm method). --}}
                    <template x-if="upiQr">
                        <div class="cfd-pay-qr">
                            <div class="cfd-qr-card"><img :src="upiQr" alt=""></div>
                            <div class="say">
                                <div class="h" x-text="labels.scan_upi"></div>
                                <div class="s" x-text="labels.scan_upi_hint"></div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            {{-- ══════════════ THANK YOU ══════════════ --}}
            <template x-if="state === 'thankyou'">
                <div class="cfd-thanks">
                    <div class="tick">
                        <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 6 9 17l-5-5"/>
                        </svg>
                    </div>
                    <div class="cfd-thanks-title" x-text="thankyou"></div>
                    <template x-if="snap.thankyou && snap.thankyou.has_change">
                        <div class="cfd-thanks-change">
                            <span class="k" x-text="labels.change"></span>
                            <span class="v" x-text="snap.thankyou.change_due"></span>
                        </div>
                    </template>

                    {{-- Receipt QR — scan for a no-login digital receipt.
                         Only present for online sales (offline sales have no
                         server link yet). --}}
                    <template x-if="receiptQr">
                        <div class="cfd-thanks-receipt">
                            <div class="cfd-qr-mini"><img :src="receiptQr" alt=""></div>
                            <div class="txt">
                                <div class="h" x-text="labels.receipt_scan"></div>
                                <div class="s" x-text="labels.receipt_hint"></div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>
</body>
</html>
