<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      dir="{{ in_array(app()->getLocale(), ['ar', 'he', 'ur', 'fa']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="pos-theme-default" content="{{ $themeDefault }}">
    <title>{{ __('kiosk.title') }} — {{ $appName }}</title>

    @php
        // Currency registry for posFormatMoney() — same shape the admin +
        // cashier layouts emit, so the kiosk's client-side totals preview
        // formats exactly like every other amount in the app.
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

    {{-- No-flash theme bootstrap — match the till's saved theme before first
         paint so the customer never sees a light→dark flip. --}}
    <script>
        (function () {
            try {
                var t = localStorage.getItem('pos_theme')
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

    @vite(['resources/css/kiosk/kiosk.css', 'resources/js/kiosk/kiosk-app.js'])

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
    <div class="kiosk"
         x-data="kioskApp({{ Js::from($bootstrap) }})"
         x-cloak
         @pointerdown="bump()">

        {{-- ══════════════ ATTRACT ══════════════ --}}
        <section class="k-attract" x-show="state === 'attract'" @click="start()">
            <div class="k-attract-inner">
                <div class="k-brand">
                    @if ($appLogoUrl || $appLogoDarkUrl)
                        @if ($appLogoUrl)
                            <img src="{{ $appLogoUrl }}" alt="" class="k-logo-img{{ $appLogoDarkUrl ? ' dark:hidden' : '' }}">
                        @endif
                        @if ($appLogoDarkUrl)
                            <img src="{{ $appLogoDarkUrl }}" alt="" class="k-logo-img hidden dark:block">
                        @endif
                    @else
                        <span class="k-logo">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($storeName, 0, 1)) }}</span>
                    @endif
                    <div class="k-brand-text">
                        <div class="k-store">{{ $storeName }}</div>
                        <div class="k-status"><span class="k-dot"></span>{{ __('kiosk.status.open') }}</div>
                    </div>
                </div>

                <div class="k-hero">
                    <div class="k-hero-title" x-text="welcome"></div>
                    <div class="k-hero-sub" x-text="labels.tagline"></div>
                </div>

                {{-- Attract slideshow (Slice 5) — merchant promo images crossfade --}}
                <template x-if="hasAttract">
                    <div class="k-attract-media">
                        <div class="k-attract-stage">
                            <template x-for="(url, i) in attract.media" :key="i">
                                <img class="k-attract-slide" :src="url" alt="" :style="`opacity:${i === promoIx ? 1 : 0}`">
                            </template>
                            <div class="k-attract-dots" x-show="attract.media.length > 1">
                                <template x-for="(url, i) in attract.media" :key="'d' + i">
                                    <i :class="i === promoIx && 'on'"></i>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>

                <button class="k-cta k-cta-pulse">
                    <x-icon name="pos" class="w-6 h-6" />
                    <span x-text="labels.start"></span>
                </button>
            </div>
        </section>

        {{-- ══════════════ BROWSE ══════════════ --}}
        <section class="k-browse" x-show="state === 'browse'">
            {{-- Two ways to reach the exit prompt: the close button, and a 3s
                 press-and-hold on the brand mark (which still works when the
                 station is locked into a fullscreen kiosk browser). Both open
                 the SAME supervisor-authenticated prompt — turn on
                 `supervisor_pin_required` on the terminal so a customer who
                 taps the X can't actually get out. See
                 docs/features/kiosk-self-ordering.md §6. --}}
            <header class="k-head">
                <div class="k-head-brand k-exit-hold"
                     :class="holding && 'is-holding'"
                     @pointerdown="startExitHold()"
                     @pointerup="cancelExitHold()"
                     @pointerleave="cancelExitHold()"
                     @pointercancel="cancelExitHold()"
                     @contextmenu.prevent>
                    @if ($appLogoUrl || $appLogoDarkUrl)
                        @if ($appLogoUrl)
                            <img src="{{ $appLogoUrl }}" alt="" class="k-logo-img k-logo-img-sm{{ $appLogoDarkUrl ? ' dark:hidden' : '' }}">
                        @endif
                        @if ($appLogoDarkUrl)
                            <img src="{{ $appLogoDarkUrl }}" alt="" class="k-logo-img k-logo-img-sm hidden dark:block">
                        @endif
                    @else
                        <span class="k-logo k-logo-sm">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($storeName, 0, 1)) }}</span>
                    @endif
                    <div class="k-brand-text">
                        <div class="k-store">{{ $storeName }}</div>
                        <div class="k-substore">{{ __('kiosk.head.subtitle') }}</div>
                    </div>
                    <span class="k-hold-bar" x-show="holding" x-cloak aria-hidden="true"><i></i></span>
                </div>
                <x-lang-toggle button-class="k-icon-btn" />

                <button class="k-icon-btn" @click="exitOpen = true" :aria-label="labels.exit_title">
                    <x-icon name="x" class="w-5 h-5" />
                </button>
            </header>

            <div class="k-search-bar">
                <div class="k-search">
                    <span class="k-search-ic"><x-icon name="search" class="w-5 h-5" /></span>
                    <input type="text" x-model="search" :placeholder="labels.search" class="k-search-input"
                           x-ref="searchInput" autocomplete="off">
                </div>
            </div>

            <div class="k-cats">
                <button class="k-cat" :class="selectedCat === null && 'is-active'" @click="selectedCat = null" x-text="labels.all"></button>
                <template x-for="c in visibleCategories" :key="c.id">
                    <button class="k-cat" :class="selectedCat === c.id && 'is-active'" @click="selectedCat = c.id" x-text="c.name"></button>
                </template>
            </div>

            <div class="k-grid-wrap">
                {{-- Loading / offline-cold states --}}
                <template x-if="loading">
                    <div class="k-note" x-text="labels.loading"></div>
                </template>
                <template x-if="!loading && !products.length">
                    <div class="k-note" x-text="labels.offline"></div>
                </template>

                <div class="k-grid" x-show="!loading && products.length">
                    <template x-for="p in filtered" :key="p.id">
                        <button class="k-tile" @click="tileTap(p)">
                            <span class="k-thumb">
                                <template x-if="p.image_url"><img :src="p.image_url" alt="" loading="lazy"></template>
                                <template x-if="!p.image_url"><span class="k-thumb-ph" x-text="p.name.slice(0,1).toUpperCase()"></span></template>
                                <template x-if="p.is_kit">
                                    <span class="k-opts-badge k-combo-badge" x-text="labels.combo"></span>
                                </template>
                                <template x-if="!p.is_kit && p.has_variants">
                                    <span class="k-opts-badge" x-text="labels.options.replace(':count', p.variant_count)"></span>
                                </template>
                            </span>
                            <div class="k-tile-name" x-text="p.name"></div>
                            {{-- Combo tiles preview the bundle contents right on the card, McDonald's-meal style. --}}
                            <template x-if="p.is_kit && p.kit_items && p.kit_items.length">
                                <div class="k-tile-kit" x-text="p.kit_items.map(k => k.name).join(' · ')"></div>
                            </template>
                            <div class="k-tile-foot">
                                <span class="k-price-group">
                                    <template x-if="tileWasLabel(p)">
                                        <span class="k-price-was" x-text="tileWasLabel(p)"></span>
                                    </template>
                                    <span class="k-price" x-text="tilePrice(p)"></span>
                                </span>
                                <span class="k-add">
                                    <template x-if="p.is_kit || p.has_variants"><x-icon name="chevron" class="w-5 h-5 rotate-90" /></template>
                                    <template x-if="!p.is_kit && !p.has_variants"><x-icon name="plus" class="w-5 h-5" /></template>
                                </span>
                            </div>
                        </button>
                    </template>
                </div>

                <template x-if="!loading && products.length && !filtered.length">
                    <div class="k-note" x-text="labels.empty_search"></div>
                </template>
            </div>

            <div class="k-cartbar" x-show="cart.length" x-transition.opacity x-cloak>
                <button class="k-cta k-cta-bar" @click="state = 'cart'">
                    <span class="k-cta-left">
                        <span class="k-count" x-text="itemCount"></span>
                        <span x-text="labels.view_cart"></span>
                    </span>
                    <span x-text="money(total)"></span>
                </button>
            </div>
        </section>

        {{-- ══════════════ CART ══════════════ --}}
        <section class="k-cartview" x-show="state === 'cart'">
            <header class="k-head">
                <button class="k-back" @click="state = 'browse'">
                    <x-icon name="chevron" class="w-5 h-5 -rotate-90 rtl:rotate-90" />
                    <span x-text="labels.keep_shopping"></span>
                </button>
                <div class="k-head-title" x-text="labels.your_order"></div>
                <span class="k-head-spacer"></span>
            </header>

            <div class="k-cart-body">
                <div class="k-lines-col">
                    <template x-if="!cart.length">
                        <div class="k-empty">
                            <span class="k-empty-ic"><x-icon name="pos" class="w-7 h-7" /></span>
                            <div class="k-empty-txt" x-text="labels.empty_cart"></div>
                            <button class="k-btn" @click="state = 'browse'" x-text="labels.start_shopping"></button>
                        </div>
                    </template>

                    <ul class="k-lines" x-show="cart.length">
                        <template x-for="line in cart" :key="line.lid">
                            <li class="k-line">
                                <span class="k-line-thumb">
                                    <template x-if="line.image_url"><img :src="line.image_url" alt=""></template>
                                    <template x-if="!line.image_url"><span class="k-thumb-ph" x-text="line.name.slice(0,1).toUpperCase()"></span></template>
                                </span>
                                <div class="k-line-main">
                                    <div class="k-line-name" x-text="line.name"></div>
                                    <div class="k-line-each" x-text="money(line.price) + ' ' + labels.each"></div>
                                </div>
                                <div class="k-step">
                                    <button @click="dec(line)" :aria-label="'−'"><x-icon name="minus" class="w-5 h-5" /></button>
                                    <span class="k-step-val" x-text="line.qty"></span>
                                    <button @click="inc(line)" :aria-label="'+'"><x-icon name="plus" class="w-5 h-5" /></button>
                                </div>
                                <div class="k-line-amt" x-text="money(line.price * line.qty)"></div>
                                <button class="k-line-del" @click="remove(line)" aria-label="remove"><x-icon name="trash" class="w-5 h-5" /></button>
                            </li>
                        </template>
                    </ul>
                </div>

                {{-- The rail carries everything that isn't a cart line: totals,
                     the shopper's details, and the pay actions. Keeping the
                     details out of the line column means the list never gets
                     pushed off-screen by a form, and the rail — which used to
                     be mostly empty space — earns its 420px. --}}
                <aside class="k-summary" x-show="cart.length">
                    <div class="k-summary-scroll">
                        <div class="k-summary-head">
                            <span x-text="labels.your_order"></span>
                            <span class="k-summary-count tnum" x-text="itemCount"></span>
                        </div>
                        <div class="k-summary-rows">
                            <div class="k-summary-row"><span x-text="labels.subtotal"></span><span class="tnum" x-text="money(subtotal)"></span></div>
                            <div class="k-summary-row"><span x-text="labels.tax"></span><span class="tnum" x-text="money(tax)"></span></div>
                        </div>
                        <div class="k-summary-divider"></div>
                        <div class="k-summary-total">
                            <span x-text="labels.total"></span>
                            <span class="k-total-amt tnum" x-text="money(total)"></span>
                        </div>

                        <div class="k-cart-panel" x-show="customerMode !== 'off' || allowNote" x-cloak>
                            {{-- Customer details prompt (Slice 5) — off | optional | required --}}
                            <div class="k-cust-field" x-show="customerMode !== 'off'">
                                <div class="field-label">
                                    <span x-text="labels.cust_title"></span>
                                    <span x-show="customerMode === 'required'" class="k-req" x-text="'*'"></span>
                                </div>
                                <div class="k-cust-inputs">
                                    <input type="text" class="pos-input" x-model="custName" :placeholder="labels.cust_name" maxlength="120">
                                    <input type="tel" class="pos-input" x-model="custPhone" :placeholder="labels.cust_phone" maxlength="32">
                                </div>
                            </div>

                            <div class="k-note-field" x-show="allowNote">
                                <label class="field">
                                    <span class="field-label" x-text="labels.note"></span>
                                    <input type="text" class="pos-input" x-model="note" :placeholder="labels.note_hint" maxlength="255">
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Checkout (pay-at-kiosk) and/or place-order (pay-at-counter)
                         actions. In `both` mode the customer is shown both and
                         picks the path they want. --}}
                    <div class="k-summary-actions">
                        <template x-if="mode === 'both'">
                            <div class="k-pay-choice" x-text="labels.pay_choice"></div>
                        </template>
                        <template x-if="showCheckout">
                            <button class="k-pay-btn k-pay-btn-primary" @click="startCheckout()">
                                <span class="k-pay-btn-ic"><x-icon name="card" class="w-5 h-5" /></span>
                                <span class="k-pay-btn-label" x-text="labels.pay_now"></span>
                                <span class="k-pay-btn-amt tnum" x-text="money(total)"></span>
                            </button>
                        </template>
                        {{-- Static UPI QR. Placed, not charged — see the `upi` screen. --}}
                        <template x-if="showUpi">
                            <button class="k-pay-btn k-pay-btn-ghost" @click="startUpi()">
                                <span class="k-pay-btn-ic"><x-icon name="qr-code" class="w-5 h-5" /></span>
                                <span class="k-pay-btn-label" x-text="labels.upi_pay.replace(':method', upi.name)"></span>
                                <span class="k-pay-btn-amt tnum" x-text="money(total)"></span>
                            </button>
                        </template>
                        <template x-if="showOrder">
                            <button class="k-pay-btn"
                                    :class="(mode === 'both' || showUpi) ? 'k-pay-btn-ghost' : 'k-pay-btn-primary'"
                                    @click="placeOrder()" :disabled="placing">
                                <span class="k-pay-btn-ic">
                                    <svg x-show="placing" x-cloak class="w-5 h-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                                    </svg>
                                    <template x-if="!placing"><x-icon name="receipt" class="w-5 h-5" /></template>
                                </span>
                                <span class="k-pay-btn-label" x-text="placing ? labels.placing : labels.pay_counter"></span>
                                <span class="k-pay-btn-amt tnum" x-show="mode !== 'both'" x-text="money(total)"></span>
                            </button>
                        </template>
                    </div>
                </aside>
            </div>
        </section>

        {{-- ══════════════ PAYMENT (checkout mode) ══════════════ --}}
        <section class="k-payment" x-show="state === 'payment'">
            <header class="k-head">
                <button class="k-back" @click="cancelCheckout()" x-show="payStatus !== 'paid'">
                    <x-icon name="chevron" class="w-5 h-5 -rotate-90 rtl:rotate-90" />
                    <span x-text="labels.pay_cancel"></span>
                </button>
                <div class="k-head-title" x-text="labels.pay_amount"></div>
                <span class="k-head-spacer"></span>
            </header>

            <div class="k-pay-body">
                <div class="k-pay-amount tnum" x-text="money(payAmount)"></div>

                <template x-if="payStatus === 'starting'">
                    <div class="k-note" x-text="labels.pay_starting"></div>
                </template>

                <template x-if="payQr && payStatus !== 'starting'">
                    <div class="k-pay-qr-wrap">
                        <div class="k-pay-qr"><img :src="payQr" alt=""></div>
                        <div class="k-pay-scan" x-text="labels.pay_scan"></div>
                        <div class="k-pay-hint" x-text="labels.pay_scan_hint"></div>
                        <div class="k-pay-waiting">
                            <span class="k-spinner"></span>
                            <span x-text="labels.pay_waiting"></span>
                        </div>
                    </div>
                </template>
            </div>
        </section>

        {{-- ══════════════ STATIC UPI QR ══════════════
             The shopper scans with their own UPI app and pays. Nothing calls
             back to tell us the transfer landed, so "I've paid" does NOT
             complete a sale — it places the order with a claim that staff
             verify at the counter. See docs/features/kiosk-self-ordering.md §5. --}}
        <section class="k-payment" x-show="state === 'upi'" x-cloak>
            <header class="k-head">
                <button class="k-back" @click="cancelUpi()" :disabled="upiClaiming">
                    <x-icon name="chevron" class="w-5 h-5 -rotate-90 rtl:rotate-90" />
                    <span x-text="labels.upi_back"></span>
                </button>
                <div class="k-head-title" x-text="labels.pay_amount"></div>
                <span class="k-head-spacer"></span>
            </header>

            <div class="k-pay-body">
                <div class="k-pay-amount tnum" x-text="money(total)"></div>

                <div class="k-pay-qr-wrap">
                    <div class="k-pay-qr"><img :src="upiQr" alt=""></div>
                    <div class="k-pay-scan" x-text="labels.upi_scan"></div>
                    <div class="k-pay-hint" x-text="labels.upi_hint"></div>
                </div>

                <button class="k-pay-btn k-pay-btn-primary k-upi-confirm"
                        @click="confirmUpiPaid()" :disabled="upiClaiming || placing">
                    <span class="k-pay-btn-ic">
                        <svg x-show="upiClaiming" x-cloak class="w-5 h-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                        </svg>
                        <template x-if="!upiClaiming"><x-icon name="check" class="w-5 h-5" /></template>
                    </span>
                    <span class="k-pay-btn-label" x-text="upiClaiming ? labels.placing : labels.upi_paid"></span>
                </button>
            </div>
        </section>

        {{-- ══════════════ THANK YOU (order placed) ══════════════ --}}
        <section class="k-thankyou" x-show="state === 'thankyou'" @click="tapThankyou()">
            <div class="k-ty-inner">
                <div class="k-ty-tick">
                    <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 6 9 17l-5-5"/>
                    </svg>
                </div>
                {{-- Order placed offline → saved-locally message (no server
                     pickup code yet; it syncs to the counter queue when the
                     connection returns). --}}
                <template x-if="savedOffline">
                    <div class="k-ty-block">
                        <div class="k-ty-title" x-text="labels.ty_offline"></div>
                        <div class="k-ty-sub" x-text="labels.ty_offline_sub"></div>
                    </div>
                </template>
                {{-- Order mode online → pickup code. Checkout mode → receipt QR.
                     A UPI claim reads differently: the shopper has paid, but a
                     human still has to confirm the transfer landed. --}}
                <template x-if="!receiptQr && !savedOffline">
                    <div class="k-ty-block">
                        <div class="k-ty-title" x-text="upiClaimed ? labels.ty_upi : labels.ty_placed"></div>
                        <div class="k-ty-pickup-label" x-text="labels.ty_pickup"></div>
                        <div class="k-ty-code" x-text="pickupCode"></div>
                        <div class="k-ty-sub" x-text="upiClaimed ? labels.ty_upi_sub : labels.ty_sub"></div>
                    </div>
                </template>
                {{-- Paid at the kiosk. The money is settled, but the goods are
                     still behind the counter — so the pickup code leads, and the
                     receipt QR (for the shopper's own records) follows it. --}}
                <template x-if="receiptQr">
                    <div class="k-ty-block">
                        <div class="k-ty-title" x-text="labels.ty_paid"></div>
                        <template x-if="pickupCode">
                            <div class="k-ty-pickup">
                                <div class="k-ty-pickup-label" x-text="labels.ty_pickup"></div>
                                <div class="k-ty-code k-ty-code-sm" x-text="pickupCode"></div>
                                <div class="k-ty-sub" x-text="labels.ty_collect"></div>
                            </div>
                        </template>
                        <div class="k-ty-receipt-qr"><img :src="receiptQr" alt=""></div>
                        <div class="k-ty-pickup-label" x-text="labels.ty_receipt"></div>
                        <div class="k-ty-sub" x-text="labels.ty_receipt_hint"></div>
                    </div>
                </template>
                <button class="k-btn k-btn-primary mt-2" @click.stop="finishThankyou()" x-text="labels.ty_done"></button>
            </div>
        </section>

        {{-- Variant picker — a customer taps a product with options and
             chooses one; the chosen variant carries its own price into the
             cart. The kiosk never sends prices, so this is display-only. --}}
        <div class="k-modal-scrim" x-show="variantPicker" x-transition.opacity @click.self="closeVariant()" x-cloak>
            <div class="k-modal k-variant-modal" x-show="variantPicker">
                <div class="k-variant-head">
                    <div class="k-modal-title" x-text="variantPicker?.name"></div>
                    <div class="k-modal-body" x-text="labels.choose_option"></div>
                </div>
                <ul class="k-variant-list">
                    <template x-for="v in (variantPicker?.variants || [])" :key="v.id">
                        <li>
                            <button class="k-variant"
                                    :disabled="(Number(v.charge_price ?? v.selling_price) || 0) <= 0"
                                    @click="pickVariant(variantPicker, v)">
                                <span class="k-variant-thumb">
                                    <template x-if="v.image_url"><img :src="v.image_url" alt=""></template>
                                    <template x-if="!v.image_url"><span class="k-thumb-ph" x-text="(v.label || variantPicker.name).slice(0,1).toUpperCase()"></span></template>
                                </span>
                                <span class="k-variant-label" x-text="v.label || v.sku"></span>
                                <span class="k-variant-price tnum">
                                    <template x-if="v.sale_price">
                                        <span class="k-variant-price-was" x-text="money(v.selling_price)"></span>
                                    </template>
                                    <span x-text="money(v.charge_price ?? v.selling_price)"></span>
                                </span>
                                <span class="k-add"><x-icon name="plus" class="w-5 h-5" /></span>
                            </button>
                        </li>
                    </template>
                </ul>
                <div class="k-modal-actions k-variant-actions">
                    <button class="k-btn" @click="closeVariant()" x-text="labels.keep_shopping"></button>
                </div>
            </div>
        </div>

        {{-- Kit / combo details — the customer taps a bundle and sees exactly
             what's inside (McDonald's-meal style) before adding it. The kit is
             one line at its own price; the component list is display-only. --}}
        <div class="k-modal-scrim" x-show="kitDetail" x-transition.opacity @click.self="closeKit()" x-cloak>
            <div class="k-modal k-kit-modal" x-show="kitDetail">
                <div class="k-kit-hero">
                    <span class="k-kit-thumb">
                        <template x-if="kitDetail?.image_url"><img :src="kitDetail.image_url" alt=""></template>
                        <template x-if="!kitDetail?.image_url"><span class="k-thumb-ph" x-text="(kitDetail?.name || '').slice(0,1).toUpperCase()"></span></template>
                        <span class="k-opts-badge k-combo-badge" x-text="labels.combo"></span>
                    </span>
                    <div class="k-kit-herometa">
                        <div class="k-modal-title" x-text="kitDetail?.name"></div>
                        <div class="k-kit-price tnum">
                            <template x-if="kitDetail?.sale_price">
                                <span class="k-variant-price-was" x-text="money(kitDetail?.selling_price)"></span>
                            </template>
                            <span x-text="money(kitDetail?.charge_price ?? kitDetail?.selling_price)"></span>
                        </div>
                    </div>
                </div>

                <div class="k-kit-included" x-text="labels.whats_included"></div>
                <ul class="k-kit-list">
                    <template x-for="(k, i) in (kitDetail?.kit_items || [])" :key="i">
                        <li class="k-kit-row">
                            <span class="k-kit-qty" x-text="qty(k.quantity) + '×'"></span>
                            <span class="k-kit-name">
                                <span x-text="k.name"></span>
                                <span class="k-kit-variant" x-show="k.variant_label" x-text="k.variant_label"></span>
                            </span>
                        </li>
                    </template>
                </ul>

                <div class="k-modal-actions k-kit-actions">
                    <button class="k-btn" @click="closeKit()" x-text="labels.keep_shopping"></button>
                    <button class="k-btn k-btn-primary" @click="addKit(kitDetail)">
                        <x-icon name="plus" class="w-5 h-5" />
                        <span x-text="labels.add_combo"></span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Staff exit prompt — supervisor re-auth when the terminal requires it (Slice 5) --}}
        <div class="k-modal-scrim" x-show="exitOpen" x-transition.opacity @click.self="exitOpen = false" x-cloak>
            <div class="k-modal">
                <span class="k-modal-ic"><x-icon name="lock" class="w-6 h-6" /></span>
                <div class="k-modal-title" x-text="labels.exit_title"></div>
                <div class="k-modal-body" x-text="labels.exit_body"></div>

                <template x-if="pinRequired">
                    <div class="k-exit-form">
                        <input type="email" class="pos-input" x-model="exitEmail" :placeholder="labels.exit_email" autocomplete="off">
                        <input type="password" class="pos-input" x-model="exitPassword" :placeholder="labels.exit_password" autocomplete="off">
                        <div class="k-exit-error" x-show="exitError" x-text="exitError"></div>
                    </div>
                </template>

                <div class="k-modal-actions">
                    <button class="k-btn" @click="exitOpen = false; exitError = ''" x-text="labels.exit_stay"></button>
                    <button class="k-btn k-btn-primary" @click="exitKiosk()" x-text="labels.exit_leave"></button>
                </div>
            </div>
        </div>

        {{-- Transient toast. A failure must not wear a green tick — the shopper
             reads the icon before the words. --}}
        <div class="k-toast" :class="toastKind === 'error' && 'k-toast--error'"
             x-show="toast" x-transition.opacity x-cloak>
            <template x-if="toastKind !== 'error'"><x-icon name="check" class="w-5 h-5" /></template>
            <template x-if="toastKind === 'error'"><x-icon name="alert" class="w-5 h-5" /></template>
            <span x-text="toast"></span>
        </div>
    </div>
</body>
</html>
