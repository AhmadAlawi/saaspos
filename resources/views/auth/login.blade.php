<x-auth-layout :title="__('auth.title')">
    @php
        // Branding data is loaded by AuthLayout for its own template, but
        // Blade slots render in the calling scope and can't see component
        // properties. The duplicate `Company::current()` here is one extra
        // SELECT on a page that's hit by humans (not in a hot loop), so
        // we accept it instead of refactoring to pass props through a slot.
        //
        // Guard against the pre-install state — first hit after a fresh
        // download, before migrations run, there's no `company` table at
        // all. Mirrors AuthLayout::loadBranding().
        $company        = null;
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('company')) {
                $company = \App\Models\Company::current();
            }
        } catch (\Throwable) {
            // Table not ready yet — fall through with the config fallback.
        }
        $appLogoUrl     = $company?->app_logo_url;
        $appLogoDarkUrl = $company?->app_logo_dark_url;
        $appName        = $company?->display_app_name ?: config('app.name');
    @endphp
    {{-- Demo-mode notice — sits OUTSIDE the login card so it reads as a
         site-level banner rather than a form alert. Renders only when
         POS_DEMO_MODE=true; link target is POS_DEMO_WEBSITE_URL. --}}
    @if (config('pos.demo.enabled'))
        <div class="alert alert-warning auth-demo-banner">
            <span class="alert-icon"><x-icon name="alert" class="w-[18px] h-[18px]" /></span>
            <div class="alert-body">
                <div class="alert-title">
                    {!! __('auth.demo.notice', [
                        'link' => '<a href="' . e(config('pos.demo.website_url')) . '" class="auth-link" target="_blank" rel="noopener">' . e(__('auth.demo.link_text')) . '</a>',
                    ]) !!}
                </div>
            </div>
        </div>
    @endif

    {{-- novalidate: validation is owned by the server (LoginRequest /
         PinLoginRequest in app/Http/Requests/Auth/). The browser's HTML5
         popups would race with the server response and be inconsistent.

         Two login surfaces share one Alpine scope: PIN (default — the
         numpad from resources/views/components/cashier/pin-pad.blade.php,
         already used for discount/refund approval) for a touchscreen
         till, and email+password as the fallback. `mode` toggles which
         is shown; both post via the shared `$http` client so CSRF/error
         handling stays identical to what this page already did. --}}
    <div class="auth-card"
         x-data="{
             mode: 'pin',
             submitting: false,
             errorMsg: '',
             pinForm: { pin: '' },
             filledRole: null,
             fillCredentials(role, email, password) {
                 const form = this.$refs.passwordForm;
                 const emailEl    = form.querySelector('input[name=&quot;email&quot;]');
                 const passwordEl = form.querySelector('input[name=&quot;password&quot;]');
                 if (emailEl)    { emailEl.value    = email;    emailEl.dispatchEvent(new Event('input', {bubbles: true})); }
                 if (passwordEl) { passwordEl.value = password; passwordEl.dispatchEvent(new Event('input', {bubbles: true})); }
                 this.filledRole = role;
                 setTimeout(() => { if (this.filledRole === role) this.filledRole = null; }, 1800);
             },
             async doLogin(evt) {
                 if (this.submitting) return;
                 this.submitting = true;
                 this.errorMsg = '';
                 try {
                     const { data } = await this.$http.post(evt.target.action, new FormData(evt.target));
                     window.location.href = data.redirect || '/admin';
                 } catch (e) {
                     this.submitting = false;
                     this.errorMsg = (e.errors && Object.values(e.errors).flat()[0]) || e.message || @js(__('auth.failed'));
                     if (e.errors) this.$http.applyValidationErrors(evt.target, e.errors);
                 }
             },
             async submitPin() {
                 if (this.submitting) return;
                 this.submitting = true;
                 this.errorMsg = '';
                 try {
                     const { data } = await this.$http.post(@js(route('login.pin')), { pin: this.pinForm.pin });
                     window.location.href = data.redirect || '/admin';
                 } catch (e) {
                     this.submitting = false;
                     this.pinForm.pin = '';
                     this.errorMsg = (e.errors && Object.values(e.errors).flat()[0]) || e.message || @js(__('auth.pin_invalid'));
                 }
             },
             switchMode(next) {
                 this.mode = next;
                 this.errorMsg = '';
                 this.pinForm.pin = '';
             }
         }">

        {{-- Brand. Don't use Tailwind's `hidden` / `dark:block` for the
             light↔dark swap — those utilities are in `@layer utilities`
             with the same specificity, and one company's combination of
             both logos rendered BOTH images in light mode (the second
             escaping the card on the right). Distinct
             `auth-brand-logo-light` / `-dark` classes toggled via plain
             CSS in [auth/login.css] are bulletproof. --}}
        <div class="auth-brand">
            @if ($appLogoUrl || $appLogoDarkUrl)
                @if ($appLogoUrl)
                    <img src="{{ $appLogoUrl }}"
                         class="auth-brand-logo auth-brand-logo-light"
                         alt="{{ $appName }}">
                @endif
                @if ($appLogoDarkUrl)
                    <img src="{{ $appLogoDarkUrl }}"
                         class="auth-brand-logo auth-brand-logo-dark"
                         alt="{{ $appName }}">
                @endif
            @else
                <span class="auth-brand-mark">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($appName, 0, 1)) }}</span>
                <div class="auth-brand-text">
                    <div class="auth-brand-name">{{ $appName }}</div>
                    <div class="auth-brand-sub">{{ __('auth.brand_sub') }}</div>
                </div>
            @endif
        </div>

        {{-- Title --}}
        <h1 class="auth-title">{{ __('auth.title') }}</h1>
        <p class="auth-sub" x-show="mode === 'pin'">{{ __('auth.pin_sub') }}</p>
        <p class="auth-sub" x-show="mode === 'password'" x-cloak>{{ __('auth.sub', ['name' => $appName]) }}</p>

        {{-- Success status — surfaces "Password updated" after a
             password-reset round-trip lands the user back here. --}}
        @if (session('status'))
            <div class="alert alert-success mb-5">
                <span class="alert-icon"><x-icon name="check" class="w-[18px] h-[18px]" /></span>
                <div class="alert-body">
                    <div class="alert-title">{{ session('status') }}</div>
                </div>
            </div>
        @endif

        {{-- Error alert — SSR fallback (shown on traditional POST / JS disabled) --}}
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

        {{-- Error alert — AJAX response (shared by both modes) --}}
        <div class="alert alert-danger mb-5" x-show="errorMsg" x-cloak>
            <span class="alert-icon"><x-icon name="alert" class="w-[18px] h-[18px]" /></span>
            <div class="alert-body">
                <div class="alert-title" x-text="errorMsg"></div>
            </div>
        </div>

        {{-- PIN mode — the touchscreen-friendly default. --}}
        <div x-show="mode === 'pin'" style="display:flex; flex-direction:column; align-items:center;">
            <x-cashier.pin-pad form-path="pinForm.pin" on-complete="submitPin()" />
            <p class="text-sm fg-tertiary mt-2" x-show="submitting" x-cloak>{{ __('auth.signing_in') }}</p>
            <button type="button" class="auth-link mt-4" @click="switchMode('password')">
                {{ __('auth.use_password_instead') }}
            </button>
        </div>

        {{-- Email + password mode — the fallback. --}}
        <form method="POST" action="{{ route('login.attempt') }}"
              x-ref="passwordForm"
              x-show="mode === 'password'" x-cloak
              novalidate
              @submit.prevent="doLogin($event)">
            @csrf

            {{-- Fields — spacing handled by .form-stack, NOT per-field margins.
                 Use this pattern for every multi-field form going forward. --}}
            <div class="form-stack">
                {{-- Email — server-side validation only (no `required` / `type=email`). --}}
                <label class="field">
                    <span class="field-label">{{ __('auth.email') }}</span>
                    <input type="text" name="email" autocomplete="username"
                           value="{{ old('email') }}"
                           class="pos-input"
                           placeholder="admin@local">
                </label>

                {{-- Password (with show / hide toggle) --}}
                <label class="field" x-data="{ shown: false }">
                    <span class="field-label">{{ __('auth.password') }}</span>
                    <div class="pos-input-wrap has-suffix">
                        <input :type="shown ? 'text' : 'password'"
                               name="password" autocomplete="current-password"
                               class="pos-input mono"
                               placeholder="••••••••">
                        <button type="button"
                                class="pos-input-suffix"
                                @click="shown = !shown"
                                :aria-label="shown ? @js(__('auth.hide_password')) : @js(__('auth.show_password'))"
                                :aria-pressed="shown">
                            <span x-show="!shown"><x-icon name="eye" class="w-[18px] h-[18px]" /></span>
                            <span x-show="shown" x-cloak><x-icon name="eye-off" class="w-[18px] h-[18px]" /></span>
                        </button>
                    </div>
                </label>
            </div>

            {{-- Remember me + forgot password --}}
            <div class="auth-actions-row">
                <label class="auth-remember">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                    <span>{{ __('auth.remember_me') }}</span>
                </label>

                <a href="{{ route('password.request') }}" class="auth-link">
                    {{ __('auth.forgot') }}
                </a>
            </div>

            {{-- Submit --}}
            <button type="submit"
                    class="pos-btn pos-btn-primary auth-submit"
                    :disabled="submitting">
                <svg x-show="submitting" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                </svg>
                <span x-text="submitting ? @js(__('auth.signing_in')) : @js(__('auth.sign_in'))"></span>
            </button>

            <button type="button" class="auth-link mt-4" @click="switchMode('pin')" style="display:block; text-align:center; width:100%;">
                {{ __('auth.use_pin_instead') }}
            </button>

            {{-- Demo credentials — renders below the Sign in button when demo
                 mode is on AND at least one credential pair is filled in via .env.
                 Each entry gets a Copy & Fill button that pastes email + password
                 into the inputs above. --}}
            @php
                $demoCredentials = collect(config('pos.demo.credentials', []))
                    ->filter(fn ($c) => ! empty($c['email']) && ! empty($c['password']))
                    ->values();
            @endphp
            @if (config('pos.demo.enabled') && $demoCredentials->isNotEmpty())
                <div class="demo-creds">
                    <div class="demo-creds-head">
                        <span class="demo-creds-title">{{ __('auth.demo.credentials_title') }}</span>
                        <span class="demo-creds-badge">{{ __('auth.demo.mode_badge') }}</span>
                    </div>

                    @foreach ($demoCredentials as $cred)
                        @php
                            $roleKey   = $cred['role'] ?? 'admin';
                            $roleLabel = __('auth.demo.role.' . $roleKey);
                            if ($roleLabel === 'auth.demo.role.' . $roleKey) {
                                $roleLabel = ucfirst($roleKey) . ' ' . __('auth.demo.credentials_title');
                            }
                        @endphp
                        <div class="demo-cred-card">
                            <div class="demo-cred-card-head">
                                <span class="demo-cred-role">{{ $roleLabel }}</span>
                                <button type="button" class="demo-cred-fill-btn"
                                        @click="fillCredentials({{ \Illuminate\Support\Js::from($roleKey) }}, {{ \Illuminate\Support\Js::from($cred['email']) }}, {{ \Illuminate\Support\Js::from($cred['password']) }})">
                                    <span x-show="filledRole !== @js($roleKey)">{{ __('auth.demo.copy_and_fill') }}</span>
                                    <span x-show="filledRole === @js($roleKey)" x-cloak>{{ __('auth.demo.filled') }}</span>
                                </button>
                            </div>
                            <dl class="demo-cred-fields">
                                <div>
                                    <dt>{{ __('auth.demo.email_label') }}:</dt>
                                    <dd><span class="demo-cred-chip">{{ $cred['email'] }}</span></dd>
                                </div>
                                <div>
                                    <dt>{{ __('auth.demo.password_label') }}:</dt>
                                    <dd><span class="demo-cred-chip">{{ $cred['password'] }}</span></dd>
                                </div>
                            </dl>
                        </div>
                    @endforeach
                </div>
            @endif
        </form>
    </div>
</x-auth-layout>
