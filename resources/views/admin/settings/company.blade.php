<x-admin-layout
    active="settings"
    :title="__('settings.company.title')"
    :crumbs="[
        ['label' => __('settings.crumb_parent')],
        ['label' => __('settings.title'), 'href' => route('admin.settings.index')],
        ['label' => __('settings.company.title')],
    ]">

    @php
        $v = fn (string $key) => old($key, data_get($company, $key));
    @endphp

    <div class="page-wide">
        <x-admin.demo-lock-banner>{{ __('settings.demo.locked_banner_generic') }}</x-admin.demo-lock-banner>

        {{-- AJAX via lib/ajax-form.js. --}}
        <form method="POST" action="{{ route('admin.settings.company.update') }}" enctype="multipart/form-data" data-ajax-form>
            @csrf
            @method('PATCH')
            {{-- On the demo, `disabled` makes the whole form read-only; `contents`
                 keeps the existing layout intact. --}}
            <fieldset @disabled(pos_is_demo()) class="contents">

            <div class="page-header mb-6">
                <div class="flex items-start gap-3">
                    <a href="{{ route('admin.settings.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('settings.title') }}">
                        <x-icon name="back" class="w-4 h-4" />
                    </a>
                    <div>
                        <h1 class="page-title">{{ __('settings.company.title') }}</h1>
                        <p class="page-sub">{{ __('settings.company.sub') }}</p>
                    </div>
                </div>
                <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="check" class="w-4 h-4" />
                    {{ __('settings.company.actions.save') }}
                </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">
                <div class="space-y-5">
                    {{-- Identity --}}
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('settings.company.sections.identity') }}</div>
                            <div class="card-title-sub">{{ __('settings.company.sections.identity_sub') }}</div>
                        </div></div>
                        <div class="card-body">
                            <div class="form-stack">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label class="field">
                                        <span class="field-label is-required">{{ __('settings.company.fields.name') }}</span>
                                        <input type="text" name="name" value="{{ $v('name') }}" maxlength="191" class="pos-input">
                                        @error('name')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                    <label class="field">
                                        <span class="field-label">{{ __('settings.company.fields.legal_name') }}</span>
                                        <input type="text" name="legal_name" value="{{ $v('legal_name') }}" maxlength="191" class="pos-input">
                                        @error('legal_name')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label class="field">
                                        <span class="field-label">{{ __('settings.company.fields.tax_registration_number') }}</span>
                                        <input type="text" name="tax_registration_number" value="{{ $v('tax_registration_number') }}" maxlength="64" class="pos-input mono">
                                        @error('tax_registration_number')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                    <label class="field">
                                        <span class="field-label">{{ __('settings.company.fields.website') }}</span>
                                        <input type="url" name="website" value="{{ $v('website') }}" maxlength="191" class="pos-input" placeholder="https://">
                                        @error('website')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label class="field">
                                        <span class="field-label">{{ __('settings.company.fields.email') }}</span>
                                        <input type="email" name="email" value="{{ $v('email') }}" maxlength="191" class="pos-input">
                                        @error('email')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                    <label class="field">
                                        <span class="field-label">{{ __('settings.company.fields.phone') }}</span>
                                        <input type="text" name="phone" value="{{ $v('phone') }}" maxlength="32" class="pos-input">
                                        @error('phone')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Address --}}
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('settings.company.sections.address') }}</div>
                            <div class="card-title-sub">{{ __('settings.company.sections.address_sub') }}</div>
                        </div></div>
                        <div class="card-body">
                            <div class="form-stack">
                                <label class="field">
                                    <span class="field-label">{{ __('settings.company.fields.address_line1') }}</span>
                                    <input type="text" name="address_line1" value="{{ $v('address_line1') }}" maxlength="191" class="pos-input">
                                    @error('address_line1')<p class="field-error">{{ $message }}</p>@enderror
                                </label>
                                <label class="field">
                                    <span class="field-label">{{ __('settings.company.fields.address_line2') }}</span>
                                    <input type="text" name="address_line2" value="{{ $v('address_line2') }}" maxlength="191" class="pos-input">
                                    @error('address_line2')<p class="field-error">{{ $message }}</p>@enderror
                                </label>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label class="field">
                                        <span class="field-label">{{ __('settings.company.fields.city') }}</span>
                                        <input type="text" name="city" value="{{ $v('city') }}" maxlength="100" class="pos-input">
                                        @error('city')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                    <label class="field">
                                        <span class="field-label">{{ __('settings.company.fields.state') }}</span>
                                        <input type="text" name="state" value="{{ $v('state') }}" maxlength="100" class="pos-input">
                                        @error('state')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label class="field">
                                        <span class="field-label">{{ __('settings.company.fields.postal_code') }}</span>
                                        <input type="text" name="postal_code" value="{{ $v('postal_code') }}" maxlength="20" class="pos-input">
                                        @error('postal_code')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                    <label class="field">
                                        <span class="field-label">{{ __('settings.company.fields.country_code') }}</span>
                                        <select name="country_code" class="pos-input" x-data="enhancedSelect()">
                                            <option value="">—</option>
                                            @foreach ($countries as $code => $name)
                                                <option value="{{ $code }}" @selected($v('country_code') === $code)>{{ $name }} ({{ $code }})</option>
                                            @endforeach
                                        </select>
                                        @error('country_code')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Logo --}}
                <div class="space-y-5 lg:sticky lg:top-4">
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('settings.company.sections.logo') }}</div>
                            <div class="card-title-sub">{{ __('settings.company.sections.logo_sub') }}</div>
                        </div></div>
                        <div class="card-body">
                            <x-admin.image-upload
                                name="logo"
                                :initial-url="$company->logo_url"
                                :empty-title="__('settings.company.logo_empty_title')" />
                            @error('logo')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>
            </div>
            </fieldset>
        </form>
    </div>
</x-admin-layout>
