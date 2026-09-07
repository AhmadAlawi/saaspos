@php
    // `$public` — rendered by the no-login /r/{token} viewer. Masks the
    // customer's contact details and drops the admin "Back" tool. Defaults
    // false so the admin receipt + thermal print callers are unaffected.
    $public = $public ?? false;
    // `$receiptUrl` — the sale's no-login public-receipt URL, encoded into
    // the QR when `receipt_show_qr` is on. Null when the caller didn't mint
    // a link (e.g. QR toggle off), so the QR block simply doesn't render.
    $receiptUrl = $receiptUrl ?? null;
    // `$template` — a resolved ReceiptTemplate, or null on every install
    // until an admin creates and assigns one (see ResolveReceiptTemplate).
    // Null means: render the original hardcoded markup below, unchanged.
    $template = $template ?? null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('sales.receipt.title', ['number' => $sale->number]) }}</title>
    {{-- Inline the receipt stylesheet rather than going through Vite —
         this is a print-only page rendered occasionally; a 4KB inline
         <style> beats a separate request + manifest dependency. --}}
    <style>{!! file_get_contents(resource_path('css/receipt.css')) !!}</style>
</head>
<body class="receipt-body receipt-paper-{{ $paper }}">

    {{-- Toolbar — visible on screen, hidden when printing. The Print
         button works on every browser; downloads as PDF if the user picks
         "Save as PDF" in their print dialog. --}}
    <div class="receipt-toolbar" role="toolbar">
        @unless ($public)
            <a href="{{ url()->previous() }}" class="receipt-tool-btn">← {{ __('sales.receipt.back') }}</a>
        @endunless
        <span class="receipt-tool-spacer"></span>
        <button type="button" class="receipt-tool-btn" onclick="window.print()">🖨 {{ __('sales.receipt.print') }}</button>
    </div>

    <div class="receipt {{ $paper === 'a4' ? 'receipt-a4' : 'receipt-thermal' }}">
        @if ($template && $template->isCanvas())
            @include('sales.receipt-canvas')
        @elseif ($template)
            @foreach ($template->visibleBlocks() as $block)
                @include('sales.receipt-blocks.'.$block->type, ['block' => $block])
            @endforeach
        @else
        @if ($company->receipt_show_logo && $company->logo_url)
            <div class="rcpt-logo-img"><img src="{{ $company->logo_url }}" alt=""></div>
        @elseif ($company->receipt_show_logo)
            <div class="rcpt-logo">{{ $company->display_app_name }}</div>
        @endif

        {{-- Store identity --}}
        <div class="rcpt-store">
            <div class="rcpt-store-name">{{ $sale->store->name }}</div>
            @foreach ($sale->store->address_lines as $line)
                <div class="rcpt-store-addr">{{ $line }}</div>
            @endforeach
            @if ($sale->store->phone)
                <div class="rcpt-store-addr">{{ $sale->store->phone }}</div>
            @endif
            {{-- Tax registration lives on `company`, not `stores`
                 (single tax-ID per business, not per location). --}}
            @if ($company->tax_registration_number)
                <div class="rcpt-store-tax">{{ __('sales.receipt.gstin') }}: {{ $company->tax_registration_number }}</div>
            @endif
        </div>

        @if (trim((string) $company->receipt_header) !== '')
            <div class="rcpt-header">{{ $company->receipt_header }}</div>
        @endif

        <div class="rcpt-meta">
            <div><strong>{{ $sale->number }}</strong></div>
            <div>{{ format_datetime($sale->sale_datetime ?? $sale->created_at) }}</div>
        </div>

        @if ($company->receipt_show_customer && $sale->customer)
            <div class="rcpt-line">
                {{ __('sales.receipt.customer') }}: {{ $sale->customer->name }}
                {{-- On the public /r/{token} page the phone is masked to
                     the last 4 digits so a shared link never leaks a full
                     contact number (docs/features/whatsapp-receipts.md §11.2). --}}
                @if ($sale->customer->phone)
                    @php $ph = (string) $sale->customer->phone; @endphp
                    · {{ $public && mb_strlen($ph) > 4 ? '••••'.mb_substr($ph, -4) : $ph }}
                @endif
            </div>
        @endif
        @if ($company->receipt_show_cashier && $sale->cashier)
            <div class="rcpt-line">{{ __('sales.receipt.cashier') }}: {{ $sale->cashier->name }}</div>
        @endif

        <div class="rcpt-rule"></div>

        {{-- Items list --}}
        <div class="rcpt-items">
            @foreach ($sale->items as $item)
                <div class="rcpt-item">
                    <span class="rcpt-item-name">
                        {{ $item->product_name_snapshot ?: $item->product?->name }}
                        @if ($item->variant?->label)
                            <span class="rcpt-item-variant">· {{ $item->variant->label }}</span>
                        @endif
                    </span>
                    @if ($company->receipt_show_sku && ($sku = $item->sku_snapshot ?: $item->product?->sku))
                        <span class="rcpt-item-code">{{ __('sales.receipt.sku') }}: {{ $sku }}</span>
                    @endif
                    @if ($company->receipt_show_hsn && ($hsn = $item->hsn_snapshot ?: $item->product?->hsn_code))
                        <span class="rcpt-item-code">{{ __('sales.receipt.hsn') }}: {{ $hsn }}</span>
                    @endif
                    <span class="rcpt-item-qty">{{ rtrim(rtrim($item->quantity, '0'), '.') }} × {{ format_money($item->unit_price) }}</span>
                    <span class="rcpt-item-total">{{ format_money($item->line_total) }}</span>
                    {{-- Per-line tax. "(incl.)" suffix when the tax was
                         extracted from the unit price rather than added
                         on top — read from the line's TaxBreakdown
                         snapshot so the suffix stays correct even if the
                         tax group flips inclusive later. --}}
                    @if ((float) $item->tax_amount > 0)
                        <span class="rcpt-item-tax">
                            {{ __('sales.receipt.tax') }} {{ format_money($item->tax_amount) }}
                            @if (! empty($item->tax_breakdown['is_inclusive']))
                                <span class="rcpt-item-tax-incl">{{ __('sales.totals.tax_incl_suffix') }}</span>
                            @endif
                        </span>
                    @endif
                    @if ($item->batch)
                        <span class="rcpt-item-note">Batch {{ $item->batch->batch_number }}@if ($item->batch->expiry_date) · Exp {{ $item->batch->expiry_date->toDateString() }}@endif</span>
                    @endif
                    @if ($item->notes)
                        <span class="rcpt-item-note">{{ $item->notes }}</span>
                    @endif
                    {{-- Kit components — printed inline so the customer's
                         receipt enumerates what's inside the bundle. --}}
                    @if ($item->product && $item->product->type === 'kit' && $item->product->kitItems->isNotEmpty())
                        @foreach ($item->product->kitItems as $k)
                            <span class="rcpt-item-note rcpt-item-kit">
                                + {{ rtrim(rtrim((string) $k->quantity, '0'), '.') }}× {{ $k->component?->name ?: '—' }}@if ($k->variant?->label) · {{ $k->variant->label }}@endif
                            </span>
                        @endforeach
                    @endif
                </div>
            @endforeach
        </div>

        <div class="rcpt-rule"></div>

        @php
            // Pre-discount gross subtotal = sum of (qty × unit_price)
            // across every line. Sale row stores POST-discount net + tax,
            // so we add the discount back to recover the figure the
            // cashier saw. Math then reads:
            //   Subtotal − Discount = Total
            $grossSubtotal = bcadd(
                bcadd((string) $sale->subtotal, (string) $sale->tax_total, 4),
                (string) $sale->discount_total,
                4
            );
        @endphp
        <div class="rcpt-totals">
            <div><span>{{ __('sales.receipt.subtotal') }}</span><span>{{ format_money($grossSubtotal) }}</span></div>

            @if ((float) $sale->discount_total > 0)
                <div><span>{{ __('sales.receipt.discount') }}</span><span>−{{ format_money($sale->discount_total) }}</span></div>
            @endif

            @if ((float) $sale->tax_total > 0)
                <div class="rcpt-tax-incl"><span>{{ __('sales.receipt.tax_already_included') }}</span><span>{{ format_money($sale->tax_total) }}</span></div>
            @endif

            @if ((float) ($sale->additional_charges_total ?? 0) > 0)
                <div><span>{{ __('sales.receipt.charges') }}</span><span>{{ format_money($sale->additional_charges_total) }}</span></div>
            @endif

            @if ((float) ($sale->rounding_adjustment ?? 0) !== 0.0)
                <div><span>{{ __('sales.receipt.rounding') }}</span><span>{{ format_money($sale->rounding_adjustment) }}</span></div>
            @endif

            <div class="rcpt-total"><span>{{ __('sales.receipt.grand_total') }}</span><span>{{ format_money($sale->grand_total) }}</span></div>

            {{-- Payments --}}
            @foreach ($sale->payments as $payment)
                <div>
                    <span>{{ $payment->paymentMethod?->name ?? $payment->method_code }}</span>
                    <span>{{ format_money($payment->amount) }}</span>
                </div>
                @if ((float) ($payment->tendered_amount ?? 0) > (float) $payment->amount)
                    <div class="rcpt-tendered"><span>{{ __('sales.receipt.tendered') }}</span><span>{{ format_money($payment->tendered_amount) }}</span></div>
                @endif
            @endforeach

            @if ((float) $sale->change_returned > 0)
                <div><span>{{ __('sales.receipt.change') }}</span><span>{{ format_money($sale->change_returned) }}</span></div>
            @endif
        </div>

        {{-- HSN-wise tax summary — the block a GST tax invoice needs below
             the totals. One row per HSN code with its taxable value and
             tax; the per-component amounts (CGST / SGST / …) print beneath.
             Aggregation lives in the shared ReceiptHsnSummary helper so the
             thermal receipt prints the identical figures. --}}
        @if ($company->receipt_show_hsn_summary)
            @php $hsnSummary = \App\Support\ReceiptHsnSummary::build($sale); @endphp
            @if (count($hsnSummary))
                <div class="rcpt-rule"></div>
                <div class="rcpt-hsn">
                    <div class="rcpt-hsn-title">{{ __('sales.receipt.hsn_summary') }}</div>
                    <div class="rcpt-hsn-head">
                        <span>{{ __('sales.receipt.hsn_col_code') }}</span>
                        <span>{{ __('sales.receipt.hsn_col_taxable') }}</span>
                        <span>{{ __('sales.receipt.hsn_col_tax') }}</span>
                    </div>
                    @foreach ($hsnSummary as $row)
                        <div class="rcpt-hsn-row">
                            <span>{{ $row['hsn'] }}</span>
                            <span>{{ format_money($row['taxable']) }}</span>
                            <span>{{ format_money($row['tax']) }}</span>
                        </div>
                        @foreach ($row['components'] as $c)
                            <div class="rcpt-hsn-comp">
                                <span>{{ $c['name'] }}</span>
                                <span>{{ format_money($c['amount']) }}</span>
                            </div>
                        @endforeach
                    @endforeach
                </div>
            @endif
        @endif

        <div class="rcpt-rule"></div>

        @if (trim((string) $company->receipt_footer) !== '')
            <div class="rcpt-footer">{{ $company->receipt_footer }}</div>
        @endif

        {{-- Real Code 128 barcode of the sale number (picqer SVG). The QR
             remains a stub pending a QR generator. --}}
        @if ($company->receipt_show_barcode)
            <x-receipt-barcode :value="$sale->number" />
            <div class="rcpt-barcode-number mono">{{ $sale->number }}</div>
        @endif

        @if ($company->receipt_show_qr && $receiptUrl)
            <div class="rcpt-qr">
                <x-receipt-qr :url="$receiptUrl" />
                <div class="rcpt-qr-caption">{{ __('sales.receipt.qr_caption') }}</div>
            </div>
        @endif

        @if (trim((string) $company->receipt_return_policy) !== '')
            <div class="rcpt-return">{{ $company->receipt_return_policy }}</div>
        @endif
        @endif
    </div>

    @if ($auto)
        {{-- `data-auto-print` is what the receipt test asserts on — the
             Print button's own `onclick` also contains `window.print()`,
             so we need a marker that's unique to the autoprint path. --}}
        <script data-auto-print>
            // Auto-print on load — the success overlay's "Print receipt"
            // link sends `?print=1`. Run after one tick so the browser
            // has rendered the page before pulling up its print dialog.
            window.addEventListener('load', () => setTimeout(() => window.print(), 250));
        </script>
    @endif
</body>
</html>
