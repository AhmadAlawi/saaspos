<x-admin-layout
    active="settings"
    :title="__('settings.currency.title')"
    :crumbs="[
        ['label' => __('settings.crumb_parent')],
        ['label' => __('settings.title'), 'href' => route('admin.settings.index')],
        ['label' => __('settings.currency.title')],
    ]">

    @php
        $cur = [
            'code'         => old('base_currency_code', $current['code']),
            'symbol'       => old('symbol', $current['symbol']),
            'symbol_first' => (bool) old('symbol_first', $current['symbol_first']),
            'decimals'     => (int) old('decimals', $current['decimals']),
            'thousands'    => old('thousands_separator', $current['thousands_separator']),
            'decimal'      => old('decimal_separator', $current['decimal_separator']),
        ];

        $currencyMap = $currencies->mapWithKeys(fn ($c) => [(string) $c->code => [
            'symbol'              => (string) $c->symbol,
            'symbol_first'        => (bool) $c->symbol_first,
            'decimals'            => (int) $c->decimals,
            'thousands_separator' => (string) $c->thousands_separator,
            'decimal_separator'   => (string) ($c->decimal_separator ?: '.'),
        ]])->all();
    @endphp

    <div class="page-wide"
         x-data="currencySettings({
             code:        '{{ $cur['code'] }}',
             symbol:      @js($cur['symbol']),
             symbolFirst: {{ $cur['symbol_first'] ? 'true' : 'false' }},
             decimals:    {{ $cur['decimals'] }},
             thousands:   @js($cur['thousands']),
             decimal:     @js($cur['decimal']),
             map:         {{ Js::from($currencyMap) }},
         })">

        {{-- AJAX via lib/ajax-form.js. --}}
        <form method="POST" action="{{ route('admin.settings.currency.update') }}" data-ajax-form>
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
                        <h1 class="page-title">{{ __('settings.currency.title') }}</h1>
                        <p class="page-sub">{{ __('settings.currency.sub') }}</p>
                    </div>
                </div>
                <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="check" class="w-4 h-4" />
                    {{ __('settings.currency.actions.save') }}
                </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">
                <div class="space-y-5">
                    {{-- Base currency --}}
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <div class="card-title">{{ __('settings.currency.section') }}</div>
                                <div class="card-title-sub">{{ __('settings.currency.section_sub') }}</div>
                            </div>
                        </div>
                        <div class="card-body">
                            <label class="field">
                                <span class="field-label is-required">{{ __('settings.currency.fields.currency') }}</span>
                                <select x-data="enhancedSelect()"
                                        x-effect="ts && ts.setValue(code, true)"
                                        x-model="code"
                                        name="base_currency_code"
                                        class="pos-input">
                                    @foreach ($currencies as $c)
                                        <option value="{{ $c->code }}" @selected($cur['code'] === $c->code)>
                                            {{ $c->code }} — {{ $c->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('base_currency_code')<p class="field-error">{{ $message }}</p>@enderror
                            </label>
                        </div>
                    </div>

                    {{-- Display format --}}
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <div class="card-title">{{ __('settings.currency.format') }}</div>
                                <div class="card-title-sub">{{ __('settings.currency.format_sub') }}</div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="form-stack">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label class="field">
                                        <span class="field-label is-required">{{ __('settings.currency.fields.symbol') }}</span>
                                        <input type="text" name="symbol" x-model="symbol"
                                               maxlength="8" class="pos-input"
                                               value="{{ $cur['symbol'] }}">
                                        @error('symbol')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>

                                    <label class="field">
                                        <span class="field-label is-required">{{ __('settings.currency.fields.decimals') }}</span>
                                        <select name="decimals" class="pos-input"
                                                x-data="enhancedSelect()"
                                                x-effect="ts && ts.setValue(String(decimals), true)"
                                                x-model.number="decimals">
                                            @for ($d = 0; $d <= 4; $d++)
                                                <option value="{{ $d }}" @selected($cur['decimals'] === $d)>{{ $d }}</option>
                                            @endfor
                                        </select>
                                        @error('decimals')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label class="field">
                                        <span class="field-label">{{ __('settings.currency.fields.thousands_separator') }}</span>
                                        <input type="text" name="thousands_separator" x-model="thousands"
                                               maxlength="2" class="pos-input"
                                               value="{{ $cur['thousands'] }}">
                                        <p class="field-help">{{ __('settings.currency.fields.thousands_help') }}</p>
                                        @error('thousands_separator')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>

                                    <label class="field">
                                        <span class="field-label is-required">{{ __('settings.currency.fields.decimal_separator') }}</span>
                                        <input type="text" name="decimal_separator" x-model="decimal"
                                               maxlength="2" class="pos-input"
                                               value="{{ $cur['decimal'] }}">
                                        @error('decimal_separator')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                </div>

                                <label class="field-toggle">
                                    <input type="hidden" name="symbol_first" value="0">
                                    <input type="checkbox" name="symbol_first" value="1"
                                           x-model="symbolFirst" @checked($cur['symbol_first'])>
                                    <span>
                                        <span class="block">{{ __('settings.currency.fields.symbol_first') }}</span>
                                        <span class="block text-[11.5px] fg-tertiary mt-0.5">{{ __('settings.currency.fields.symbol_first_help') }}</span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Live preview --}}
                <div class="space-y-5 lg:sticky lg:top-4">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <div class="card-title">{{ __('settings.currency.preview') }}</div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="currency-preview" x-text="preview">{{ format_money(1234567.5) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>
