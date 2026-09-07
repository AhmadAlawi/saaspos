<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('shifts.sections.day_report') }} — {{ $day->business_date->toDateString() }}</title>
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
            <div class="rcpt-store-name">{{ $day->store?->name }}</div>
            @if ($day->terminal)
                <div class="rcpt-store-addr">{{ $day->terminal->name }}</div>
            @endif
        </div>

        <div class="rcpt-header rcpt-refund-banner">{{ __('shifts.sections.day_report') }}</div>

        <div class="rcpt-rule"></div>
        <div class="rcpt-totals">
            <div><span>{{ __('shifts.day_fields.date') }}</span><span>{{ $day->business_date->toDateString() }}</span></div>
            <div><span>{{ __('shifts.day_fields.opened') }}</span><span>{{ format_datetime($day->opened_at) }} — {{ $day->openedBy?->name }}</span></div>
            @if ($day->closed_at)
                <div><span>{{ __('shifts.day_fields.closed') }}</span><span>{{ format_datetime($day->closed_at) }} — {{ $day->closedBy?->name }}</span></div>
            @endif
            <div><span>{{ __('shifts.day_fields.shift_count') }}</span><span>{{ $totals['shift_count'] }}</span></div>
        </div>

        <div class="rcpt-rule"></div>
        <div class="rcpt-meta"><strong>{{ __('shifts.sections.sales_summary') }}</strong></div>
        <div class="rcpt-totals">
            <div><span>{{ __('shifts.totals.sales_total') }}</span><span>{{ format_money($totals['sales_total']) }}</span></div>
            <div><span>{{ __('shifts.totals.sales_count') }}</span><span>{{ $totals['sales_count'] }}</span></div>
            <div><span>{{ __('shifts.totals.refunds_total') }}</span><span>-{{ format_money($totals['refunds_total']) }}</span></div>
            <div><span>{{ __('shifts.day_fields.refunds_count') }}</span><span>{{ $totals['refunds_count'] }}</span></div>
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
            <div><span>{{ __('shifts.day_fields.closing_cash_total') }}</span><span>{{ format_money($totals['closing_cash_total']) }}</span></div>
            <div><span>{{ __('shifts.totals.variance') }}</span><span>{{ format_money($totals['cash_variance_total']) }}</span></div>
        </div>

        {{-- Per-employee breakdown — the whole point of the day-level
             report is that it's ONE print, but accountability per
             cashier's own shift is still visible line by line. --}}
        <div class="rcpt-rule"></div>
        <div class="rcpt-meta"><strong>{{ __('shifts.day_fields.by_employee') }}</strong></div>
        <div class="rcpt-totals">
            @foreach ($totals['employees'] as $emp)
                <div><span>{{ $emp['cashier_name'] }}</span><span>{{ format_money($emp['sales_total']) }}</span></div>
                @if ((float) $emp['refunds_total'] > 0)
                    <div><span>&nbsp;&nbsp;{{ __('shifts.totals.refunds_total') }}</span><span>-{{ format_money($emp['refunds_total']) }}</span></div>
                @endif
                @if ((float) $emp['cash_variance'] !== 0.0)
                    <div><span>&nbsp;&nbsp;{{ __('shifts.totals.variance') }}</span><span>{{ format_money($emp['cash_variance']) }}</span></div>
                @endif
            @endforeach
        </div>
    </div>
</body>
</html>
