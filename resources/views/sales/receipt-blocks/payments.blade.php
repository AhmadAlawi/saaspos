<div class="rcpt-totals">
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
