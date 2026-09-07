<x-admin-layout
    active="settings"
    :title="__('settings.branding.title')"
    :crumbs="[
        ['label' => __('settings.crumb_parent')],
        ['label' => __('settings.title'), 'href' => route('admin.settings.index')],
        ['label' => __('settings.branding.title')],
    ]">

    @php
        $color = old('brand_color', $company->brand_color ?: '#F97316');
        $text  = old('brand_text_color', $company->brand_text_color ?: '#FFFFFF');
        $theme = old('theme_default', $company->theme_default ?: 'light');
    @endphp

    <div class="page-wide"
         x-data="brandingSettings({ accent: @js($color), text: @js($text) })">
        <x-admin.demo-lock-banner>{{ __('settings.demo.locked_banner_generic') }}</x-admin.demo-lock-banner>

        {{-- AJAX via lib/ajax-form.js. --}}
        <form method="POST" action="{{ route('admin.settings.branding.update') }}" enctype="multipart/form-data" data-ajax-form>
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
                        <h1 class="page-title">{{ __('settings.branding.title') }}</h1>
                        <p class="page-sub">{{ __('settings.branding.sub') }}</p>
                    </div>
                </div>
                <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="check" class="w-4 h-4" />
                    {{ __('settings.branding.actions.save') }}
                </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_380px] gap-5 items-start">
                {{-- Controls --}}
                <div class="space-y-5">

                    {{-- App name --}}
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('settings.branding.sections.app_name') }}</div>
                            <div class="card-title-sub">{{ __('settings.branding.sections.app_name_sub') }}</div>
                        </div></div>
                        <div class="card-body">
                            <label class="field">
                                <span class="field-label">{{ __('settings.branding.fields.app_name') }}</span>
                                <input type="text"
                                       name="app_name"
                                       value="{{ old('app_name', $company->app_name) }}"
                                       maxlength="100"
                                       class="pos-input"
                                       placeholder="{{ config('app.name') }}">
                                <p class="field-help">{{ __('settings.branding.fields.app_name_help') }}</p>
                            </label>
                            @error('app_name')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    {{-- Footer text --}}
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('settings.branding.sections.footer') }}</div>
                            <div class="card-title-sub">{{ __('settings.branding.sections.footer_sub') }}</div>
                        </div></div>
                        <div class="card-body">
                            <label class="field">
                                <span class="field-label">{{ __('settings.branding.fields.footer_text') }}</span>
                                <input type="text"
                                       name="footer_text"
                                       value="{{ old('footer_text', $company->footer_text) }}"
                                       maxlength="300"
                                       class="pos-input"
                                       placeholder="{{ __('settings.branding.fields.footer_text_ph', ['year' => now()->year, 'name' => $company->display_app_name]) }}">
                                <p class="field-help">{{ __('settings.branding.fields.footer_text_help') }}</p>
                            </label>
                            @error('footer_text')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    {{-- App logos — 2 × 2 grid (light / dark) × (full / collapsed) --}}
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('settings.branding.sections.logos') }}</div>
                            <div class="card-title-sub">{{ __('settings.branding.sections.logos_sub') }}</div>
                        </div></div>
                        <div class="card-body space-y-5">
                            {{-- Row 1: full logo --}}
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <p class="text-[12px] font-semibold text-[var(--text-primary)]">{{ __('settings.branding.sections.logo_full_section') }}</p>
                                    <span class="text-[11px] text-[var(--text-tertiary)] bg-[var(--bg-elevated)] border border-[var(--border-subtle)] rounded px-2 py-0.5 font-mono">
                                        {{ __('settings.branding.size_hint_full') }}
                                    </span>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <p class="field-label mb-2">{{ __('settings.branding.sections.logo_full_light') }}</p>
                                        <x-admin.image-upload
                                            name="app_logo"
                                            accept="image/png,image/svg+xml,image/jpeg,image/webp"
                                            :max-size-kb="1024"
                                            :initial-url="$company->app_logo_url"
                                            :empty-title="__('settings.branding.logo_empty_title')" />
                                        @error('app_logo')<p class="field-error">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <p class="field-label mb-2">{{ __('settings.branding.sections.logo_full_dark') }}</p>
                                        <x-admin.image-upload
                                            name="app_logo_dark"
                                            accept="image/png,image/svg+xml,image/jpeg,image/webp"
                                            :max-size-kb="1024"
                                            :initial-url="$company->app_logo_dark_url"
                                            :empty-title="__('settings.branding.logo_empty_title')" />
                                        @error('app_logo_dark')<p class="field-error">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                            </div>

                            <div class="border-t border-[var(--border-subtle)]"></div>

                            {{-- Row 2: collapsed / half logo --}}
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <p class="text-[12px] font-semibold text-[var(--text-primary)]">{{ __('settings.branding.sections.logo_half_section') }}</p>
                                    <span class="text-[11px] text-[var(--text-tertiary)] bg-[var(--bg-elevated)] border border-[var(--border-subtle)] rounded px-2 py-0.5 font-mono">
                                        {{ __('settings.branding.size_hint_half') }}
                                    </span>
                                </div>
                                <p class="text-[12px] text-[var(--text-tertiary)] mb-3">{{ __('settings.branding.sections.logo_half_hint') }}</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <p class="field-label mb-2">{{ __('settings.branding.sections.logo_half_light') }}</p>
                                        <x-admin.image-upload
                                            name="app_logo_half"
                                            accept="image/png,image/svg+xml,image/jpeg,image/webp"
                                            :max-size-kb="512"
                                            :initial-url="$company->app_logo_half_url"
                                            :empty-title="__('settings.branding.logo_half_empty_title')" />
                                        @error('app_logo_half')<p class="field-error">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <p class="field-label mb-2">{{ __('settings.branding.sections.logo_half_dark') }}</p>
                                        <x-admin.image-upload
                                            name="app_logo_half_dark"
                                            accept="image/png,image/svg+xml,image/jpeg,image/webp"
                                            :max-size-kb="512"
                                            :initial-url="$company->app_logo_half_dark_url"
                                            :empty-title="__('settings.branding.logo_half_empty_title')" />
                                        @error('app_logo_half_dark')<p class="field-error">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Favicon --}}
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('settings.branding.sections.favicon') }}</div>
                            <div class="card-title-sub">{{ __('settings.branding.sections.favicon_sub') }}</div>
                        </div></div>
                        <div class="card-body">
                            <x-admin.image-upload
                                name="favicon"
                                accept="image/png,image/svg+xml,image/x-icon,image/jpeg"
                                :max-size-kb="512"
                                :initial-url="$company->favicon_url"
                                :empty-title="__('settings.branding.favicon_empty_title')" />
                            @error('favicon')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    {{-- Colors --}}
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('settings.branding.sections.color') }}</div>
                            <div class="card-title-sub">{{ __('settings.branding.sections.color_sub') }}</div>
                        </div></div>
                        <div class="card-body">
                            <div class="form-stack">
                                {{-- Accent --}}
                                <div class="field">
                                    <span class="field-label">{{ __('settings.branding.fields.suggested') }}</span>
                                    <div class="brand-swatch-row">
                                        @foreach ($accentChoices as $hex)
                                            <button type="button" class="brand-swatch"
                                                    :class="{ 'is-active': accent.toLowerCase() === @js(strtolower($hex)) }"
                                                    style="--swatch-color: {{ $hex }};"
                                                    @click="setAccent({{ \Illuminate\Support\Js::from($hex) }})"
                                                    aria-label="{{ $hex }}"></button>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="field">
                                    <span class="field-label">{{ __('settings.branding.fields.custom') }}</span>
                                    <div class="brand-color-field">
                                        <input type="color" name="brand_color" x-model="accent" class="brand-color-swatch" aria-label="{{ __('settings.branding.fields.brand_color') }}">
                                        <span class="brand-color-hex mono" x-text="accent.toUpperCase()"></span>
                                        <button type="button" class="pos-btn pos-btn-xs pos-btn-ghost" @click="setAccent('#F97316')">
                                            {{ __('settings.branding.fields.reset_color') }}
                                        </button>
                                    </div>
                                    @error('brand_color')<p class="field-error">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Text color --}}
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('settings.branding.sections.text_color') }}</div>
                            <div class="card-title-sub">{{ __('settings.branding.sections.text_color_sub') }}</div>
                        </div></div>
                        <div class="card-body">
                            <div class="brand-color-field">
                                <div class="brand-swatch-row me-1">
                                    @foreach ($textChoices as $hex)
                                        <button type="button" class="brand-swatch"
                                                :class="{ 'is-active': text.toLowerCase() === @js(strtolower($hex)) }"
                                                style="--swatch-color: {{ $hex }};"
                                                @click="setText({{ \Illuminate\Support\Js::from($hex) }})"
                                                aria-label="{{ $hex }}"></button>
                                    @endforeach
                                </div>
                                <input type="color" name="brand_text_color" x-model="text" class="brand-color-swatch" aria-label="{{ __('settings.branding.fields.text_color') }}">
                                <span class="brand-color-hex mono" x-text="text.toUpperCase()"></span>
                            </div>
                            @error('brand_text_color')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    {{-- Default theme --}}
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('settings.branding.sections.theme') }}</div>
                            <div class="card-title-sub">{{ __('settings.branding.sections.theme_sub') }}</div>
                        </div></div>
                        <div class="card-body">
                            <label class="field">
                                <span class="field-label">{{ __('settings.branding.fields.theme_default') }}</span>
                                <select name="theme_default" class="pos-input"
                                        x-data="enhancedSelect()"
                                        x-effect="ts && ts.setValue(@js($theme), true)">
                                    <option value="light" @selected($theme === 'light')>{{ __('settings.branding.theme.light') }}</option>
                                    <option value="dark"  @selected($theme === 'dark')>{{ __('settings.branding.theme.dark') }}</option>
                                    <option value="auto"  @selected($theme === 'auto')>{{ __('settings.branding.theme.auto') }}</option>
                                </select>
                                @error('theme_default')<p class="field-error">{{ $message }}</p>@enderror
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Live preview --}}
                <div class="space-y-5 lg:sticky lg:top-4">
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('settings.branding.preview.title') }}</div>
                            <div class="card-title-sub">{{ __('settings.branding.preview.sub') }}</div>
                        </div></div>
                        <div class="card-body space-y-4">
                            <div class="brand-preview brand-preview-light" :style="previewStyle">
                                <span class="brand-preview-tag">{{ __('settings.branding.preview.light') }}</span>
                                <x-admin.brand-preview-body />
                            </div>
                            <div class="brand-preview brand-preview-dark" :style="previewStyle">
                                <span class="brand-preview-tag">{{ __('settings.branding.preview.dark') }}</span>
                                <x-admin.brand-preview-body />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </fieldset>
        </form>
    </div>
</x-admin-layout>
