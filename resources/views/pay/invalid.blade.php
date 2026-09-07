<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.google-analytics')
    <title>{{ __('pay.invalid.title') }}</title>
    @vite(['resources/css/pay.css'])
    <style>
        html, body { height: 100%; margin: 0; }
        body {
            background: var(--bg-canvas);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .invalid-card {
            max-width: 420px;
            width: 100%;
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: 14px;
            padding: 36px 28px;
            text-align: center;
        }
        .invalid-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        .invalid-sub {
            font-size: 13.5px;
            color: var(--text-secondary);
            line-height: 1.5;
        }
    </style>
</head>
<body class="pos-theme">
    <div class="invalid-card">
        <div class="invalid-title">{{ __('pay.invalid.title') }}</div>
        <div class="invalid-sub">{{ $error ?? __('pay.invalid.sub') }}</div>
    </div>
</body>
</html>
