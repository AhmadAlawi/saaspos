<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.google-analytics')
    <title>{{ __('pay.title') }} — {{ $company->name ?? config('app.name') }}</title>
    @vite(['resources/css/pay.css', 'resources/js/pay.js'])
    @php
        /* Per-provider visual brand for the chooser tile.
         * `tone` drives the avatar background — values map to CSS
         * classes below so theming stays in one place. */
        $brand = [
            'stripe'       => ['initials' => 'ST', 'tone' => 'violet'],
            'razorpay'     => ['initials' => 'RA', 'tone' => 'blue'],
            'paystack'     => ['initials' => 'PA', 'tone' => 'cyan'],
            'flutterwave'  => ['initials' => 'FL', 'tone' => 'orange'],
            'mercado_pago' => ['initials' => 'MP', 'tone' => 'sky'],
        ];

        /* Merchant avatar initials = first letter of each word, max 2.
         * Matches the "CI" avatar in the mockup. */
        $merchantName = $company->name ?? config('app.name');
        $merchantInitials = collect(explode(' ', $merchantName))
            ->filter()->take(2)
            ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
            ->implode('');
    @endphp
</head>
<body class="pos-theme pay-body">
    <main class="pay-shell">

        {{-- Merchant + amount card — mirrors the "City Square Mart"
             header in the mockup. --}}
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

        {{-- Order summary — the cart snapshot the cashier captured when
             generating the QR, so the customer can see what they're
             paying for (items, kit contents, per-line tax, discount,
             totals) before choosing a method. --}}
        @php
            /* Tolerate both the rich {lines, totals} shape and any older
               flat-array snapshot, so a session created before this
               change still renders something sensible. */
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

                                {{-- Kit / bundle contents --}}
                                @if (! empty($item['kit_items']))
                                    <ul class="pay-cart-kit">
                                        @foreach ($item['kit_items'] as $k)
                                            <li>
                                                {{ $k['quantity'] }}&times; {{ $k['name'] }}@if (! empty($k['variant_label'])) <span class="pay-cart-kit-variant">({{ $k['variant_label'] }})</span>@endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif

                                {{-- Per-line tax note --}}
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

                {{-- Subtotal / discount / tax breakdown --}}
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

        @if ($session->isPaid())
            <section class="pay-card pay-card-state pay-state-paid">
                <div class="pay-state-icon">✓</div>
                <div class="pay-state-title">{{ __('pay.already_paid') }}</div>
            </section>
        @elseif ($session->isTerminal() || $session->isExpired())
            <section class="pay-card pay-card-state pay-state-expired">
                {{ __('pay.session_expired') }}
            </section>
        @elseif ($methods->isEmpty())
            <section class="pay-card pay-card-state">
                {{ __('pay.no_methods_configured') }}
            </section>
        @else
            {{-- Live-status root — present only while the session is still
                 payable. pay.js polls pay.pos.status and reloads this page
                 the moment the cashier cancels / it expires / it's paid,
                 so the customer's screen flips to the terminal state on
                 its own. An already-terminal render omits this, so it
                 never polls (no reload loop). --}}
            <div data-pay-status-url="{{ route('pay.pos.status', $session->uuid) }}" hidden></div>

            <section class="pay-card pay-card-methods">
                <div class="pay-section-title">{{ __('pay.choose_method') }}</div>

                <div class="pay-methods">
                    @foreach ($methods as $method)
                        @php
                            $b = $brand[$method->provider] ?? ['initials' => mb_strtoupper(mb_substr($method->name, 0, 2)), 'tone' => 'slate'];
                            $caps = trans()->has('pay.capabilities.' . $method->provider)
                                ? __('pay.capabilities.' . $method->provider)
                                : '';
                        @endphp
                        <form method="POST" action="{{ route('pay.pos.select', $session->uuid) }}">
                            @csrf
                            <input type="hidden" name="payment_method_id" value="{{ $method->id }}">
                            <button type="submit" class="pay-method-btn">
                                <span class="pay-method-avatar pay-tone-{{ $b['tone'] }}">{{ $b['initials'] }}</span>
                                <span class="pay-method-meta">
                                    <span class="pay-method-name">{{ $method->name }}</span>
                                    @if ($caps)
                                        <span class="pay-method-caps">{{ $caps }}</span>
                                    @endif
                                </span>
                                <span class="pay-method-arrow" aria-hidden="true">→</span>
                            </button>
                        </form>
                    @endforeach
                </div>

                @if (($hidden_for_currency ?? collect())->isNotEmpty())
                    <p class="pay-card-warning">
                        {{ __('pay.hidden_for_currency', [
                            'currency' => strtoupper($session->currency),
                            'names'    => $hidden_for_currency->pluck('name')->implode(', '),
                        ]) }}
                    </p>
                @endif

                <p class="pay-card-footnote">{{ __('pay.auto_confirm') }}</p>
            </section>
        @endif

    </main>
</body>
</html>
