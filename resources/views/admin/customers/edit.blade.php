<x-admin-layout
    active="customers"
    :title="$customer->exists ? $customer->name : __('customers.new')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('customers.crumb_parent')],
        ['label' => __('customers.title'), 'href' => route('admin.customers.index')],
        ['label' => $customer->exists ? $customer->name : __('customers.new')],
    ]">

    @php
        $isEdit = $customer->exists;
        $action = $isEdit
            ? route('admin.customers.update', $customer)
            : route('admin.customers.store');

        $addrPayload = $isEdit
            ? $customer->addresses->map(fn ($a) => [
                'label'        => $a->label,
                'line1'        => $a->line1,
                'line2'        => $a->line2,
                'city'         => $a->city,
                'state'        => $a->state,
                'postal_code'  => $a->postal_code,
                'country_code' => $a->country_code,
                'landmark'     => $a->landmark,
                'is_default'   => (bool) $a->is_default,
            ])->values()
            : collect([['is_default' => true]]); // start with one blank default row
    @endphp

    <div class="page-wide"
         x-data="customerForm({
             isBusiness:       {{ old('is_business', $customer->is_business) ? 'true' : 'false' }},
             business:         {{ Js::from([
                                    'name'   => (string) old('business_name', $customer->business_name ?? ''),
                                    'gstin'  => (string) old('gstin', $customer->gstin ?? ''),
                                    'pan'    => (string) old('pan', $customer->pan ?? ''),
                                    'taxReg' => (string) old('tax_registration_number', $customer->tax_registration_number ?? ''),
                                ]) }},
             addresses:        {{ Js::from($addrPayload) }},
             groups:           {{ Js::from($groups->map(fn ($g) => [
                                    'id'                       => $g->id,
                                    'default_discount_percent' => $g->default_discount_percent !== null
                                                                    ? (float) $g->default_discount_percent : null,
                                ])->values()) }},
             initialGroupId:   {{ Js::from(old('customer_group_id', $customer->customer_group_id)) }},
             initialDiscount:  {{ Js::from(old('default_discount_percent', $customer->default_discount_percent)) }},
             defaultCountry:   {{ Js::from($defaultCountry) }},
         })">
        {{-- AJAX via lib/ajax-form.js — controller returns JSON on
             success / 422 on error. Non-AJAX fallback (tests, no-JS)
             still gets a classic redirect from the same endpoint. --}}
        <form method="POST" action="{{ $action }}" novalidate data-ajax-form>
            @csrf
            @if ($isEdit) @method('PATCH') @endif

            <div class="page-header mb-6">
                <div class="flex items-start gap-3">
                    <a href="{{ route('admin.customers.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('customers.title') }}">
                        <x-icon name="back" class="w-4 h-4" />
                    </a>
                    <div>
                        <h1 class="page-title">{{ $isEdit ? $customer->name : __('customers.new') }}</h1>
                        <p class="page-sub">{{ __('customers.sub') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ $isEdit ? route('admin.customers.show', $customer) : route('admin.customers.index') }}"
                       class="pos-btn pos-btn-sm pos-btn-ghost">
                        {{ __('customers.actions.discard') }}
                    </a>
                    <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                        <x-icon name="check" class="w-4 h-4" />
                        {{ $isEdit ? __('customers.actions.save') : __('customers.actions.create') }}
                    </button>
                </div>
            </div>

            {{-- Two-column layout — cards stack inside per-column wrappers
                 so each column flows naturally with `space-y-5`, instead of
                 a grid-row aligning Profile + Pricing and leaving an empty
                 gap when one card is taller (see memory
                 table-and-form-conventions). --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">
                <div class="space-y-5">
                {{-- Profile --}}
                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('customers.sections.profile') }}</div>
                        <div class="card-title-sub">{{ __('customers.sections.profile_sub') }}</div>
                    </div></div>
                    <div class="card-body">
                        <div class="form-stack">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="field">
                                    <span class="field-label is-required">{{ __('customers.fields.name') }}</span>
                                    <input type="text" name="name" value="{{ old('name', $customer->name) }}" class="pos-input" required maxlength="191">
                                    @error('name')<p class="field-error">{{ $message }}</p>@enderror
                                </label>
                                <label class="field">
                                    <span class="field-label">{{ __('customers.fields.code') }}</span>
                                    <input type="text" name="code" value="{{ old('code', $customer->code) }}" class="pos-input mono" maxlength="32" placeholder="{{ __('customers.fields.code_help') }}">
                                    @error('code')<p class="field-error">{{ $message }}</p>@enderror
                                </label>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="field">
                                    <span class="field-label">{{ __('customers.fields.phone') }}</span>
                                    <input type="text" name="phone" value="{{ old('phone', $customer->phone) }}" class="pos-input mono" maxlength="32" inputmode="tel">
                                    @error('phone')<p class="field-error">{{ $message }}</p>@enderror
                                </label>
                                <label class="field">
                                    <span class="field-label">{{ __('customers.fields.whatsapp_phone') }}</span>
                                    <input type="text" name="whatsapp_phone" value="{{ old('whatsapp_phone', $customer->whatsapp_phone) }}" class="pos-input mono" maxlength="32" inputmode="tel">
                                    <p class="field-help">{{ __('customers.fields.whatsapp_help') }}</p>
                                    @error('whatsapp_phone')<p class="field-error">{{ $message }}</p>@enderror
                                </label>
                            </div>

                            <label class="field">
                                <span class="field-label">{{ __('customers.fields.email') }}</span>
                                <input type="email" name="email" value="{{ old('email', $customer->email) }}" class="pos-input" maxlength="191">
                                @error('email')<p class="field-error">{{ $message }}</p>@enderror
                            </label>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="field">
                                    <span class="field-label">{{ __('customers.fields.dob') }}</span>
                                    <input type="text" name="dob" value="{{ old('dob', optional($customer->dob)->toDateString()) }}" class="pos-input js-datepicker" placeholder="YYYY-MM-DD">
                                    @error('dob')<p class="field-error">{{ $message }}</p>@enderror
                                </label>
                                <label class="field">
                                    <span class="field-label">{{ __('customers.fields.gender') }}</span>
                                    <select name="gender" class="pos-input" x-data="enhancedSelect()">
                                        <option value="">—</option>
                                        @foreach ($genders as $g)
                                            <option value="{{ $g }}" @selected(old('gender', $customer->gender) === $g)>{{ __('customers.gender.'.$g) }}</option>
                                        @endforeach
                                    </select>
                                    @error('gender')<p class="field-error">{{ $message }}</p>@enderror
                                </label>
                            </div>

                            <label class="field-toggle">
                                <input type="hidden" name="whatsapp_opt_out" value="0">
                                <input type="checkbox" name="whatsapp_opt_out" value="1" @checked(old('whatsapp_opt_out', $customer->whatsapp_opt_out))>
                                <span>{{ __('customers.fields.whatsapp_opt_out') }}</span>
                            </label>
                            <label class="field-toggle">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $customer->is_active ?? true))>
                                <span>{{ __('customers.fields.is_active') }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Business --}}
                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('customers.sections.business') }}</div>
                        <div class="card-title-sub">{{ __('customers.sections.business_sub') }}</div>
                    </div></div>
                    <div class="card-body">
                        <div class="form-stack">
                            <label class="field-toggle">
                                <input type="hidden" name="is_business" value="0">
                                <input type="checkbox" name="is_business" value="1" x-model="isBusiness">
                                <span>{{ __('customers.fields.is_business') }}</span>
                            </label>

                            <template x-if="isBusiness">
                                <div class="form-stack">
                                    <label class="field">
                                        <span class="field-label">{{ __('customers.fields.business_name') }}</span>
                                        <input type="text" name="business_name" x-model="business.name" class="pos-input" maxlength="191">
                                        @error('business_name')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <label class="field">
                                            <span class="field-label">{{ __('customers.fields.gstin') }}</span>
                                            <input type="text" name="gstin" x-model="business.gstin" class="pos-input mono" maxlength="32">
                                            @error('gstin')<p class="field-error">{{ $message }}</p>@enderror
                                        </label>
                                        <label class="field">
                                            <span class="field-label">{{ __('customers.fields.pan') }}</span>
                                            <input type="text" name="pan" x-model="business.pan" class="pos-input mono" maxlength="16">
                                            @error('pan')<p class="field-error">{{ $message }}</p>@enderror
                                        </label>
                                    </div>
                                    <label class="field">
                                        <span class="field-label">{{ __('customers.fields.tax_reg') }}</span>
                                        <input type="text" name="tax_registration_number" x-model="business.taxReg" class="pos-input mono" maxlength="64">
                                        @error('tax_registration_number')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
                </div>{{-- end left column --}}

                <div class="space-y-5">
                {{-- Pricing & loyalty --}}
                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('customers.sections.pricing') }}</div>
                        <div class="card-title-sub">{{ __('customers.sections.pricing_sub') }}</div>
                    </div></div>
                    <div class="card-body">
                        <div class="form-stack">
                            <label class="field">
                                <span class="field-label">{{ __('customers.fields.group') }}</span>
                                <select name="customer_group_id" class="pos-input"
                                        x-data="enhancedSelect()" x-model="groupId">
                                    <option value="">—</option>
                                    @foreach ($groups as $group)
                                        <option value="{{ $group->id }}"
                                            @selected((string) old('customer_group_id', $customer->customer_group_id) === (string) $group->id)>{{ $group->name }}</option>
                                    @endforeach
                                </select>
                                @error('customer_group_id')<p class="field-error">{{ $message }}</p>@enderror
                            </label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="field">
                                    <span class="field-label">{{ __('customers.fields.discount') }}</span>
                                    <input type="number" step="0.01" min="0" max="100"
                                           name="default_discount_percent"
                                           x-model="discountPercent"
                                           class="pos-input tnum">
                                    <p class="field-help">{{ __('customers.fields.discount_help') }}</p>
                                    @error('default_discount_percent')<p class="field-error">{{ $message }}</p>@enderror
                                </label>
                                <label class="field">
                                    <span class="field-label">{{ __('customers.fields.credit_limit') }}</span>
                                    <input type="number" step="0.0001" min="0" name="credit_limit" value="{{ old('credit_limit', $customer->credit_limit) }}" class="pos-input tnum">
                                    <p class="field-help">{{ __('customers.fields.credit_limit_help') }}</p>
                                    @error('credit_limit')<p class="field-error">{{ $message }}</p>@enderror
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Notes --}}
                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('customers.sections.notes') }}</div>
                        <div class="card-title-sub">{{ __('customers.sections.notes_sub') }}</div>
                    </div></div>
                    <div class="card-body">
                        <label class="field">
                            <span class="field-label">{{ __('customers.fields.notes') }}</span>
                            <textarea name="notes" rows="5" class="pos-input" maxlength="5000">{{ old('notes', $customer->notes) }}</textarea>
                            @error('notes')<p class="field-error">{{ $message }}</p>@enderror
                        </label>
                    </div>
                </div>
                </div>{{-- end right column --}}
            </div>

            {{-- Addresses --}}
            <div class="card mt-5">
                <div class="card-header flex items-center justify-between">
                    <div>
                        <div class="card-title">{{ __('customers.sections.addresses') }}</div>
                        <div class="card-title-sub">{{ __('customers.sections.addresses_sub') }}</div>
                    </div>
                    <button type="button" @click="addAddress()" class="pos-btn pos-btn-sm pos-btn-ghost">
                        <x-icon name="plus" class="w-4 h-4" />
                        {{ __('customers.address.add') }}
                    </button>
                </div>
                <div class="card-body">
                    <template x-for="(addr, i) in addresses" :key="addr._uid">
                        <div class="addr-row form-stack">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <label class="field">
                                    <span class="field-label">{{ __('customers.address.label') }}</span>
                                    <input type="text" :name="`addresses[${i}][label]`" x-model="addr.label" class="pos-input" maxlength="32" placeholder="{{ __('customers.address.label_ph') }}">
                                </label>
                                <label class="field">
                                    <span class="field-label">{{ __('customers.address.line1') }}</span>
                                    <input type="text" :name="`addresses[${i}][line1]`" x-model="addr.line1" class="pos-input" maxlength="191">
                                </label>
                                <label class="field">
                                    <span class="field-label">{{ __('customers.address.line2') }}</span>
                                    <input type="text" :name="`addresses[${i}][line2]`" x-model="addr.line2" class="pos-input" maxlength="191">
                                </label>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                                <label class="field">
                                    <span class="field-label">{{ __('customers.address.city') }}</span>
                                    <input type="text" :name="`addresses[${i}][city]`" x-model="addr.city" class="pos-input" maxlength="100">
                                </label>
                                <label class="field">
                                    <span class="field-label">{{ __('customers.address.state') }}</span>
                                    <input type="text" :name="`addresses[${i}][state]`" x-model="addr.state" class="pos-input" maxlength="100">
                                </label>
                                <label class="field">
                                    <span class="field-label">{{ __('customers.address.postal') }}</span>
                                    <input type="text" :name="`addresses[${i}][postal_code]`" x-model="addr.postal_code" class="pos-input mono" maxlength="20">
                                </label>
                                <label class="field">
                                    <span class="field-label">{{ __('customers.address.country') }}</span>
                                    <select :name="`addresses[${i}][country_code]`"
                                            x-data="enhancedSelect({ value: addr.country_code })"
                                            x-model="addr.country_code"
                                            class="pos-input">
                                        <option value="">—</option>
                                        @foreach ($countries as $code => $name)
                                            <option value="{{ $code }}">{{ $name }} ({{ $code }})</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="field">
                                    <span class="field-label">{{ __('customers.address.landmark') }}</span>
                                    <input type="text" :name="`addresses[${i}][landmark]`" x-model="addr.landmark" class="pos-input" maxlength="191">
                                </label>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <label class="field-toggle">
                                    <input type="hidden" :name="`addresses[${i}][is_default]`" value="0">
                                    <input type="checkbox" :name="`addresses[${i}][is_default]`" value="1"
                                           :checked="addr.is_default"
                                           @change="makeDefault(addr._uid)">
                                    <span>{{ __('customers.address.is_default') }}</span>
                                </label>
                                <button type="button" class="pos-btn pos-btn-xs pos-btn-ghost pos-btn-danger"
                                        @click="removeAddress(addr._uid)">
                                    <x-icon name="trash" class="w-4 h-4" />
                                    {{ __('customers.address.remove') }}
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>
