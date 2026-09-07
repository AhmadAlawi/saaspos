<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.google-analytics')
    <title>{{ __('pay.title') }} — {{ $company->name ?? config('app.name') }}</title>
    @vite(['resources/css/pay.css', 'resources/js/pay-wallet.js'])
    @php
        $merchantName = $company->name ?? config('app.name');
        $merchantInitials = collect(explode(' ', $merchantName))
            ->filter()->take(2)
            ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
            ->implode('');
        $walletTitle = $walletBrand === 'apple_pay'
            ? __('pay.wallet.apple_pay_title')
            : __('pay.wallet.google_pay_title');
    @endphp
</head>
<body class="pos-theme pay-body">
    <main class="pay-shell">

        {{-- Merchant + amount card — identical to pay/pos.blade.php. --}}
        <section class="pay-card pay-card-merchant">
            <header class="pay-merchant">
                <div class="pay-merchant-avatar">{{ $merchantInitials ?: '·' }}</div>
                <div class="pay-merchant-meta">
                    <div class="pay-merchant-name">{{ $merchantName }}</div>
                    <div class="pay-merchant-sub">{{ __('pay.secure') }}</div>
                </div>
            </header>

            <div class="pay-amount-block">
                <div class="pay-amount-eyebrow">{{ __('pay.you_are_paying') }}</div>
                <div class="pay-amount">{{ format_money($session->amount) }}</div>
            </div>
        </section>

        {{-- Order summary — identical block to pay/pos.blade.php. --}}
        @php
            $cs     = $session->cart_summary ?? [];
            $lines  = $cs['lines']  ?? (array_is_list($cs) ? $cs : []);
            $totals = $cs['totals'] ?? null;
            $trimPct = fn ($r) => rtrim(rtrim(number_format((float) $r, 2, '.', ''), '0'), '.');
        @endphp
        @if (! empty($lines))
            <section class="pay-card pay-card-cart">
                <div class="pay-section-title">{{ __('pay.order_summary') }}</div>

                <ul class="pay-cart-list">
                    @foreach ($lines as $item)
                        <li class="pay-cart-row">
                            <div class="pay-cart-row-main">
                                <span class="pay-cart-name">{{ $item['name'] }}</span>
                                <span class="pay-cart-qty">{{ $item['quantity'] }} &times; {{ format_money($item['unit_price'] ?? 0) }}</span>

                                @if (! empty($item['kit_items']))
                                    <ul class="pay-cart-kit">
                                        @foreach ($item['kit_items'] as $k)
                                            <li>
                                                {{ $k['quantity'] }}&times; {{ $k['name'] }}@if (! empty($k['variant_label'])) <span class="pay-cart-kit-variant">({{ $k['variant_label'] }})</span>@endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif

                                @if (($item['tax_taxable'] ?? true) && (float) ($item['tax_amount'] ?? 0) > 0)
                                    <span class="pay-cart-tax-note">
                                        {{ __('pay.line_tax', [
                                            'rate'   => $trimPct($item['tax_rate'] ?? 0),
                                            'amount' => format_money($item['tax_amount']),
                                        ]) }}
                                    </span>
                                @endif
                            </div>
                            <span class="pay-cart-line-total">{{ format_money($item['line_total'] ?? 0) }}</span>
                        </li>
                    @endforeach
                </ul>

                @if ($totals)
                    <div class="pay-cart-breakdown">
                        <div class="pay-cart-brow">
                            <span>{{ __('pay.subtotal') }}</span>
                            <span>{{ format_money($totals['subtotal'] ?? 0) }}</span>
                        </div>

                        @if (! empty($totals['discount_type']) && (float) ($totals['discount_amount'] ?? 0) > 0)
                            <div class="pay-cart-brow pay-cart-brow-disc">
                                <span>
                                    {{ __('pay.discount') }}@if ($totals['discount_type'] === 'pct') ({{ $trimPct($totals['discount_value'] ?? 0) }}%)@endif
                                </span>
                                <span>&minus;{{ format_money($totals['discount_amount']) }}</span>
                            </div>
                        @endif

                        @if ((float) ($totals['tax_total'] ?? 0) > 0)
                            <div class="pay-cart-brow pay-cart-brow-muted">
                                <span>{{ __('pay.tax_included') }}</span>
                                <span>{{ format_money($totals['tax_total']) }}</span>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="pay-cart-total">
                    <span>{{ __('pay.total') }}</span>
                    <span>{{ format_money($totals['grand_total'] ?? $session->amount) }}</span>
                </div>
            </section>
        @endif

        {{-- Live-status root — same polling contract as pay/pos.blade.php,
             reused by pay-wallet.js so the page reacts if the cashier
             cancels or the session expires while this is open. --}}
        <div data-pay-status-url="{{ route('pay.pos.status', $session->uuid) }}" hidden></div>

        {{-- Wallet button mount — Stripe.js decides at runtime whether
             Apple Pay or Google Pay (or neither) is available on this
             device; `$walletBrand` only picks the headline copy, it
             doesn't force which wallet Stripe offers (there's no such
             restriction in Stripe's API — see pay-wallet.js). --}}
        <section class="pay-card pay-card-methods"
                 data-wallet-root
                 data-client-secret="{{ $clientSecret }}"
                 data-publishable-key="{{ $publishableKey }}"
                 data-currency="{{ $session->currency }}"
                 data-amount-minor="{{ $amountMinor }}"
                 data-country="{{ $session->store?->country_code ?? 'US' }}"
                 data-merchant-name="{{ $merchantName }}"
                 data-select-url="{{ route('pay.pos.select', $session->uuid) }}"
                 data-method-id="{{ $method->id }}">
            <div class="pay-section-title">{{ $walletTitle }}</div>

            <div id="payment-request-button" data-wallet-loading>{{ __('pay.wallet.loading') }}</div>

            <p class="pay-card-footnote" data-wallet-unavailable hidden>
                {{ __('pay.wallet.unavailable') }}
            </p>

            <form method="POST" action="{{ route('pay.pos.select', $session->uuid) }}" data-wallet-fallback hidden>
                @csrf
                <input type="hidden" name="payment_method_id" value="{{ $method->id }}">
                <button type="submit" class="pay-method-btn">
                    <span class="pay-method-meta">
                        <span class="pay-method-name">{{ __('pay.wallet.fallback_link') }}</span>
                    </span>
                    <span class="pay-method-arrow" aria-hidden="true">→</span>
                </button>
            </form>

            <p class="pay-card-footnote">{{ __('pay.auto_confirm') }}</p>
        </section>

    </main>
</body>
</html>
