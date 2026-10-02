<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('cashier.drawer_pin.title') }}</title>
    <style>{!! file_get_contents(resource_path('css/receipt.css')) !!}</style>
</head>
<body class="receipt-body receipt-paper-{{ $paper }}">

    <div class="receipt-toolbar" role="toolbar">
        <span class="receipt-tool-spacer"></span>
        <button type="button" class="receipt-tool-btn" onclick="window.print()">🖨 {{ __('shifts.actions.print') }}</button>
    </div>

    <div class="receipt {{ $paper === 'a4' ? 'receipt-a4' : 'receipt-thermal' }}">
        <div class="rcpt-store">
            <div class="rcpt-store-name">{{ $store?->name }}</div>
        </div>

        <div class="rcpt-header rcpt-refund-banner">{{ __('cashier.drawer_pin.title') }}</div>

        <div class="rcpt-rule"></div>
        <div class="rcpt-totals">
            <div><span>{{ __('sales.payments.paid_at') }}</span><span>{{ format_datetime(now()) }}</span></div>
            @if ($performedBy)
                <div><span>{{ __('cash_drawer.columns.by') }}</span><span>{{ $performedBy }}</span></div>
            @endif
            @if ($reason)
                <div><span>{{ __('cash_drawer.fields.reason') }}</span><span>{{ $reason }}</span></div>
            @endif
        </div>
    </div>

    <script data-auto-print>
        window.addEventListener('load', () => setTimeout(() => window.print(), 300));
    </script>
</body>
</html>
