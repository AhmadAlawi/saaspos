<x-auth-layout :title="__('auth.password_reset.request_title')">
    @php
        // Same branding lookup pattern as login.blade.php — see comment
        // there for why this duplicate lives here too.
        $company        = null;
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('company')) {
                $company = \App\Models\Company::current();
            }
        } catch (\Throwable) { /* pre-install — fall back to config */ }
        $appLogoUrl     = $company?->app_logo_url;
        $appLogoDarkUrl = $company?->app_logo_dark_url;
        $appName        = $company?->display_app_name ?: config('app.name');
    @endphp
    {{-- AJAX submit (authForm, mode 'status') — no full-page reload. The
         native POST still works with JS disabled; the SSR alerts below are
         the fallback for that path. --}}
    <form method="POST" action="{{ route('password.email') }}" class="auth-card" novalidate
          x-data="authForm({ mode: 'status' })"
          @submit.prevent="submit($event)">
        @csrf

        <div class="auth-brand">
            @if ($appLogoUrl || $appLogoDarkUrl)
                @if ($appLogoUrl)
                    <img src="{{ $appLogoUrl }}" class="auth-brand-logo auth-brand-logo-light" alt="">
                @endif
                @if ($appLogoDarkUrl)
                    <img src="{{ $appLogoDarkUrl }}" class="auth-brand-logo auth-brand-logo-dark" alt="">
                @endif
            @else
                {{-- No logo image → fall back to the name text. When a logo
                     exists it already carries the wordmark, so the name is
                     redundant and hidden. --}}
                <span class="auth-brand-name">{{ $appName }}</span>
            @endif
        </div>

        <h1 class="auth-title">{{ __('auth.password_reset.request_title') }}</h1>
        <p class="auth-sub">{{ __('auth.password_reset.request_sub') }}</p>

        {{-- "Email sent" success / generic confirmation. We always send
             the same message regardless of whether the address matched
             a real account — see PasswordResetController::sendLink. --}}
        {{-- SSR success (JS disabled / traditional POST fallback) --}}
        @if (session('status'))
            <div class="alert alert-success mb-5">
                <span class="alert-icon"><x-icon name="check" class="w-[18px] h-[18px]" /></span>
                <div class="alert-body">
                    <div class="alert-title">{{ session('status') }}</div>
                </div>
            </div>
        @endif

        {{-- AJAX success --}}
        <div class="alert alert-success mb-5" x-show="statusMsg" x-cloak>
            <span class="alert-icon"><x-icon name="check" class="w-[18px] h-[18px]" /></span>
            <div class="alert-body">
                <div class="alert-title" x-text="statusMsg"></div>
            </div>
        </div>

        {{-- SSR error --}}
        @if ($errors->any())
            <div class="alert alert-danger mb-5">
                <span class="alert-icon"><x-icon name="alert" class="w-[18px] h-[18px]" /></span>
                <div class="alert-body">
                    <div class="alert-title">{{ $errors->first() }}</div>
                </div>
            </div>
        @endif

        {{-- AJAX error --}}
        <div class="alert alert-danger mb-5" x-show="errorMsg" x-cloak>
            <span class="alert-icon"><x-icon name="alert" class="w-[18px] h-[18px]" /></span>
            <div class="alert-body">
                <div class="alert-title" x-text="errorMsg"></div>
            </div>
        </div>

        <div class="form-stack">
            <label class="field">
                <span class="field-label">{{ __('auth.email') }}</span>
                <input type="text" name="email" autofocus autocomplete="username"
                       value="{{ old('email') }}"
                       class="pos-input"
                       placeholder="you@example.com">
            </label>
        </div>

        <button type="submit" class="pos-btn pos-btn-primary auth-submit" :disabled="submitting">
            <svg x-show="submitting" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
            </svg>
            <span>{{ __('auth.password_reset.send_link') }}</span>
        </button>

        <div class="auth-actions-row auth-actions-row--center">
            <a href="{{ route('login') }}" class="auth-link">
                ← {{ __('auth.password_reset.back_to_login') }}
            </a>
        </div>
    </form>
</x-auth-layout>
