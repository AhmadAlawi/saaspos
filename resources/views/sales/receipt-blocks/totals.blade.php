@php
    // Pre-discount gross subtotal — sale row stores POST-discount net + tax,
    // so add the discount back to recover the figure the cashier saw.
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
</div>
