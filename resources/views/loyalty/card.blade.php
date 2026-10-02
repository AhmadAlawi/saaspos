<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('loyalty.card.title') }}</title>
    <style>{!! file_get_contents(resource_path('css/receipt.css')) !!}</style>
    <style>
        .loy-card {
            max-width: 360px;
            margin: 0 auto;
            border-radius: 16px;
            padding: 24px;
            background: #141414;
            color: #fff;
            text-align: center;
        }
        .loy-card-points {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 8px 0;
        }
        .loy-card-label {
            font-size: 0.75rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            opacity: 0.7;
        }
        .loy-card-barcode {
            background: #fff;
            border-radius: 8px;
            padding: 12px;
            margin-top: 16px;
        }
        .loy-card-barcode svg { width: 100%; height: auto; }
        .loy-card-code {
            font-family: monospace;
            letter-spacing: 0.1em;
            margin-top: 6px;
            color: #141414;
        }
    </style>
</head>
<body class="receipt-body">

    <div class="receipt-toolbar" role="toolbar">
        <span class="receipt-tool-spacer"></span>
        <button type="button" class="receipt-tool-btn" onclick="window.print()">🖨 {{ __('shifts.actions.print') }}</button>
    </div>

    <div class="loy-card">
        @if ($logoUrl = App\Models\Company::current()?->logo_url)
            <img src="{{ $logoUrl }}" alt="" style="max-height:40px; max-width:80%; margin-bottom:12px;">
        @endif
        <div class="loy-card-label">{{ __('loyalty.card.member') }}</div>
        <div style="font-size:1.1rem; font-weight:600;">{{ $customer->name }}</div>

        <div class="loy-card-label" style="margin-top:16px;">{{ __('loyalty.card.points') }}</div>
        <div class="loy-card-points">{{ number_format($customer->loyalty_points) }}</div>

        <div class="loy-card-barcode">
            <x-receipt-barcode :value="'MBR:'.$code" />
            <div class="loy-card-code">{{ $code }}</div>
        </div>
    </div>

    <p style="text-align:center; margin-top:16px; font-size:0.8rem; color:#888;">
        {{ __('loyalty.card.hint') }}
    </p>

</body>
</html>
