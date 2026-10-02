<x-admin-layout
    active="settings"
    :title="__('settings.apple_wallet.title')"
    :crumbs="[
        ['label' => __('settings.crumb_parent')],
        ['label' => __('settings.title'), 'href' => route('admin.settings.index')],
        ['label' => __('settings.apple_wallet.title')],
    ]">

    <div class="page-wide">
        <form method="POST" action="{{ route('admin.settings.apple-wallet.update') }}" enctype="multipart/form-data" data-ajax-form>
            @csrf
            @method('PATCH')

            <div class="page-header mb-6">
                <div class="flex items-start gap-3">
                    <a href="{{ route('admin.settings.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('settings.title') }}">
                        <x-icon name="back" class="w-4 h-4" />
                    </a>
                    <div>
                        <h1 class="page-title">{{ __('settings.apple_wallet.title') }}</h1>
                        <p class="page-sub">{{ __('settings.apple_wallet.sub') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                        <x-icon name="check" class="w-4 h-4" />
                        {{ __('settings.apple_wallet.actions.save') }}
                    </button>
                </div>
            </div>

            <div class="max-w-3xl">
                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('settings.apple_wallet.sections.main') }}</div>
                        <div class="card-title-sub">{{ __('settings.apple_wallet.sections.main_sub') }}</div>
                    </div></div>
                    <div class="card-body">
                        <div class="form-stack">
                            <label class="field-toggle">
                                <input type="hidden" name="apple_wallet_enabled" value="0">
                                <input type="checkbox" name="apple_wallet_enabled" value="1"
                                       @checked(old('apple_wallet_enabled', $company->apple_wallet_enabled))>
                                <span>{{ __('settings.apple_wallet.fields.enabled') }}</span>
                            </label>

                            <label class="field">
                                <span class="field-label">{{ __('settings.apple_wallet.fields.team_id') }}</span>
                                <input type="text" name="apple_team_id" class="pos-input" maxlength="32"
                                       value="{{ old('apple_team_id', $company->apple_team_id) }}"
                                       placeholder="e.g. AB12CD34EF">
                            </label>

                            <label class="field">
                                <span class="field-label">{{ __('settings.apple_wallet.fields.pass_type_id') }}</span>
                                <input type="text" name="apple_pass_type_id" class="pos-input" maxlength="191"
                                       value="{{ old('apple_pass_type_id', $company->apple_pass_type_id) }}"
                                       placeholder="pass.com.yourcompany.loyalty">
                            </label>

                            <label class="field">
                                <span class="field-label">{{ __('settings.apple_wallet.fields.cert') }}</span>
                                <input type="file" name="cert" class="pos-input" accept=".p12,.pfx">
                                <p class="field-help">
                                    @if ($hasCert)
                                        {{ __('settings.apple_wallet.fields.cert_present') }}
                                    @else
                                        {{ __('settings.apple_wallet.fields.cert_missing') }}
                                    @endif
                                </p>
                            </label>

                            <label class="field">
                                <span class="field-label">{{ __('settings.apple_wallet.fields.cert_password') }}</span>
                                <input type="password" name="apple_cert_password" class="pos-input" maxlength="255"
                                       placeholder="{{ __('settings.apple_wallet.fields.cert_password_placeholder') }}">
                            </label>

                            <p class="field-help">{{ __('settings.apple_wallet.help') }}</p>
                        </div>
                    </div>
                </div>

                <div class="card" style="margin-top:20px;">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('settings.apple_wallet.sections.passfast') }}</div>
                        <div class="card-title-sub">{{ __('settings.apple_wallet.sections.passfast_sub') }}</div>
                    </div></div>
                    <div class="card-body">
                        <div class="form-stack">
                            <label class="field">
                                <span class="field-label">{{ __('settings.apple_wallet.fields.passfast_api_key') }}</span>
                                <input type="password" name="passfast_api_key" class="pos-input" maxlength="255"
                                       placeholder="{{ $hasPassFastKey ? __('settings.apple_wallet.fields.passfast_key_present') : 'sk_live_...' }}">
                            </label>

                            <label class="field">
                                <span class="field-label">{{ __('settings.apple_wallet.fields.passfast_template_id') }}</span>
                                <input type="text" name="passfast_template_id" class="pos-input" maxlength="191"
                                       value="{{ old('passfast_template_id', $company->passfast_template_id) }}">
                                <p class="field-help">{{ __('settings.apple_wallet.fields.passfast_template_id_help') }}</p>
                            </label>

                            <label class="field">
                                <span class="field-label">{{ __('settings.apple_wallet.fields.passfast_app_id') }}</span>
                                <input type="text" name="passfast_app_id" class="pos-input" maxlength="191"
                                       value="{{ old('passfast_app_id', $company->passfast_app_id) }}">
                                <p class="field-help">{{ __('settings.apple_wallet.fields.passfast_app_id_help') }}</p>
                            </label>

                            <p class="field-help">{{ __('settings.apple_wallet.passfast_help') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>
