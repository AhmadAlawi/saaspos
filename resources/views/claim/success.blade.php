<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('claim.success.title') }}</title>
    <style>
        body { font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 24px 16px; }
        .claim-card { max-width: 380px; margin: 0 auto; background: #fff; border-radius: 16px; padding: 24px; text-align: center; box-shadow: 0 2px 12px rgba(0,0,0,.06); }
        .claim-tick { color: #16a34a; margin-bottom: 8px; }
        .claim-title { font-size: 1.25rem; font-weight: 700; margin: 0 0 6px; }
        .claim-sub { color: #666; font-size: .9rem; margin: 0 0 18px; }
        .claim-points { font-size: 2.25rem; font-weight: 700; margin: 4px 0; }
        .claim-points-label { font-size: .75rem; letter-spacing: .08em; text-transform: uppercase; color: #888; }
        .claim-balance { margin-top: 4px; color: #666; font-size: .85rem; }
        .claim-wallet-wrap { display: flex; justify-content: center; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="claim-card">
        <div class="claim-tick">
            <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 6 9 17l-5-5"/>
            </svg>
        </div>
        <div class="claim-title">{{ __('claim.success.title') }}</div>
        <div class="claim-sub">{{ __('claim.success.sub', ['name' => $customer->name]) }}</div>

        <div class="claim-points-label">{{ __('claim.success.points_label') }}</div>
        <div class="claim-points">{{ number_format($customer->loyalty_points) }}</div>
        <div class="claim-balance">{{ __('claim.success.balance') }}</div>

        @if ($walletPassUrl || $googleWalletPassUrl)
            <div class="claim-wallet-wrap">
                <x-wallet-buttons :apple-url="$walletPassUrl" :google-url="$googleWalletPassUrl" />
            </div>
        @endif
    </div>
</body>
</html>
