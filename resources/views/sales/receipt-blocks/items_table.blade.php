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
