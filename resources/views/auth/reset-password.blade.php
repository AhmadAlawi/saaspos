<x-auth-layout :title="__('auth.password_reset.reset_title')">
    @php
        $company        = null;
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('company')) {
                $company = \App\Models\Company::current();
            }
        } catch (\Throwable) { /* pre-install */ }
        $appLogoUrl     = $company?->app_logo_url;
        $appLogoDarkUrl = $company?->app_logo_dark_url;
        $appName        = $company?->display_app_name ?: config('app.name');
    @endphp
    {{-- AJAX submit (authForm, mode 'redirect') — on success the server
         flashes the "password updated" status and returns a redirect target
         to the login page. The native POST + SSR errors below are the
         JS-disabled fallback. --}}
    <form method="POST" action="{{ route('password.update') }}" class="auth-card" novalidate
          x-data="authForm({ mode: 'redirect' })"
          @submit.prevent="submit($event)">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

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

        <h1 class="auth-title">{{ __('auth.password_reset.reset_title') }}</h1>
        <p class="auth-sub">{{ __('auth.password_reset.reset_sub') }}</p>

        {{-- SSR error (JS disabled / traditional POST fallback) --}}
        @if ($errors->any())
            <div class="alert alert-danger mb-5">
                <span class="alert-icon"><x-icon name="alert" class="w-[18px] h-[18px]" /></span>
                <div class="alert-body">
                    @if ($errors->count() === 1)
                        <div class="alert-title">{{ $errors->first() }}</div>
                    @else
                        <ul class="alert-list">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    @endif
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
                <input type="text" name="email" autocomplete="username"
                       value="{{ old('email', $email) }}"
                       class="pos-input">
            </label>

            <label class="field" x-data="{ shown: false }">
                <span class="field-label">{{ __('auth.password_reset.new_password') }}</span>
                <div class="pos-input-wrap has-suffix">
                    <input :type="shown ? 'text' : 'password'"
                           name="password" autocomplete="new-password" autofocus
                           class="pos-input mono"
                           placeholder="••••••••">
                    <button type="button" class="pos-input-suffix"
                            @click="shown = !shown"
                            :aria-label="shown ? @js(__('auth.hide_password')) : @js(__('auth.show_password'))">
                        <span x-show="!shown"><x-icon name="eye" class="w-[18px] h-[18px]" /></span>
                        <span x-show="shown" x-cloak><x-icon name="eye-off" class="w-[18px] h-[18px]" /></span>
                    </button>
                </div>
            </label>

            <label class="field" x-data="{ shown: false }">
                <span class="field-label">{{ __('auth.password_reset.confirm_password') }}</span>
                <div class="pos-input-wrap has-suffix">
                    <input :type="shown ? 'text' : 'password'"
                           name="password_confirmation" autocomplete="new-password"
                           class="pos-input mono"
                           placeholder="••••••••">
                    <button type="button" class="pos-input-suffix"
                            @click="shown = !shown"
                            :aria-label="shown ? @js(__('auth.hide_password')) : @js(__('auth.show_password'))">
                        <span x-show="!shown"><x-icon name="eye" class="w-[18px] h-[18px]" /></span>
                        <span x-show="shown" x-cloak><x-icon name="eye-off" class="w-[18px] h-[18px]" /></span>
                    </button>
                </div>
            </label>
        </div>

        <button type="submit" class="pos-btn pos-btn-primary auth-submit" :disabled="submitting">
            <svg x-show="submitting" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
            </svg>
            <span>{{ __('auth.password_reset.update_password') }}</span>
        </button>

        <div class="auth-actions-row auth-actions-row--center">
            <a href="{{ route('login') }}" class="auth-link">
                ← {{ __('auth.password_reset.back_to_login') }}
            </a>
        </div>
    </form>
</x-auth-layout>
