@php
    $store  ??= null;
    $isEdit = ($mode ?? 'create') === 'edit';
    $action = $isEdit ? route('admin.stores.update', $store) : route('admin.stores.store');

    $v = fn (string $key, $default = null) => old($key, $isEdit ? data_get($store, $key) : $default);
    $vb = fn (string $key, bool $default = false) => (bool) old($key, $isEdit ? (bool) data_get($store, $key) : $default);

    $code = $v('code', '');
    $name = $v('name', '');
@endphp

<div class="page-wide">
    {{-- AJAX via lib/ajax-form.js. --}}
    <form method="POST" action="{{ $action }}" data-ajax-form>
        @csrf
        @if ($isEdit) @method('PATCH') @endif

        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ route('admin.stores.index') }}"
                   class="pos-btn pos-btn-sm pos-btn-ghost"
                   aria-label="{{ __('stores.title') }}">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title">{{ $isEdit ? __('stores.edit.title') : __('stores.create.title') }}</h1>
                    <p class="page-sub">{{ $isEdit ? __('stores.edit.sub') : __('stores.create.sub') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.stores.index') }}"
                   class="pos-btn pos-btn-sm pos-btn-ghost">
                    {{ __('stores.actions.cancel') }}
                </a>
                <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="check" class="w-4 h-4" />
                    {{ $isEdit ? __('stores.actions.save') : __('stores.actions.create') }}
                </button>
            </div>
        </div>

        <div class="space-y-5">
            {{-- Identity --}}
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">{{ __('stores.form.identity') }}</div>
                        <div class="card-title-sub">{{ __('stores.form.identity_sub') }}</div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-stack">
                        <div class="grid grid-cols-1 sm:grid-cols-[160px_minmax(0,1fr)] gap-3">
                            <label class="field">
                                <span class="field-label is-required">{{ __('stores.fields.code') }}</span>
                                <input type="text" name="code" value="{{ $code }}" maxlength="32"
                                       class="pos-input mono" placeholder="{{ __('stores.fields.code_placeholder') }}">
                                <p class="field-help">{{ __('stores.fields.code_help') }}</p>
                                @error('code')<p class="field-error">{{ $message }}</p>@enderror
                            </label>
                            <label class="field">
                                <span class="field-label is-required">{{ __('stores.fields.name') }}</span>
                                <input type="text" name="name" value="{{ $name }}" maxlength="191"
                                       class="pos-input" placeholder="{{ __('stores.fields.name_placeholder') }}">
                                @error('name')<p class="field-error">{{ $message }}</p>@enderror
                            </label>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="field">
                                <span class="field-label">{{ __('stores.fields.phone') }}</span>
                                <input type="text" name="phone" value="{{ $v('phone', '') }}" maxlength="32" class="pos-input">
                                @error('phone')<p class="field-error">{{ $message }}</p>@enderror
                            </label>
                            <label class="field">
                                <span class="field-label">{{ __('stores.fields.email') }}</span>
                                <input type="email" name="email" value="{{ $v('email', '') }}" maxlength="191" class="pos-input">
                                @error('email')<p class="field-error">{{ $message }}</p>@enderror
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Address --}}
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">{{ __('stores.form.address') }}</div>
                        <div class="card-title-sub">{{ __('stores.form.address_sub') }}</div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-stack">
                        <label class="field">
                            <span class="field-label">{{ __('stores.fields.address_line1') }}</span>
                            <input type="text" name="address_line1" value="{{ $v('address_line1', '') }}" maxlength="191" class="pos-input">
                            @error('address_line1')<p class="field-error">{{ $message }}</p>@enderror
                        </label>
                        <label class="field">
                            <span class="field-label">{{ __('stores.fields.address_line2') }}</span>
                            <input type="text" name="address_line2" value="{{ $v('address_line2', '') }}" maxlength="191" class="pos-input">
                            @error('address_line2')<p class="field-error">{{ $message }}</p>@enderror
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="field">
                                <span class="field-label">{{ __('stores.fields.city') }}</span>
                                <input type="text" name="city" value="{{ $v('city', '') }}" maxlength="100" class="pos-input">
                                @error('city')<p class="field-error">{{ $message }}</p>@enderror
                            </label>
                            <label class="field">
                                <span class="field-label">{{ __('stores.fields.state') }}</span>
                                <input type="text" name="state" value="{{ $v('state', '') }}" maxlength="100" class="pos-input">
                                @error('state')<p class="field-error">{{ $message }}</p>@enderror
                            </label>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="field">
                                <span class="field-label">{{ __('stores.fields.postal_code') }}</span>
                                <input type="text" name="postal_code" value="{{ $v('postal_code', '') }}" maxlength="20" class="pos-input">
                                @error('postal_code')<p class="field-error">{{ $message }}</p>@enderror
                            </label>
                            <label class="field">
                                <span class="field-label">{{ __('stores.fields.country_code') }}</span>
                                <select name="country_code" class="pos-input" x-data="enhancedSelect({ maxOptions: 300 })">
                                    <option value="">{{ __('stores.fields.country_code_placeholder') }}</option>
                                    @foreach ($countries as $code => $name)
                                        <option value="{{ $code }}" @selected($v('country_code', '') === $code)>{{ $name }}</option>
                                    @endforeach
                                </select>
                                @error('country_code')<p class="field-error">{{ $message }}</p>@enderror
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Behavior --}}
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">{{ __('stores.form.behavior') }}</div>
                        <div class="card-title-sub">{{ __('stores.form.behavior_sub') }}</div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-stack">
                        <label class="field-toggle">
                            <input type="hidden" name="enforce_shifts" value="0">
                            <input type="checkbox" name="enforce_shifts" value="1" @checked($vb('enforce_shifts', true))>
                            <span>
                                <span class="block">{{ __('stores.fields.enforce_shifts') }}</span>
                                <span class="block text-[11.5px] fg-tertiary mt-0.5">{{ __('stores.fields.enforce_shifts_help') }}</span>
                            </span>
                        </label>
                        <label class="field-toggle">
                            <input type="hidden" name="require_day_open" value="0">
                            <input type="checkbox" name="require_day_open" value="1" @checked($vb('require_day_open', false))>
                            <span>
                                <span class="block">{{ __('stores.fields.require_day_open') }}</span>
                                <span class="block text-[11.5px] fg-tertiary mt-0.5">{{ __('stores.fields.require_day_open_help') }}</span>
                            </span>
                        </label>
                        <label class="field">
                            <span class="field-label">{{ __('stores.fields.discount_threshold') }}</span>
                            <input type="number" name="discount_threshold_percent"
                                   step="0.01" min="0" max="100"
                                   value="{{ $v('discount_threshold_percent', 10) }}"
                                   class="pos-input num tnum">
                            <p class="field-help">{{ __('stores.fields.discount_threshold_help') }}</p>
                        </label>

                        <label class="field">
                            <span class="field-label">{{ __('stores.fields.receipt_template') }}</span>
                            <select name="receipt_template_id" class="pos-input">
                                <option value="">{{ __('stores.fields.receipt_template_default') }}</option>
                                @foreach (($receiptTemplates ?? []) as $tpl)
                                    <option value="{{ $tpl->id }}" @selected((string) $v('receipt_template_id') === (string) $tpl->id)>
                                        {{ $tpl->name }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="field-help">{{ __('stores.fields.receipt_template_help') }}</p>
                        </label>

                        <label class="field-toggle">
                            <input type="hidden" name="tax_inclusive_pricing" value="0">
                            <input type="checkbox" name="tax_inclusive_pricing" value="1" @checked($vb('tax_inclusive_pricing', false))>
                            <span>
                                <span class="block">{{ __('stores.fields.tax_inclusive_pricing') }}</span>
                                <span class="block text-[11.5px] fg-tertiary mt-0.5">{{ __('stores.fields.tax_inclusive_pricing_help') }}</span>
                            </span>
                        </label>
                        <label class="field-toggle">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" @checked($vb('is_active', true))>
                            <span>
                                <span class="block">{{ __('stores.fields.is_active') }}</span>
                                <span class="block text-[11.5px] fg-tertiary mt-0.5">{{ __('stores.fields.is_active_help') }}</span>
                            </span>
                        </label>

                        <label class="field-toggle">
                            <input type="hidden" name="is_default" value="0">
                            <input type="checkbox" name="is_default" value="1"
                                   @checked($vb('is_default', false))
                                   @if($isEdit && $store->is_default) checked disabled @endif>
                            <span>
                                <span class="block">{{ __('stores.fields.is_default') }}</span>
                                <span class="block text-[11.5px] fg-tertiary mt-0.5">{{ __('stores.fields.is_default_help') }}</span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
