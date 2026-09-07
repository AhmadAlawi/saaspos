<x-admin-layout
    active="suppliers"
    :title="$supplier->exists ? $supplier->name : __('suppliers.new')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('suppliers.crumb_parent')],
        ['label' => __('suppliers.title'), 'href' => route('admin.suppliers.index')],
        ['label' => $supplier->exists ? $supplier->name : __('suppliers.new')],
    ]">

    @php
        $isEdit = $supplier->exists;
        $action = $isEdit
            ? route('admin.suppliers.update', $supplier)
            : route('admin.suppliers.store');
    @endphp

    <div class="page-wide">
        {{-- AJAX via lib/ajax-form.js — see http-client memory rule. --}}
        <form method="POST" action="{{ $action }}" novalidate data-ajax-form>
            @csrf
            @if ($isEdit) @method('PATCH') @endif

            <div class="page-header mb-6">
                <div class="flex items-start gap-3">
                    <a href="{{ route('admin.suppliers.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('suppliers.title') }}">
                        <x-icon name="back" class="w-4 h-4" />
                    </a>
                    <div>
                        <h1 class="page-title">{{ $isEdit ? $supplier->name : __('suppliers.new') }}</h1>
                        <p class="page-sub">{{ __('suppliers.sub') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ $isEdit ? route('admin.suppliers.show', $supplier) : route('admin.suppliers.index') }}"
                       class="pos-btn pos-btn-sm pos-btn-ghost">
                        {{ __('suppliers.actions.discard') }}
                    </a>
                    <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                        <x-icon name="check" class="w-4 h-4" />
                        {{ $isEdit ? __('suppliers.actions.save') : __('suppliers.actions.create') }}
                    </button>
                </div>
            </div>

            {{-- Two-column layout — cards stack inside per-column wrappers
                 (see memory table-and-form-conventions). --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">
                {{-- ── LEFT column: Profile + Address ──────────────── --}}
                <div class="space-y-5">
                    {{-- Profile --}}
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('suppliers.sections.profile') }}</div>
                            <div class="card-title-sub">{{ __('suppliers.sections.profile_sub') }}</div>
                        </div></div>
                        <div class="card-body">
                            <div class="form-stack">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label class="field">
                                        <span class="field-label is-required">{{ __('suppliers.fields.name') }}</span>
                                        <input type="text" name="name" value="{{ old('name', $supplier->name) }}" class="pos-input" required maxlength="191">
                                        @error('name')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                    <label class="field">
                                        <span class="field-label">{{ __('suppliers.fields.code') }}</span>
                                        <input type="text" name="code" value="{{ old('code', $supplier->code) }}" class="pos-input mono" maxlength="32" placeholder="{{ __('suppliers.fields.code_help') }}">
                                        @error('code')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                </div>

                                <label class="field">
                                    <span class="field-label">{{ __('suppliers.fields.business_name') }}</span>
                                    <input type="text" name="business_name" value="{{ old('business_name', $supplier->business_name) }}" class="pos-input" maxlength="191">
                                    @error('business_name')<p class="field-error">{{ $message }}</p>@enderror
                                </label>

                                <label class="field">
                                    <span class="field-label">{{ __('suppliers.fields.contact_person') }}</span>
                                    <input type="text" name="contact_person" value="{{ old('contact_person', $supplier->contact_person) }}" class="pos-input" maxlength="191">
                                    @error('contact_person')<p class="field-error">{{ $message }}</p>@enderror
                                </label>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label class="field">
                                        <span class="field-label">{{ __('suppliers.fields.phone') }}</span>
                                        <input type="text" name="phone" value="{{ old('phone', $supplier->phone) }}" class="pos-input mono" maxlength="32" inputmode="tel">
                                        @error('phone')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                    <label class="field">
                                        <span class="field-label">{{ __('suppliers.fields.email') }}</span>
                                        <input type="email" name="email" value="{{ old('email', $supplier->email) }}" class="pos-input" maxlength="191">
                                        @error('email')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                </div>

                                <label class="field-toggle">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $supplier->is_active ?? true))>
                                    <span>{{ __('suppliers.fields.is_active') }}</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Primary address --}}
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('suppliers.sections.address') }}</div>
                            <div class="card-title-sub">{{ __('suppliers.sections.address_sub') }}</div>
                        </div></div>
                        <div class="card-body">
                            <div class="form-stack">
                                <label class="field">
                                    <span class="field-label">{{ __('suppliers.fields.address_line1') }}</span>
                                    <input type="text" name="address_line1" value="{{ old('address_line1', $supplier->address_line1) }}" class="pos-input" maxlength="191">
                                </label>
                                <label class="field">
                                    <span class="field-label">{{ __('suppliers.fields.address_line2') }}</span>
                                    <input type="text" name="address_line2" value="{{ old('address_line2', $supplier->address_line2) }}" class="pos-input" maxlength="191">
                                </label>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <label class="field">
                                        <span class="field-label">{{ __('suppliers.fields.city') }}</span>
                                        <input type="text" name="city" value="{{ old('city', $supplier->city) }}" class="pos-input" maxlength="100">
                                    </label>
                                    <label class="field">
                                        <span class="field-label">{{ __('suppliers.fields.state') }}</span>
                                        <input type="text" name="state" value="{{ old('state', $supplier->state) }}" class="pos-input" maxlength="100">
                                    </label>
                                    <label class="field">
                                        <span class="field-label">{{ __('suppliers.fields.postal_code') }}</span>
                                        <input type="text" name="postal_code" value="{{ old('postal_code', $supplier->postal_code) }}" class="pos-input mono" maxlength="20">
                                    </label>
                                </div>
                                <label class="field">
                                    <span class="field-label">{{ __('suppliers.fields.country') }}</span>
                                    @php $current = old('country_code', $supplier->country_code ?: $defaultCountry); @endphp
                                    <select name="country_code" class="pos-input" x-data="enhancedSelect()">
                                        <option value="">—</option>
                                        @foreach ($countries as $code => $name)
                                            <option value="{{ $code }}" @selected($current === $code)>{{ $name }} ({{ $code }})</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── RIGHT column: Tax & registration + Terms + Notes ── --}}
                <div class="space-y-5">
                    {{-- Tax & registration --}}
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('suppliers.sections.tax') }}</div>
                            <div class="card-title-sub">{{ __('suppliers.sections.tax_sub') }}</div>
                        </div></div>
                        <div class="card-body">
                            <div class="form-stack">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label class="field">
                                        <span class="field-label">{{ __('suppliers.fields.gstin') }}</span>
                                        <input type="text" name="gstin" value="{{ old('gstin', $supplier->gstin) }}" class="pos-input mono" maxlength="64">
                                        @error('gstin')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                    <label class="field">
                                        <span class="field-label">{{ __('suppliers.fields.pan') }}</span>
                                        <input type="text" name="pan" value="{{ old('pan', $supplier->pan) }}" class="pos-input mono" maxlength="16">
                                        @error('pan')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                </div>
                                <label class="field">
                                    <span class="field-label">{{ __('suppliers.fields.tax_reg') }}</span>
                                    <input type="text" name="tax_registration_number" value="{{ old('tax_registration_number', $supplier->tax_registration_number) }}" class="pos-input mono" maxlength="64">
                                    @error('tax_registration_number')<p class="field-error">{{ $message }}</p>@enderror
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Terms --}}
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('suppliers.sections.terms') }}</div>
                            <div class="card-title-sub">{{ __('suppliers.sections.terms_sub') }}</div>
                        </div></div>
                        <div class="card-body">
                            <div class="form-stack">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label class="field">
                                        <span class="field-label">{{ __('suppliers.fields.default_currency') }}</span>
                                        @php $curCurrency = old('default_currency_code', $supplier->default_currency_code ?: $baseCurrency); @endphp
                                        <select name="default_currency_code" class="pos-input" x-data="enhancedSelect()">
                                            <option value="">—</option>
                                            @foreach ($currencies as $cur)
                                                <option value="{{ $cur->code }}" @selected($curCurrency === $cur->code)>{{ $cur->code }} — {{ $cur->name }}</option>
                                            @endforeach
                                        </select>
                                        <p class="field-help">{{ __('suppliers.fields.default_currency_help') }}</p>
                                        @error('default_currency_code')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                    <label class="field">
                                        <span class="field-label">{{ __('suppliers.fields.payment_terms_days') }}</span>
                                        <input type="number" min="0" max="365" step="1" name="payment_terms_days" value="{{ old('payment_terms_days', $supplier->payment_terms_days) }}" class="pos-input tnum">
                                        <p class="field-help">{{ __('suppliers.fields.payment_terms_days_help') }}</p>
                                        @error('payment_terms_days')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Notes --}}
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('suppliers.sections.notes') }}</div>
                            <div class="card-title-sub">{{ __('suppliers.sections.notes_sub') }}</div>
                        </div></div>
                        <div class="card-body">
                            <label class="field">
                                <span class="field-label">{{ __('suppliers.fields.notes') }}</span>
                                <textarea name="notes" rows="5" class="pos-input" maxlength="5000">{{ old('notes', $supplier->notes) }}</textarea>
                                @error('notes')<p class="field-error">{{ $message }}</p>@enderror
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>
