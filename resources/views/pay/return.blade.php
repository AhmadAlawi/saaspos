<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.google-analytics')
    <title>{{ __('pay.return.title') }}</title>
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
        .return-card {
            max-width: 420px;
            width: 100%;
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: 14px;
            padding: 36px 28px;
            text-align: center;
        }
        .return-icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 16px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: 800;
        }
        .return-icon.is-paid {
            background: var(--success-soft, color-mix(in srgb, var(--success, #15803d) 14%, transparent));
            color: var(--success, #15803d);
        }
        .return-icon.is-failed {
            background: var(--danger-soft, color-mix(in srgb, var(--danger, #b91c1c) 14%, transparent));
            color: var(--danger, #b91c1c);
        }
        .return-title {
            font-size: 20px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        .return-sub {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.5;
        }
    </style>
</head>
<body class="pos-theme">
    <div class="return-card">
        @if ($session->isPaid())
            <div class="return-icon is-paid">✓</div>
            <div class="return-title">{{ __('pay.return.paid_title') }}</div>
            <div class="return-sub">{{ __('pay.return.paid_sub') }}</div>
        @else
            <div class="return-icon is-failed">!</div>
            <div class="return-title">{{ __('pay.return.failed_title') }}</div>
            <div class="return-sub">{{ __('pay.return.failed_sub') }}</div>
        @endif
    </div>
</body>
</html>
