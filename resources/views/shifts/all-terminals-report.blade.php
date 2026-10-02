<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('shifts.sections.all_terminals_report') }} — {{ $data['business_date'] }}</title>
    <style>{!! file_get_contents(resource_path('css/receipt.css')) !!}</style>
</head>
<body class="receipt-body receipt-paper-{{ $paper }}">

    <div class="receipt-toolbar" role="toolbar">
        <a href="{{ url()->previous() }}" class="receipt-tool-btn">← {{ __('shifts.actions.cancel') }}</a>
        <span class="receipt-tool-spacer"></span>
        <button type="button" class="receipt-tool-btn" onclick="window.print()">🖨 {{ __('shifts.actions.print') }}</button>
    </div>

    @php
        // Same reasoning as shifts.day-report: no cash-counting step
        // happens at this level, so the whole report is pure
        // reporting — gate all of it, not just the revenue sections.
        $canViewAmounts = auth()->user()?->hasPermission('sales.view_amounts', (int) $store->id) ?? false;
    @endphp
    <div class="receipt {{ $paper === 'a4' ? 'receipt-a4' : 'receipt-thermal' }}">
        <div class="rcpt-store">
            <div class="rcpt-store-name">{{ $store->name }}</div>
        </div>

        <div class="rcpt-header rcpt-refund-banner">{{ __('shifts.sections.all_terminals_report') }}</div>

        @if (! $canViewAmounts)
            <div class="rcpt-rule"></div>
            <div class="rcpt-meta">{{ __('shifts.errors.amounts_restricted') }}</div>
        @else
        <div class="rcpt-rule"></div>
        <div class="rcpt-totals">
            <div><span>{{ __('shifts.day_fields.date') }}</span><span>{{ $data['business_date'] }}</span></div>
            <div><span>{{ __('shifts.day_fields.terminal_count') }}</span><span>{{ $data['grand']['terminal_count'] }}</span></div>
        </div>

        {{-- One block per terminal, each self-contained — same fields
             as the single-terminal day report, just repeated. A
             terminal whose day is still open is labelled so the reader
             knows this run caught it mid-day, not after close. --}}
        @foreach ($data['terminals'] as $t)
            <div class="rcpt-rule"></div>
            <div class="rcpt-meta">
                <strong>{{ $t['terminal_name'] }}</strong>
                @if ($t['status'] !== \App\Models\TradingDay::STATUS_CLOSED)
                    — {{ __('shifts.day.status_open') }}
                @endif
            </div>
            <div class="rcpt-totals">
                <div><span>{{ __('shifts.totals.sales_total') }}</span><span>{{ format_money($t['totals']['sales_total']) }}</span></div>
                <div><span>{{ __('shifts.totals.sales_count') }}</span><span>{{ $t['totals']['sales_count'] }}</span></div>
                @if ((float) $t['totals']['refunds_total'] > 0)
                    <div><span>{{ __('shifts.totals.refunds_total') }}</span><span>-{{ format_money($t['totals']['refunds_total']) }}</span></div>
                @endif
                <div><span>{{ __('shifts.totals.variance') }}</span><span>{{ format_money($t['totals']['cash_variance_total']) }}</span></div>
            </div>
            @if (count($t['totals']['employees']) > 1)
                {{-- Only worth a sub-breakdown when more than one cashier
                     rotated through this till today — a single-shift
                     terminal would just repeat the block above. --}}
                <div class="rcpt-totals">
                    @foreach ($t['totals']['employees'] as $emp)
                        <div><span>&nbsp;&nbsp;{{ $emp['cashier_name'] }}</span><span>{{ format_money($emp['sales_total']) }}</span></div>
                    @endforeach
                </div>
            @endif
        @endforeach

        {{-- Grand total — the whole point of this report over clicking
             "Print" on each terminal row separately. --}}
        <div class="rcpt-rule"></div>
        <div class="rcpt-meta"><strong>{{ __('shifts.day_fields.grand_total') }}</strong></div>
        <div class="rcpt-totals">
            <div><span>{{ __('shifts.totals.sales_total') }}</span><span>{{ format_money($data['grand']['sales_total']) }}</span></div>
            <div><span>{{ __('shifts.totals.sales_count') }}</span><span>{{ $data['grand']['sales_count'] }}</span></div>
            <div><span>{{ __('shifts.totals.refunds_total') }}</span><span>-{{ format_money($data['grand']['refunds_total']) }}</span></div>
            <div><span>{{ __('shifts.day_fields.refunds_count') }}</span><span>{{ $data['grand']['refunds_count'] }}</span></div>
        </div>

        <div class="rcpt-rule"></div>
        <div class="rcpt-meta"><strong>{{ __('shifts.sections.payments_received') }}</strong></div>
        <div class="rcpt-totals">
            @foreach ($data['grand']['payment_totals'] as $pt)
                <div><span>{{ $pt['name'] }}</span><span>{{ format_money($pt['amount']) }}</span></div>
            @endforeach
        </div>

        <div class="rcpt-rule"></div>
        <div class="rcpt-meta"><strong>{{ __('shifts.sections.cash_drawer') }}</strong></div>
        <div class="rcpt-totals">
            <div><span>{{ __('shifts.totals.opening_cash') }}</span><span>{{ format_money($data['grand']['opening_cash']) }}</span></div>
            <div><span>{{ __('shifts.day_fields.closing_cash_total') }}</span><span>{{ format_money($data['grand']['closing_cash_total']) }}</span></div>
            <div><span>{{ __('shifts.totals.variance') }}</span><span>{{ format_money($data['grand']['cash_variance_total']) }}</span></div>
        </div>
        @endif
    </div>
</body>
</html>
