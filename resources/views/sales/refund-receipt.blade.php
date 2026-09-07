@php
    // `$public` — mirrors sales.receipt's flag; unused today (there's no
    // no-login refund viewer yet) but kept for parity if one gets added.
    $public = $public ?? false;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('sales.refund.receipt_title', ['number' => $saleReturn->number]) }}</title>
    <style>{!! file_get_contents(resource_path('css/receipt.css')) !!}</style>
</head>
<body class="receipt-body receipt-paper-{{ $paper }}">

    <div class="receipt-toolbar" role="toolbar">
        @unless ($public)
            <a href="{{ url()->previous() }}" class="receipt-tool-btn">← {{ __('sales.receipt.back') }}</a>
        @endunless
        <span class="receipt-tool-spacer"></span>
        <button type="button" class="receipt-tool-btn" onclick="window.print()">🖨 {{ __('sales.receipt.print') }}</button>
    </div>

    <div class="receipt {{ $paper === 'a4' ? 'receipt-a4' : 'receipt-thermal' }}">
        @if ($company->receipt_show_logo && $company->logo_url)
            <div class="rcpt-logo-img"><img src="{{ $company->logo_url }}" alt=""></div>
        @elseif ($company->receipt_show_logo)
            <div class="rcpt-logo">{{ $company->display_app_name }}</div>
        @endif

        <div class="rcpt-store">
            <div class="rcpt-store-name">{{ $saleReturn->store->name }}</div>
            @foreach ($saleReturn->store->address_lines as $line)
                <div class="rcpt-store-addr">{{ $line }}</div>
            @endforeach
            @if ($saleReturn->store->phone)
                <div class="rcpt-store-addr">{{ $saleReturn->store->phone }}</div>
            @endif
        </div>

        {{-- "REFUND" banner so it's unmistakable at a glance vs. a normal
             sale receipt, even before reading the number. --}}
        <div class="rcpt-header rcpt-refund-banner">{{ __('sales.refund.receipt_banner') }}</div>

        <div class="rcpt-meta">
            <div><strong>{{ $saleReturn->number }}</strong></div>
            <div>{{ format_datetime($saleReturn->created_at) }}</div>
        </div>

        @if ($saleReturn->sale)
            <div class="rcpt-line">{{ __('sales.refund.receipt_for_sale') }}: {{ $saleReturn->sale->number }}</div>
        @else
            <div class="rcpt-line">{{ __('sales.refund.receipt_no_invoice') }}</div>
        @endif
        @if ($company->receipt_show_cashier && $saleReturn->cashier)
            <div class="rcpt-line">{{ __('sales.receipt.cashier') }}: {{ $saleReturn->cashier->name }}</div>
        @endif
        <div class="rcpt-line">{{ __('sales.refund.receipt_reason') }}: {{ $saleReturn->reason?->name }}</div>

        <div class="rcpt-rule"></div>

        {{-- Items — ONLY the refunded lines, never the rest of the
             original sale. Works for both kinds of line: a sale-linked
             one falls back to its live product/variant; a blind one
             reads its own snapshot columns (no sale_item_id to follow). --}}
        <div class="rcpt-items">
            @foreach ($saleReturn->items as $item)
                <div class="rcpt-item">
                    <span class="rcpt-item-name">
                        {{ $item->name_snapshot ?: ($item->saleItem?->product_name_snapshot ?: $item->saleItem?->product?->name) }}
                        @php $variantLabel = $item->variant?->label ?: $item->saleItem?->variant?->label; @endphp
                        @if ($variantLabel)
                            <span class="rcpt-item-variant">· {{ $variantLabel }}</span>
                        @endif
                    </span>
                    @php $sku = $item->sku_snapshot ?: ($item->saleItem?->sku_snapshot ?: $item->saleItem?->product?->sku); @endphp
                    @if ($company->receipt_show_sku && $sku)
                        <span class="rcpt-item-code">{{ __('sales.receipt.sku') }}: {{ $sku }}</span>
                    @endif
                    <span class="rcpt-item-qty">{{ rtrim(rtrim((string) $item->quantity, '0'), '.') }} × {{ format_money($item->unit_price_snapshot) }}</span>
                    <span class="rcpt-item-total">{{ format_money($item->line_total) }}</span>
                    @if ((float) $item->tax_amount > 0)
                        <span class="rcpt-item-tax">{{ __('sales.receipt.tax') }} {{ format_money($item->tax_amount) }}</span>
                    @endif
                    @if ($item->notes)
                        <span class="rcpt-item-note">{{ $item->notes }}</span>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="rcpt-rule"></div>

        <div class="rcpt-totals">
            <div><span>{{ __('sales.refund.receipt_subtotal') }}</span><span>{{ format_money($saleReturn->subtotal) }}</span></div>

            @if ((float) $saleReturn->tax_total > 0)
                <div><span>{{ __('sales.refund.receipt_tax') }}</span><span>{{ format_money($saleReturn->tax_total) }}</span></div>
            @endif

            <div class="rcpt-total"><span>{{ __('sales.refund.receipt_grand') }}</span><span>{{ format_money($saleReturn->grand_total) }}</span></div>

            <div>
                <span>{{ __('sales.refund.receipt_method') }}</span>
                <span>{{ $saleReturn->refundMethod?->name ?: __('sales.refund.receipt_method_cash') }}</span>
            </div>
        </div>

        <div class="rcpt-rule"></div>

        @if (trim((string) $company->receipt_footer) !== '')
            <div class="rcpt-footer">{{ $company->receipt_footer }}</div>
        @endif
    </div>

    @if ($auto)
        <script data-auto-print>
            window.addEventListener('load', () => setTimeout(() => window.print(), 250));
        </script>
    @endif
</body>
</html>
