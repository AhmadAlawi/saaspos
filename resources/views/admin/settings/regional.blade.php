<x-admin-layout
    active="settings"
    :title="__('settings.regional.title')"
    :crumbs="[
        ['label' => __('settings.crumb_parent')],
        ['label' => __('settings.title'), 'href' => route('admin.settings.index')],
        ['label' => __('settings.regional.title')],
    ]">

    @php
        $tz   = old('timezone', $company->timezone ?: config('app.timezone', 'UTC'));
        $df   = old('date_format', $company->date_format ?: 'd M Y');
        $tf   = old('time_format', $company->time_format ?: 'h:i A');
    @endphp

    <div class="page-wide"
         x-data="regionalSettings({
             timezone:   @js($tz),
             dateFormat: @js($df),
             timeFormat: @js($tf),
             dateMap:    {{ Js::from($dateFormats) }},
             timeMap:    {{ Js::from($timeFormats) }},
         })">

        {{-- AJAX via lib/ajax-form.js. --}}
        <form method="POST" action="{{ route('admin.settings.regional.update') }}" data-ajax-form>
            @csrf
            @method('PATCH')

            <div class="page-header mb-6">
                <div class="flex items-start gap-3">
                    <a href="{{ route('admin.settings.index') }}"
                       class="pos-btn pos-btn-sm pos-btn-ghost"
                       aria-label="{{ __('settings.title') }}">
                        <x-icon name="back" class="w-4 h-4" />
                    </a>
                    <div>
                        <h1 class="page-title">{{ __('settings.regional.title') }}</h1>
                        <p class="page-sub">{{ __('settings.regional.sub') }}</p>
                    </div>
                </div>
                <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="check" class="w-4 h-4" />
                    {{ __('settings.regional.actions.save') }}
                </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">
                <div class="space-y-5">
                    {{-- Time zone --}}
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <div class="card-title">{{ __('settings.regional.sections.timezone') }}</div>
                                <div class="card-title-sub">{{ __('settings.regional.sections.timezone_sub') }}</div>
                            </div>
                        </div>
                        <div class="card-body">
                            <label class="field">
                                <span class="field-label is-required">{{ __('settings.regional.fields.timezone') }}</span>
                                <select x-data="enhancedSelect()"
                                        x-effect="ts && ts.setValue(timezone, true)"
                                        x-model="timezone"
                                        name="timezone"
                                        class="pos-input">
                                    @foreach ($timezones as $zone)
                                        <option value="{{ $zone }}" @selected($tz === $zone)>{{ str_replace('_', ' ', $zone) }}</option>
                                    @endforeach
                                </select>
                                @error('timezone')<p class="field-error">{{ $message }}</p>@enderror
                            </label>
                        </div>
                    </div>

                    {{-- Date & time format --}}
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <div class="card-title">{{ __('settings.regional.sections.formats') }}</div>
                                <div class="card-title-sub">{{ __('settings.regional.sections.formats_sub') }}</div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="field">
                                    <span class="field-label is-required">{{ __('settings.regional.fields.date_format') }}</span>
                                    <select x-data="enhancedSelect()"
                                            x-effect="ts && ts.setValue(dateFormat, true)"
                                            x-model="dateFormat"
                                            name="date_format"
                                            class="pos-input">
                                        @foreach ($dateFormats as $fmt => $example)
                                            <option value="{{ $fmt }}" @selected($df === $fmt)>{{ $example }} — {{ $fmt }}</option>
                                        @endforeach
                                    </select>
                                    @error('date_format')<p class="field-error">{{ $message }}</p>@enderror
                                </label>

                                <label class="field">
                                    <span class="field-label is-required">{{ __('settings.regional.fields.time_format') }}</span>
                                    <select x-data="enhancedSelect()"
                                            x-effect="ts && ts.setValue(timeFormat, true)"
                                            x-model="timeFormat"
                                            name="time_format"
                                            class="pos-input">
                                        @foreach ($timeFormats as $fmt => $example)
                                            <option value="{{ $fmt }}" @selected($tf === $fmt)>{{ $example }} — {{ $fmt }}</option>
                                        @endforeach
                                    </select>
                                    @error('time_format')<p class="field-error">{{ $message }}</p>@enderror
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Financial year --}}
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <div class="card-title">{{ __('settings.regional.sections.fiscal') }}</div>
                                <div class="card-title-sub">{{ __('settings.regional.sections.fiscal_sub') }}</div>
                            </div>
                        </div>
                        <div class="card-body">
                            <label class="field">
                                <span class="field-label is-required">{{ __('settings.regional.fields.fiscal_year_start') }}</span>
                                <select x-data="enhancedSelect()" name="fiscal_year_start_month" class="pos-input">
                                    @foreach ($months as $num => $name)
                                        <option value="{{ $num }}" @selected($startMonth === $num)>{{ $name }}</option>
                                    @endforeach
                                </select>
                                <p class="field-help">{{ __('settings.regional.fields.fiscal_year_help', ['range' => $fyRange]) }}</p>
                                @error('fiscal_year_start_month')<p class="field-error">{{ $message }}</p>@enderror
                            </label>
                            @if ($hasFiscalYears)
                                <x-alert type="warning" class="mt-3">{{ __('settings.regional.fields.fiscal_year_warning') }}</x-alert>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Live preview --}}
                <div class="space-y-5 lg:sticky lg:top-4">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <div class="card-title">{{ __('settings.regional.preview') }}</div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="regional-preview">
                                <div class="regional-preview-date" x-text="datePreview"></div>
                                <div class="regional-preview-time" x-text="timePreview"></div>
                                <div class="regional-preview-zone mono" x-text="timezone.replace(/_/g, ' ')"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>
