<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index, follow">
    @include('partials.google-analytics')
    <title>{{ $title }} · {{ $company->display_app_name ?? config('app.name') }}</title>
    @vite(['resources/css/admin.css'])
</head>
<body class="legal-page">
    <div class="legal-shell">
        <header class="legal-page-head">
            @if ($company->app_logo_url)
                <a href="{{ $company->website ?: '/' }}" class="legal-page-brand" aria-label="{{ $company->display_app_name }}">
                    <img src="{{ $company->app_logo_url }}" alt="" class="legal-page-logo">
                </a>
            @else
                <a href="{{ $company->website ?: '/' }}" class="legal-page-brand-text">
                    {{ $company->display_app_name ?? config('app.name') }}
                </a>
            @endif

            <h1 class="legal-page-title">{{ $title }}</h1>
            <p class="legal-page-meta">
                <span>{{ $company->display_app_name ?? config('app.name') }}</span>
                @if ($company->updated_at)
                    <span class="legal-page-meta-sep">·</span>
                    <span>{{ __('settings.legal_pages.last_updated', ['date' => format_date($company->updated_at)]) }}</span>
                @endif
            </p>
        </header>

        {{--
            Content is HTML produced by the HugeRTE editor and sanitised
            at write-time by LegalPagesSettingsController::sanitize() —
            only a small allowlist of formatting tags survives, so emitting
            it raw is safe even for a public URL that payment gateways
            will fetch. The `prose` class scopes the typography styles.
        --}}
        <main class="legal-page-body prose">
            {!! $body !!}
        </main>

        <footer class="legal-page-foot">
            <div class="legal-page-foot-org">
                {{ $company->display_app_name ?? config('app.name') }}
            </div>
            <div class="legal-page-foot-contact">
                @if ($company->email)
                    <a href="mailto:{{ $company->display_email }}">{{ $company->display_email }}</a>
                @endif
                @if ($company->email && $company->phone)
                    <span class="legal-page-meta-sep">·</span>
                @endif
                @if ($company->phone)
                    <span>{{ $company->phone }}</span>
                @endif
            </div>
            <div class="legal-page-foot-links">
                <a href="{{ route('legal.privacy') }}">{{ __('settings.legal_pages.privacy_title') }}</a>
                <span class="legal-page-meta-sep">·</span>
                <a href="{{ route('legal.terms') }}">{{ __('settings.legal_pages.terms_title') }}</a>
            </div>
        </footer>
    </div>
</body>
</html>
