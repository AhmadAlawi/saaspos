@php
    $isEdit = ($mode ?? 'create') === 'edit';
    $action = $isEdit ? route('admin.languages.update', $language) : route('admin.languages.store');
    $isBase = $isEdit && $language->code === 'en';

    $v = fn (string $key, $default = null) => old($key, $isEdit ? data_get($language, $key) : $default);
@endphp

<div class="page-wide">
    {{-- AJAX via lib/ajax-form.js. --}}
    <form method="POST" action="{{ $action }}" data-ajax-form>
        @csrf
        @if ($isEdit) @method('PATCH') @endif

        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ route('admin.languages.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('languages.title') }}">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title">{{ $isEdit ? __('languages.edit.title') : __('languages.create.title') }}</h1>
                    <p class="page-sub">{{ $isEdit ? __('languages.edit.sub') : __('languages.create.sub') }}</p>
                </div>
            </div>
            <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                <x-icon name="check" class="w-4 h-4" />
                {{ $isEdit ? __('languages.actions.save') : __('languages.actions.create') }}
            </button>
        </div>

        <div class="card max-w-[640px]">
            <div class="card-body">
                <div class="form-stack">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="field">
                            <span class="field-label is-required">{{ __('languages.fields.code') }}</span>
                            <input type="text" name="code" value="{{ $v('code', '') }}" maxlength="12"
                                   class="pos-input mono" placeholder="ar" @if($isBase) disabled @endif>
                            @if($isBase)<input type="hidden" name="code" value="en">@endif
                            <p class="field-help">{{ __('languages.fields.code_help') }}</p>
                            @error('code')<p class="field-error">{{ $message }}</p>@enderror
                        </label>
                        <label class="field">
                            <span class="field-label is-required">{{ __('languages.fields.direction') }}</span>
                            <select name="direction" class="pos-input" x-data="enhancedSelect()"
                                    x-effect="ts && ts.setValue(@js($v('direction', 'ltr')), true)">
                                <option value="ltr" @selected($v('direction', 'ltr') === 'ltr')>{{ __('languages.fields.direction_ltr') }}</option>
                                <option value="rtl" @selected($v('direction', 'ltr') === 'rtl')>{{ __('languages.fields.direction_rtl') }}</option>
                            </select>
                            @error('direction')<p class="field-error">{{ $message }}</p>@enderror
                        </label>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="field">
                            <span class="field-label is-required">{{ __('languages.fields.name') }}</span>
                            <input type="text" name="name" value="{{ $v('name', '') }}" maxlength="64"
                                   class="pos-input" placeholder="{{ __('languages.fields.name_ph') }}">
                            @error('name')<p class="field-error">{{ $message }}</p>@enderror
                        </label>
                        <label class="field">
                            <span class="field-label">{{ __('languages.fields.native_name') }}</span>
                            <input type="text" name="native_name" value="{{ $v('native_name', '') }}" maxlength="64"
                                   class="pos-input" placeholder="{{ __('languages.fields.native_name_ph') }}">
                            @error('native_name')<p class="field-error">{{ $message }}</p>@enderror
                        </label>
                    </div>

                    <label class="field-toggle">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked($v('is_active', true))>
                        <span>
                            <span class="block">{{ __('languages.fields.is_active') }}</span>
                            <span class="block text-[11.5px] fg-tertiary mt-0.5">{{ __('languages.fields.is_active_help') }}</span>
                        </span>
                    </label>
                </div>
            </div>
        </div>
    </form>
</div>
