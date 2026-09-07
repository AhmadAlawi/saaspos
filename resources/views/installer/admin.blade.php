@extends('installer.layout')

@section('content')
    <h2 class="text-[22px] font-semibold fg-primary tracking-[-0.01em]">{{ __('installer.admin.heading') }}</h2>
    <p class="mt-2 text-[13.5px] fg-secondary leading-relaxed">{{ __('installer.admin.subheading') }}</p>

    <form method="POST" action="{{ route('install.admin.save') }}" class="mt-8 space-y-8"
          x-data="{ submitting: false }" @submit="submitting = true">
        @csrf

        {{-- Section: Super-admin user --}}
        <section class="space-y-4">
            <div class="section-head"><div>{{ __('installer.admin.section_user') }}</div></div>

            <label class="block">
                <span class="field-label">{{ __('installer.admin.name') }}</span>
                <input type="text" name="admin_name" required value="{{ old('admin_name') }}" class="pos-input">
            </label>

            <label class="block">
                <span class="field-label">{{ __('installer.admin.email') }}</span>
                <input type="email" name="admin_email" required value="{{ old('admin_email') }}" class="pos-input">
            </label>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="field-label">{{ __('installer.admin.password') }}</span>
                    <x-admin.password-input name="admin_password" required autocomplete="new-password" />
                    <p class="mt-2 text-[12px] fg-tertiary">{{ __('installer.admin.password_hint') }}</p>
                </label>
                <label class="block">
                    <span class="field-label">{{ __('installer.admin.password_confirm') }}</span>
                    <x-admin.password-input name="admin_password_confirmation" required autocomplete="new-password" />
                </label>
            </div>
        </section>

        {{-- Section: Company --}}
        <section class="space-y-4">
            <div class="section-head"><div>{{ __('installer.admin.section_company') }}</div></div>

            <label class="block">
                <span class="field-label">{{ __('installer.admin.company_name') }}</span>
                <input type="text" name="company_name" required value="{{ old('company_name') }}" class="pos-input">
            </label>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="field-label">{{ __('installer.admin.country') }}</span>
                    <select name="country_code" required class="pos-input" x-data="enhancedSelect()">
                        @foreach ($countries as $code => $name)
                            <option value="{{ $code }}" @selected(old('country_code', 'IN') === $code)>{{ $name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="field-label">{{ __('installer.admin.industry') }}</span>
                    <select name="industry" required class="pos-input" x-data="enhancedSelect()">
                        @foreach ($industries as $code => $label)
                            <option value="{{ $code }}" @selected(old('industry', 'retail') === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="field-label">{{ __('installer.admin.currency') }}</span>
                    <select name="base_currency_code" required class="pos-input" x-data="enhancedSelect()">
                        @foreach ($currencies as $c)
                            <option value="{{ $c->code }}" @selected(old('base_currency_code', 'INR') === $c->code)>{{ $c->code }} — {{ $c->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="field-label">{{ __('installer.admin.timezone') }}</span>
                    <select name="timezone" required class="pos-input" x-data="enhancedSelect()">
                        @foreach ($timezones as $tz)
                            <option value="{{ $tz }}" @selected(old('timezone', 'Asia/Kolkata') === $tz)>{{ $tz }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </section>

        {{-- Section: First store --}}
        <section class="space-y-4">
            <div class="section-head"><div>{{ __('installer.admin.section_store') }}</div></div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="field-label">{{ __('installer.admin.store_name') }}</span>
                    <input type="text" name="store_name" required value="{{ old('store_name') }}" class="pos-input">
                </label>
                <label class="block">
                    <span class="field-label">{{ __('installer.admin.store_code') }}</span>
                    <input type="text" name="store_code" required value="{{ old('store_code', 'MAIN') }}" class="pos-input mono">
                </label>
            </div>

            <label class="block">
                <span class="field-label">{{ __('installer.admin.store_address') }}</span>
                <textarea name="store_address" required rows="3" class="pos-input">{{ old('store_address') }}</textarea>
            </label>
        </section>

        <div class="flex justify-between">
            <a href="{{ route('install.database') }}" class="pos-btn pos-btn-ghost">{{ __('installer.back') }}</a>
            <x-installer.submit-button :busy-label="__('installer.admin.creating')">
                {{ __('installer.continue') }}
            </x-installer.submit-button>
        </div>
    </form>
@endsection
