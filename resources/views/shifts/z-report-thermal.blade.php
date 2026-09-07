<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php($reportLabel = $shift->isClosed() ? __('shifts.sections.z_report') : __('shifts.sections.x_report'))
    <title>{{ $reportLabel }} #{{ $shift->id }}</title>
    {{-- Inline the receipt stylesheet (same print page family as the
         sale receipt) rather than going through Vite. --}}
    <style>{!! file_get_contents(resource_path('css/receipt.css')) !!}</style>
</head>
<body class="receipt-body receipt-paper-{{ $paper }}">

    <div class="receipt-toolbar" role="toolbar">
        <a href="{{ url()->previous() }}" class="receipt-tool-btn">← {{ __('shifts.actions.cancel') }}</a>
        <span class="receipt-tool-spacer"></span>
        <button type="button" class="receipt-tool-btn" onclick="window.print()">🖨 {{ __('shifts.actions.print') }}</button>
    </div>

    <div class="receipt {{ $paper === 'a4' ? 'receipt-a4' : 'receipt-thermal' }}">
        <div class="rcpt-store">
            <div class="rcpt-store-name">{{ $shift->store?->name }}</div>
            @foreach ($addrLines as $line)
                <div class="rcpt-store-addr">{{ $line }}</div>
            @endforeach
        </div>

        <div class="rcpt-rule"></div>
        <div class="rcpt-meta"><strong>{{ $reportLabel }}</strong></div>
        <div class="rcpt-totals">
            <div><span>{{ __('shifts.fields.shift_id') }}</span><span>#{{ $shift->id }}</span></div>
            <div><span>{{ __('shifts.fields.cashier') }}</span><span>{{ $shift->cashier?->name }}</span></div>
            <div><span>{{ __('shifts.fields.opened') }}</span><span>{{ format_datetime($shift->opened_at) }}</span></div>
            @if ($shift->closed_at)
                <div><span>{{ __('shifts.fields.closed') }}</span><span>{{ format_datetime($shift->closed_at) }}</span></div>
            @endif
        </div>

        <div class="rcpt-rule"></div>
        <div class="rcpt-meta"><strong>{{ __('shifts.sections.sales_summary') }}</strong></div>
        <div class="rcpt-totals">
            <div><span>{{ __('shifts.totals.sales_total') }}</span><span>{{ format_money($totals['sales_total']) }}</span></div>
            <div><span>{{ __('shifts.totals.discount_total') }}</span><span>{{ format_money($totals['discount_total']) }}</span></div>
            <div><span>{{ __('shifts.totals.tax_total') }}</span><span>{{ format_money($totals['tax_total']) }}</span></div>
            <div><span>{{ __('shifts.totals.refunds_total') }}</span><span>{{ format_money($totals['refunds_total']) }}</span></div>
            <div><span>{{ __('shifts.totals.sales_count') }}</span><span>{{ $totals['sales_count'] }}</span></div>
        </div>

        <div class="rcpt-rule"></div>
        <div class="rcpt-meta"><strong>{{ __('shifts.sections.payments_received') }}</strong></div>
        <div class="rcpt-totals">
            @foreach ($totals['payment_totals'] as $pt)
                <div><span>{{ $pt['name'] }}</span><span>{{ format_money($pt['amount']) }}</span></div>
            @endforeach
        </div>

        <div class="rcpt-rule"></div>
        <div class="rcpt-meta"><strong>{{ __('shifts.sections.cash_drawer') }}</strong></div>
        <div class="rcpt-totals">
            <div><span>{{ __('shifts.totals.opening_cash') }}</span><span>{{ format_money($totals['opening_cash']) }}</span></div>
            <div><span>{{ __('shifts.totals.cash_sales') }}</span><span>{{ format_money($totals['cash_sales']) }}</span></div>
            <div><span>{{ __('shifts.totals.cash_refunds') }}</span><span>-{{ format_money($totals['cash_refunds']) }}</span></div>
            <div><span>{{ __('shifts.totals.pay_ins') }}</span><span>{{ format_money($totals['pay_ins']) }}</span></div>
            <div><span>{{ __('shifts.totals.pay_outs') }}</span><span>-{{ format_money($totals['pay_outs']) }}</span></div>
            @if (! empty($totals['supplier_payouts']))
                <div><span>&nbsp;&nbsp;{{ __('shifts.totals.supplier_payments_heading') }}</span><span></span></div>
                @foreach ($totals['supplier_payouts'] as $sp)
                    <div><span>&nbsp;&nbsp;&nbsp;&nbsp;{{ $sp['supplier'] }}</span><span>-{{ format_money($sp['amount']) }}</span></div>
                @endforeach
            @endif
        </div>
        <div class="rcpt-total">
            <span>{{ __('shifts.totals.expected_cash') }}</span><span>{{ format_money($totals['expected_cash']) }}</span>
        </div>
        @if ($shift->closing_cash_counted !== null)
            <div class="rcpt-totals">
                <div><span>{{ __('shifts.totals.counted_cash') }}</span><span>{{ format_money($shift->closing_cash_counted) }}</span></div>
                <div><span>{{ __('shifts.totals.variance') }}</span><span>{{ format_money($shift->cash_variance) }}</span></div>
            </div>
        @endif
    </div>
</body>
</html>
