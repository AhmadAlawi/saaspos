<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('shifts.day_total.report_title') }} — {{ $data['business_date'] }}</title>
    <style>{!! file_get_contents(resource_path('css/receipt.css')) !!}</style>
</head>
<body class="receipt-body receipt-paper-{{ $paper }}">

    <div class="receipt-toolbar" role="toolbar">
        <a href="{{ url()->previous() }}" class="receipt-tool-btn">← {{ __('shifts.actions.cancel') }}</a>
        <span class="receipt-tool-spacer"></span>
        <button type="button" class="receipt-tool-btn" onclick="window.print()">🖨 {{ __('shifts.actions.print') }}</button>
    </div>

    @php
        $canViewAmounts = auth()->user()?->hasPermission('sales.view_amounts', (int) $store->id) ?? false;
        $grand = $data['grand'];
        // Sum every payment_totals row of the given type — a store can
        // have more than one method of the same type (two card readers,
        // say), and this compact slip only cares about the two lump
        // sums, not a full per-method breakdown.
        $methodTotal = function (string $type) use ($grand) {
            $sum = '0';
            foreach (($grand['payment_totals'] ?? []) as $row) {
                if (($row['type'] ?? null) === $type) {
                    $sum = bcadd($sum, (string) ($row['amount'] ?? '0'), 4);
                }
            }
            return $sum;
        };
    @endphp
    <div class="receipt {{ $paper === 'a4' ? 'receipt-a4' : 'receipt-thermal' }}">
        <div class="rcpt-store">
            <div class="rcpt-store-name">{{ $store->name }}</div>
        </div>

        <div class="rcpt-header rcpt-refund-banner">{{ __('shifts.day_total.report_title') }}</div>

        @if (! $canViewAmounts)
            <div class="rcpt-rule"></div>
            <div class="rcpt-meta">{{ __('shifts.errors.amounts_restricted') }}</div>
        @else
        <div class="rcpt-rule"></div>
        <div class="rcpt-totals">
            <div><span>{{ __('shifts.day_fields.date') }}</span><span>{{ $data['business_date'] }}</span></div>
            <div><span>{{ __('shifts.day_fields.terminal_count') }}</span><span>{{ $grand['terminal_count'] }}</span></div>
        </div>

        <div class="rcpt-rule"></div>
        <div class="rcpt-totals">
            <div><span>{{ __('shifts.day_total.total_sales') }}</span><span>{{ format_money($grand['sales_total']) }}</span></div>
            <div><span>{{ __('shifts.day_total.total_cards') }}</span><span>{{ format_money($methodTotal('card')) }}</span></div>
            <div><span>{{ __('shifts.day_total.total_cash') }}</span><span>{{ format_money($methodTotal('cash')) }}</span></div>
            <div><span>{{ __('shifts.day_total.total_short') }}</span><span>{{ format_money($grand['variance_short_total']) }}</span></div>
            <div><span>{{ __('shifts.day_total.total_over') }}</span><span>{{ format_money($grand['variance_over_total']) }}</span></div>
            @if ((float) $grand['discount_total'] > 0)
                <div><span>{{ __('shifts.day_total.total_offer') }}</span><span>{{ format_money($grand['discount_total']) }}</span></div>
            @endif
            <div><span>{{ __('shifts.day_total.total_refund') }}</span><span>{{ format_money($grand['refunds_total']) }}</span></div>
            <div><span>{{ __('shifts.day_total.total_expenses') }}</span><span>{{ format_money($grand['pay_outs_total']) }}</span></div>
        </div>
        @endif
    </div>
</body>
</html>
