<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.google-analytics')
    <title>{{ $shopName }} Price Checker</title>
    @if ($logoUrl)
        <link rel="icon" href="{{ $logoUrl }}">
    @endif
    @vite(['resources/css/pay.css', 'resources/js/pricing.js'])
</head>
<body class="pos-theme pay-body">
    <main class="pay-shell">

        <section class="pay-card" data-pricing-root data-lookup-url="{{ route('pricing.lookup') }}">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $shopName }}" class="pricing-logo">
            @endif
            <div class="pay-section-title">{{ __('pricing.title') }}</div>
            <p class="pay-merchant-sub" style="margin-top: 2px;">{{ __('pricing.subtitle') }}</p>

            {{-- Camera preview — hidden until the customer opts in (a real
                 tap is required before requesting camera permission). --}}
            <div class="pricing-video-wrap" data-video-wrap hidden>
                <video class="pricing-video" data-video playsinline muted></video>
                <div class="pricing-video-hint">{{ __('pricing.scanning_hint') }}</div>
            </div>

            <button type="button" class="pay-method-btn" data-start-scan>
                <span class="pay-method-avatar pay-tone-blue">📷</span>
                <span class="pay-method-meta">
                    <span class="pay-method-name">{{ __('pricing.scan_button') }}</span>
                </span>
            </button>

            <button type="button" class="pay-method-btn" data-stop-scan hidden>
                <span class="pay-method-avatar pay-tone-slate">✕</span>
                <span class="pay-method-meta">
                    <span class="pay-method-name">{{ __('pricing.stop_button') }}</span>
                </span>
            </button>

            <p class="pay-card-warning" data-camera-error hidden
               data-unsupported="{{ __('pricing.camera_unsupported') }}"
               data-denied="{{ __('pricing.camera_denied') }}"></p>

            <div class="pricing-divider">{{ __('pricing.or_type') }}</div>

            <form class="pricing-manual-form" data-manual-form>
                <input type="text" name="code" class="pricing-manual-input"
                       placeholder="{{ __('pricing.manual_placeholder') }}" autocomplete="off">
                <button type="submit" class="pos-btn pos-btn-primary">{{ __('pricing.check_button') }}</button>
            </form>

            <div class="pricing-result" data-result hidden>
                <div class="pricing-result-name" data-result-name></div>
                <div class="pricing-result-price" data-result-price></div>
                <button type="button" class="pay-method-btn" data-scan-again style="margin-top: 14px;">
                    <span class="pay-method-meta">
                        <span class="pay-method-name">{{ __('pricing.scan_again') }}</span>
                    </span>
                </button>
            </div>

            <div class="pricing-not-found" data-not-found hidden>
                {{ __('pricing.not_found') }}
            </div>
        </section>

    </main>
</body>
</html>
