<x-admin-layout
    active="settings"
    :title="__('settings.scale.title')"
    :crumbs="[
        ['label' => __('settings.crumb_parent')],
        ['label' => __('settings.title'), 'href' => route('admin.settings.index')],
        ['label' => __('settings.scale.title')],
    ]">

    @php
        $s = $company->scale();
        $v = [
            'enabled'        => (bool) old('enabled',        $s['enabled']),
            'prefix'         => old('prefix',         $s['prefix']),
            'plu_length'     => (int) old('plu_length',     $s['plu_length']),
            'value_offset'   => (int) old('value_offset',   $s['value_offset']),
            'value_length'   => (int) old('value_length',   $s['value_length']),
            'value_decimals' => (int) old('value_decimals', $s['value_decimals']),
            'embed_type'     => old('embed_type',     $s['embed_type']),
        ];
    @endphp

    <div class="page-wide"
         x-data="scaleSettingsPage({
            prefix:         @js($v['prefix']),
            plu_length:     {{ $v['plu_length'] }},
            value_offset:   {{ $v['value_offset'] }},
            value_length:   {{ $v['value_length'] }},
            value_decimals: {{ $v['value_decimals'] }},
            embed_type:     @js($v['embed_type']),
         })">
        {{-- AJAX submit via lib/ajax-form.js. --}}
        <form method="POST" action="{{ route('admin.settings.scale.update') }}" data-ajax-form>
            @csrf
            @method('PATCH')

            <div class="page-header mb-6">
                <div class="flex items-start gap-3">
                    <a href="{{ route('admin.settings.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('settings.title') }}">
                        <x-icon name="back" class="w-4 h-4" />
                    </a>
                    <div>
                        <h1 class="page-title">{{ __('settings.scale.title') }}</h1>
                        <p class="page-sub">{{ __('settings.scale.sub') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                        <x-icon name="check" class="w-4 h-4" />
                        {{ __('settings.scale.actions.save') }}
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">
                {{-- Enable --}}
                <div class="card lg:col-span-2">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('settings.scale.sections.enable') }}</div>
                        <div class="card-title-sub">{{ __('settings.scale.sections.enable_sub') }}</div>
                    </div></div>
                    <div class="card-body">
                        <label class="field-toggle">
                            <input type="hidden" name="enabled" value="0">
                            <input type="checkbox" name="enabled" value="1" @checked($v['enabled'])>
                            <span>{{ __('settings.scale.fields.enabled') }}</span>
                        </label>
                    </div>
                </div>

                {{-- Barcode layout --}}
                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('settings.scale.sections.layout') }}</div>
                        <div class="card-title-sub">{{ __('settings.scale.sections.layout_sub') }}</div>
                    </div></div>
                    <div class="card-body">
                        <div class="form-stack">
                            <label class="field">
                                <span class="field-label is-required">{{ __('settings.scale.fields.prefix') }}</span>
                                <input type="text" name="prefix" class="pos-input"
                                       x-model="cfg.prefix"
                                       inputmode="numeric" maxlength="3" autocomplete="off">
                                <span class="field-help">{{ __('settings.scale.fields.prefix_help') }}</span>
                            </label>

                            <label class="field">
                                <span class="field-label is-required">{{ __('settings.scale.fields.plu_length') }}</span>
                                <input type="number" name="plu_length" class="pos-input"
                                       x-model.number="cfg.plu_length"
                                       min="1" max="8" step="1" inputmode="numeric">
                                <span class="field-help">{{ __('settings.scale.fields.plu_length_help') }}</span>
                            </label>

                            <label class="field">
                                <span class="field-label is-required">{{ __('settings.scale.fields.value_offset') }}</span>
                                <input type="number" name="value_offset" class="pos-input"
                                       x-model.number="cfg.value_offset"
                                       min="0" max="4" step="1" inputmode="numeric">
                                <span class="field-help">{{ __('settings.scale.fields.value_offset_help') }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Value --}}
                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('settings.scale.sections.value') }}</div>
                        <div class="card-title-sub">{{ __('settings.scale.sections.value_sub') }}</div>
                    </div></div>
                    <div class="card-body">
                        <div class="form-stack">
                            <label class="field">
                                <span class="field-label is-required">{{ __('settings.scale.fields.value_length') }}</span>
                                <input type="number" name="value_length" class="pos-input"
                                       x-model.number="cfg.value_length"
                                       min="1" max="8" step="1" inputmode="numeric">
                                <span class="field-help">{{ __('settings.scale.fields.value_length_help') }}</span>
                            </label>

                            <label class="field">
                                <span class="field-label is-required">{{ __('settings.scale.fields.value_decimals') }}</span>
                                <input type="number" name="value_decimals" class="pos-input"
                                       x-model.number="cfg.value_decimals"
                                       min="0" max="4" step="1" inputmode="numeric">
                                <span class="field-help">{{ __('settings.scale.fields.value_decimals_help') }}</span>
                            </label>

                            <label class="field">
                                <span class="field-label is-required">{{ __('settings.scale.fields.embed_type') }}</span>
                                {{-- x-effect keeps TomSelect's chip in step when `cfg` is reset in JS. --}}
                                <select name="embed_type" class="pos-input"
                                        x-data="enhancedSelect()"
                                        x-effect="ts && ts.setValue(cfg.embed_type ?? '', true)"
                                        x-model="cfg.embed_type">
                                    @foreach ($embedTypes as $et)
                                        <option value="{{ $et }}" @selected($v['embed_type'] === $et)>{{ __('settings.scale.embed_types.'.$et) }}</option>
                                    @endforeach
                                </select>
                                <span class="field-help">{{ __('settings.scale.fields.embed_type_help') }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Live preview --}}
                <div class="card lg:col-span-2">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('settings.scale.preview.title') }}</div>
                        <div class="card-title-sub">{{ __('settings.scale.preview.help') }}</div>
                    </div></div>
                    <div class="card-body">
                        <div class="form-stack">
                            <label class="field">
                                <div class="flex items-center gap-2">
                                    <input type="text" class="pos-input mono"
                                           x-model="sample"
                                           inputmode="numeric" autocomplete="off"
                                           placeholder="2 12345 01500 8">
                                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost shrink-0"
                                            @click="sample = exampleCode">
                                        {{ __('settings.scale.preview.try') }}
                                    </button>
                                </div>
                            </label>

                            <div class="scale-preview" x-show="sample.trim() !== ''" x-cloak>
                                <template x-if="decoded">
                                    <div class="scale-preview-hit">
                                        <x-icon name="check" class="w-4 h-4" />
                                        <span>
                                            {{ __('settings.scale.preview.plu') }}
                                            <strong class="mono" x-text="decoded.plu"></strong>
                                            <span class="fg-tertiary"> · </span>
                                            <span x-text="cfg.embed_type === 'price' ? @js(__('settings.scale.preview.price')) : @js(__('settings.scale.preview.weight'))"></span>
                                            <strong class="tnum" x-text="decodedValue"></strong>
                                        </span>
                                    </div>
                                </template>
                                <template x-if="!decoded">
                                    <div class="scale-preview-miss">
                                        <x-icon name="alert" class="w-4 h-4" />
                                        <span>{{ __('settings.scale.preview.no_match') }}</span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>
