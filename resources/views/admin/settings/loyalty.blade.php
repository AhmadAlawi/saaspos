<x-admin-layout
    active="settings"
    :title="__('settings.loyalty.title')"
    :crumbs="[
        ['label' => __('settings.crumb_parent')],
        ['label' => __('settings.title'), 'href' => route('admin.settings.index')],
        ['label' => __('settings.loyalty.title')],
    ]">

    <div class="page-wide">
        <form method="POST" action="{{ route('admin.settings.loyalty.update') }}" data-ajax-form>
            @csrf
            @method('PATCH')

            <div class="page-header mb-6">
                <div class="flex items-start gap-3">
                    <a href="{{ route('admin.settings.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('settings.title') }}">
                        <x-icon name="back" class="w-4 h-4" />
                    </a>
                    <div>
                        <h1 class="page-title">{{ __('settings.loyalty.title') }}</h1>
                        <p class="page-sub">{{ __('settings.loyalty.sub') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                        <x-icon name="check" class="w-4 h-4" />
                        {{ __('settings.loyalty.actions.save') }}
                    </button>
                </div>
            </div>

            <div class="max-w-3xl">
                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('settings.loyalty.sections.main') }}</div>
                        <div class="card-title-sub">{{ __('settings.loyalty.sections.main_sub') }}</div>
                    </div></div>
                    <div class="card-body">
                        <div class="form-stack">
                            <label class="field-toggle">
                                <input type="hidden" name="loyalty_enabled" value="0">
                                <input type="checkbox"
                                       name="loyalty_enabled"
                                       value="1"
                                       @checked(old('loyalty_enabled', $company->loyalty_enabled))>
                                <span>{{ __('settings.loyalty.fields.loyalty_enabled') }}</span>
                            </label>

                            <label class="field">
                                <span class="field-label is-required">{{ __('settings.loyalty.fields.loyalty_earn_rate') }}</span>
                                <input type="number" name="loyalty_earn_rate" class="pos-input" step="0.0001" min="0"
                                       value="{{ old('loyalty_earn_rate', $company->loyalty_earn_rate ?? 1) }}" required>
                                <p class="field-help">{{ __('settings.loyalty.fields.loyalty_earn_rate_help') }}</p>
                            </label>

                            <label class="field">
                                <span class="field-label is-required">{{ __('settings.loyalty.fields.loyalty_redeem_rate') }}</span>
                                <input type="number" name="loyalty_redeem_rate" class="pos-input" step="0.0001" min="0.0001"
                                       value="{{ old('loyalty_redeem_rate', $company->loyalty_redeem_rate ?? 100) }}" required>
                                <p class="field-help">{{ __('settings.loyalty.fields.loyalty_redeem_rate_help') }}</p>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>
