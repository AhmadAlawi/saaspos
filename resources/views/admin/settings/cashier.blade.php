<x-admin-layout
    active="settings"
    :title="__('settings.cashier.title')"
    :crumbs="[
        ['label' => __('settings.crumb_parent')],
        ['label' => __('settings.title'), 'href' => route('admin.settings.index')],
        ['label' => __('settings.cashier.title')],
    ]">

    @php
        $c = $company->cashier();
        $v = [
            'layout'           => old('layout',           $c['layout']),
            'tile_size'        => old('tile_size',        $c['tile_size']),
            'theme_default'    => old('theme_default',    $c['theme_default']),
            'show_tax_line'    => (bool) old('show_tax_line',    $c['show_tax_line']),
            'show_from_prefix' => (bool) old('show_from_prefix', $c['show_from_prefix']),
            'show_quick_picks' => (bool) old('show_quick_picks', $c['show_quick_picks']),
            'sound_on_add'     => (bool) old('sound_on_add',     $c['sound_on_add'] ?? true),
            'default_category' => old('default_category', $c['default_category']),
            'allow_negative_stock' => (bool) old('allow_negative_stock', $c['allow_negative_stock'] ?? false),
        ];
    @endphp

    <div class="page-wide">
        {{-- AJAX submit via lib/ajax-form.js. --}}
        <form method="POST" action="{{ route('admin.settings.cashier.update') }}" data-ajax-form>
            @csrf
            @method('PATCH')

            <div class="page-header mb-6">
                <div class="flex items-start gap-3">
                    <a href="{{ route('admin.settings.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('settings.title') }}">
                        <x-icon name="back" class="w-4 h-4" />
                    </a>
                    <div>
                        <h1 class="page-title">{{ __('settings.cashier.title') }}</h1>
                        <p class="page-sub">{{ __('settings.cashier.sub') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('cashier.index') }}" target="_blank" class="pos-btn pos-btn-sm pos-btn-ghost">
                        <x-icon name="external" class="w-4 h-4" />
                        {{ __('cashier.title') }}
                    </a>
                    <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                        <x-icon name="check" class="w-4 h-4" />
                        {{ __('settings.cashier.actions.save') }}
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">
                {{-- Layout --}}
                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('settings.cashier.sections.layout') }}</div>
                        <div class="card-title-sub">{{ __('settings.cashier.sections.layout_sub') }}</div>
                    </div></div>
                    <div class="card-body">
                        <div class="form-stack">
                            {{-- Layout picker --}}
                            <div class="field">
                                <span class="field-label is-required">{{ __('settings.cashier.fields.layout') }}</span>
                                <div class="cashier-opt-cards" x-data="{ v: '{{ $v['layout'] }}' }">
                                    @foreach ($layouts as $L)
                                    <label class="cashier-opt-card" :class="{ 'is-active': v === '{{ $L }}' }">
                                        <input type="radio" name="layout" value="{{ $L }}"
                                               @checked($v['layout'] === $L)
                                               class="sr-only"
                                               @change="v = '{{ $L }}'">
                                        <div class="cashier-opt-preview">
                                            @if ($L === 'beam')
                                            <svg width="80" height="52" viewBox="0 0 80 52" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <rect width="80" height="52" rx="5" fill="var(--bg-elevated)"/>
                                                <rect x="4"  y="4"  width="12" height="10" rx="2" fill="var(--border-default)"/>
                                                <rect x="19" y="4"  width="12" height="10" rx="2" fill="var(--border-default)"/>
                                                <rect x="34" y="4"  width="12" height="10" rx="2" fill="var(--border-default)"/>
                                                <rect x="4"  y="17" width="12" height="10" rx="2" fill="var(--border-default)"/>
                                                <rect x="19" y="17" width="12" height="10" rx="2" fill="var(--border-default)"/>
                                                <rect x="34" y="17" width="12" height="10" rx="2" fill="var(--border-default)"/>
                                                <rect x="4"  y="30" width="12" height="10" rx="2" fill="var(--border-default)"/>
                                                <rect x="19" y="30" width="12" height="10" rx="2" fill="var(--border-default)"/>
                                                <rect x="34" y="30" width="12" height="10" rx="2" fill="var(--border-default)"/>
                                                <rect x="51" y="3"  width="26" height="46" rx="4" fill="var(--accent)" fill-opacity="0.12" stroke="var(--accent)" stroke-opacity="0.4" stroke-width="1"/>
                                                <rect x="54" y="7"  width="20" height="7"  rx="2" fill="var(--accent)" fill-opacity="0.25"/>
                                                <rect x="54" y="18" width="18" height="2"  rx="1" fill="var(--accent)" fill-opacity="0.5"/>
                                                <rect x="54" y="22" width="14" height="2"  rx="1" fill="var(--accent)" fill-opacity="0.35"/>
                                                <rect x="54" y="26" width="16" height="2"  rx="1" fill="var(--accent)" fill-opacity="0.35"/>
                                                <rect x="54" y="38" width="20" height="8"  rx="3" fill="var(--accent)"/>
                                            </svg>
                                            @elseif ($L === 'lane')
                                            <svg width="80" height="52" viewBox="0 0 80 52" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <rect width="80" height="52" rx="5" fill="var(--bg-elevated)"/>
                                                <rect x="3"  y="3"  width="26" height="46" rx="4" fill="var(--accent)" fill-opacity="0.12" stroke="var(--accent)" stroke-opacity="0.4" stroke-width="1"/>
                                                <rect x="6"  y="7"  width="20" height="7"  rx="2" fill="var(--accent)" fill-opacity="0.25"/>
                                                <rect x="6"  y="18" width="18" height="2"  rx="1" fill="var(--accent)" fill-opacity="0.5"/>
                                                <rect x="6"  y="22" width="14" height="2"  rx="1" fill="var(--accent)" fill-opacity="0.35"/>
                                                <rect x="6"  y="26" width="16" height="2"  rx="1" fill="var(--accent)" fill-opacity="0.35"/>
                                                <rect x="6"  y="38" width="20" height="8"  rx="3" fill="var(--accent)"/>
                                                <rect x="33" y="4"  width="12" height="10" rx="2" fill="var(--border-default)"/>
                                                <rect x="48" y="4"  width="12" height="10" rx="2" fill="var(--border-default)"/>
                                                <rect x="63" y="4"  width="13" height="10" rx="2" fill="var(--border-default)"/>
                                                <rect x="33" y="17" width="12" height="10" rx="2" fill="var(--border-default)"/>
                                                <rect x="48" y="17" width="12" height="10" rx="2" fill="var(--border-default)"/>
                                                <rect x="63" y="17" width="13" height="10" rx="2" fill="var(--border-default)"/>
                                                <rect x="33" y="30" width="12" height="10" rx="2" fill="var(--border-default)"/>
                                                <rect x="48" y="30" width="12" height="10" rx="2" fill="var(--border-default)"/>
                                                <rect x="63" y="30" width="13" height="10" rx="2" fill="var(--border-default)"/>
                                            </svg>
                                            @elseif ($L === 'counter')
                                            <svg width="80" height="52" viewBox="0 0 80 52" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <rect width="80" height="52" rx="5" fill="var(--bg-elevated)"/>
                                                <rect x="2"  y="2"  width="14" height="48" rx="3" fill="var(--border-default)" fill-opacity="0.7"/>
                                                <rect x="5"  y="6"  width="8"  height="6"  rx="1.5" fill="var(--accent)" fill-opacity="0.7"/>
                                                <rect x="5"  y="15" width="8"  height="4"  rx="1.5" fill="var(--bg-elevated)"/>
                                                <rect x="5"  y="22" width="8"  height="4"  rx="1.5" fill="var(--bg-elevated)"/>
                                                <rect x="5"  y="29" width="8"  height="4"  rx="1.5" fill="var(--bg-elevated)"/>
                                                <rect x="19" y="2"  width="35" height="48" rx="3" fill="var(--bg-surface)" stroke="var(--border-subtle)" stroke-width="1"/>
                                                <rect x="22" y="6"  width="29" height="8"  rx="2" fill="var(--border-default)" fill-opacity="0.8"/>
                                                <rect x="22" y="17" width="29" height="6"  rx="2" fill="var(--border-default)" fill-opacity="0.5"/>
                                                <rect x="22" y="26" width="29" height="6"  rx="2" fill="var(--border-default)" fill-opacity="0.5"/>
                                                <rect x="22" y="35" width="29" height="6"  rx="2" fill="var(--border-default)" fill-opacity="0.5"/>
                                                <rect x="57" y="2"  width="21" height="48" rx="3" fill="var(--accent)" fill-opacity="0.12" stroke="var(--accent)" stroke-opacity="0.4" stroke-width="1"/>
                                                <rect x="59" y="6"  width="17" height="6"  rx="2" fill="var(--accent)" fill-opacity="0.25"/>
                                                <rect x="59" y="16" width="15" height="2"  rx="1" fill="var(--accent)" fill-opacity="0.5"/>
                                                <rect x="59" y="21" width="12" height="2"  rx="1" fill="var(--accent)" fill-opacity="0.35"/>
                                                <rect x="59" y="26" width="14" height="2"  rx="1" fill="var(--accent)" fill-opacity="0.35"/>
                                                <rect x="59" y="38" width="17" height="8"  rx="3" fill="var(--accent)"/>
                                            </svg>
                                            @else
                                            {{-- Focus: narrow action rail | big cart | small collapsible catalog. --}}
                                            <svg width="80" height="52" viewBox="0 0 80 52" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <rect width="80" height="52" rx="5" fill="var(--bg-elevated)"/>
                                                <rect x="2"  y="2"  width="10" height="48" rx="3" fill="var(--border-default)" fill-opacity="0.7"/>
                                                <rect x="4"  y="6"  width="6"  height="6"  rx="1.5" fill="var(--accent)" fill-opacity="0.5"/>
                                                <rect x="4"  y="15" width="6"  height="6"  rx="1.5" fill="var(--accent)" fill-opacity="0.35"/>
                                                <rect x="4"  y="24" width="6"  height="6"  rx="1.5" fill="var(--accent)" fill-opacity="0.35"/>
                                                <rect x="16" y="2"  width="47" height="48" rx="3" fill="var(--accent)" fill-opacity="0.12" stroke="var(--accent)" stroke-opacity="0.4" stroke-width="1"/>
                                                <rect x="20" y="6"  width="39" height="6"  rx="2" fill="var(--accent)" fill-opacity="0.3"/>
                                                <rect x="20" y="16" width="39" height="6"  rx="2" fill="var(--accent)" fill-opacity="0.3"/>
                                                <rect x="20" y="26" width="39" height="6"  rx="2" fill="var(--accent)" fill-opacity="0.3"/>
                                                <rect x="20" y="38" width="39" height="8"  rx="3" fill="var(--accent)"/>
                                                <rect x="67" y="2"  width="11" height="48" rx="3" fill="var(--border-default)" fill-opacity="0.6"/>
                                            </svg>
                                            @endif
                                        </div>
                                        <span class="cashier-opt-label">{{ __('settings.cashier.layouts.'.$L) }}</span>
                                    </label>
                                    @endforeach
                                </div>
                                <span class="field-help">{{ __('settings.cashier.fields.layout_help') }}</span>
                            </div>

                            {{-- Tile size picker --}}
                            <div class="field">
                                <span class="field-label is-required">{{ __('settings.cashier.fields.tile_size') }}</span>
                                <div class="cashier-opt-cards" x-data="{ v: '{{ $v['tile_size'] }}' }">
                                    @foreach ($tileSizes as $T)
                                    <label class="cashier-opt-card" :class="{ 'is-active': v === '{{ $T }}' }">
                                        <input type="radio" name="tile_size" value="{{ $T }}"
                                               @checked($v['tile_size'] === $T)
                                               class="sr-only"
                                               @change="v = '{{ $T }}'">
                                        <div class="cashier-opt-preview">
                                            @if ($T === 'compact')
                                            <svg width="80" height="52" viewBox="0 0 80 52" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <rect width="80" height="52" rx="5" fill="var(--bg-elevated)"/>
                                                <rect x="4"  y="5"  width="14" height="11" rx="2" fill="var(--border-default)"/>
                                                <rect x="21" y="5"  width="14" height="11" rx="2" fill="var(--border-default)"/>
                                                <rect x="38" y="5"  width="14" height="11" rx="2" fill="var(--border-default)"/>
                                                <rect x="55" y="5"  width="14" height="11" rx="2" fill="var(--border-default)"/>
                                                <rect x="4"  y="19" width="14" height="11" rx="2" fill="var(--border-default)"/>
                                                <rect x="21" y="19" width="14" height="11" rx="2" fill="var(--border-default)"/>
                                                <rect x="38" y="19" width="14" height="11" rx="2" fill="var(--border-default)"/>
                                                <rect x="55" y="19" width="14" height="11" rx="2" fill="var(--border-default)"/>
                                                <rect x="4"  y="33" width="14" height="11" rx="2" fill="var(--border-default)"/>
                                                <rect x="21" y="33" width="14" height="11" rx="2" fill="var(--border-default)"/>
                                                <rect x="38" y="33" width="14" height="11" rx="2" fill="var(--border-default)"/>
                                                <rect x="55" y="33" width="14" height="11" rx="2" fill="var(--border-default)"/>
                                            </svg>
                                            @elseif ($T === 'comfortable')
                                            <svg width="80" height="52" viewBox="0 0 80 52" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <rect width="80" height="52" rx="5" fill="var(--bg-elevated)"/>
                                                <rect x="5"  y="6"  width="20" height="18" rx="3" fill="var(--border-default)"/>
                                                <rect x="29" y="6"  width="20" height="18" rx="3" fill="var(--border-default)"/>
                                                <rect x="53" y="6"  width="22" height="18" rx="3" fill="var(--border-default)"/>
                                                <rect x="5"  y="28" width="20" height="18" rx="3" fill="var(--border-default)"/>
                                                <rect x="29" y="28" width="20" height="18" rx="3" fill="var(--border-default)"/>
                                                <rect x="53" y="28" width="22" height="18" rx="3" fill="var(--border-default)"/>
                                            </svg>
                                            @else
                                            <svg width="80" height="52" viewBox="0 0 80 52" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <rect width="80" height="52" rx="5" fill="var(--bg-elevated)"/>
                                                <rect x="5"  y="6"  width="32" height="18" rx="3" fill="var(--border-default)"/>
                                                <rect x="42" y="6"  width="33" height="18" rx="3" fill="var(--border-default)"/>
                                                <rect x="5"  y="29" width="32" height="18" rx="3" fill="var(--border-default)"/>
                                                <rect x="42" y="29" width="33" height="18" rx="3" fill="var(--border-default)"/>
                                            </svg>
                                            @endif
                                        </div>
                                        <span class="cashier-opt-label">{{ __('settings.cashier.tile_sizes.'.$T) }}</span>
                                    </label>
                                    @endforeach
                                </div>
                                <span class="field-help">{{ __('settings.cashier.fields.tile_size_help') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Theme + defaults --}}
                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('settings.cashier.sections.theme') }}</div>
                        <div class="card-title-sub">{{ __('settings.cashier.sections.theme_sub') }}</div>
                    </div></div>
                    <div class="card-body">
                        <div class="form-stack">
                            {{-- Theme picker --}}
                            <div class="field">
                                <span class="field-label is-required">{{ __('settings.cashier.fields.theme_default') }}</span>
                                <div class="cashier-opt-cards" x-data="{ v: '{{ $v['theme_default'] }}' }">
                                    @foreach ($themes as $TH)
                                    <label class="cashier-opt-card" :class="{ 'is-active': v === '{{ $TH }}' }">
                                        <input type="radio" name="theme_default" value="{{ $TH }}"
                                               @checked($v['theme_default'] === $TH)
                                               class="sr-only"
                                               @change="v = '{{ $TH }}'">
                                        <div class="cashier-opt-preview">
                                            @if ($TH === 'light')
                                            <svg width="80" height="52" viewBox="0 0 80 52" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <rect width="80" height="52" rx="5" fill="#FAFAFA" stroke="#E2E2E6" stroke-width="1"/>
                                                <rect x="0"  y="0"  width="80" height="11" rx="5" fill="#F4F4F6"/>
                                                <rect x="0"  y="6"  width="80" height="5"  fill="#F4F4F6"/>
                                                <rect x="5"  y="3"  width="18" height="5"  rx="2" fill="#D0D0D5"/>
                                                <rect x="57" y="3"  width="18" height="5"  rx="2" fill="#D0D0D5"/>
                                                <rect x="5"  y="16" width="16" height="12" rx="2" fill="#E8E8EC"/>
                                                <rect x="25" y="16" width="16" height="12" rx="2" fill="#E8E8EC"/>
                                                <rect x="45" y="16" width="16" height="12" rx="2" fill="#E8E8EC"/>
                                                <rect x="5"  y="32" width="16" height="12" rx="2" fill="#E8E8EC"/>
                                                <rect x="25" y="32" width="16" height="12" rx="2" fill="#E8E8EC"/>
                                                <rect x="45" y="32" width="16" height="12" rx="2" fill="#E8E8EC"/>
                                                <rect x="65" y="15" width="12" height="29" rx="3" fill="#EEF0FF" stroke="#6366F1" stroke-opacity="0.4" stroke-width="1"/>
                                                <rect x="67" y="37" width="8"  height="5"  rx="2" fill="#6366F1"/>
                                            </svg>
                                            @elseif ($TH === 'dark')
                                            <svg width="80" height="52" viewBox="0 0 80 52" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <rect width="80" height="52" rx="5" fill="#0D0E10" stroke="#26272D" stroke-width="1"/>
                                                <rect x="0"  y="0"  width="80" height="11" rx="5" fill="#141519"/>
                                                <rect x="0"  y="6"  width="80" height="5"  fill="#141519"/>
                                                <rect x="5"  y="3"  width="18" height="5"  rx="2" fill="#26272D"/>
                                                <rect x="57" y="3"  width="18" height="5"  rx="2" fill="#26272D"/>
                                                <rect x="5"  y="16" width="16" height="12" rx="2" fill="#1C1D21"/>
                                                <rect x="25" y="16" width="16" height="12" rx="2" fill="#1C1D21"/>
                                                <rect x="45" y="16" width="16" height="12" rx="2" fill="#1C1D21"/>
                                                <rect x="5"  y="32" width="16" height="12" rx="2" fill="#1C1D21"/>
                                                <rect x="25" y="32" width="16" height="12" rx="2" fill="#1C1D21"/>
                                                <rect x="45" y="32" width="16" height="12" rx="2" fill="#1C1D21"/>
                                                <rect x="65" y="15" width="12" height="29" rx="3" fill="#1E1F3A" stroke="#6366F1" stroke-opacity="0.5" stroke-width="1"/>
                                                <rect x="67" y="37" width="8"  height="5"  rx="2" fill="#6366F1"/>
                                            </svg>
                                            @else
                                            <svg width="80" height="52" viewBox="0 0 80 52" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <rect width="80" height="52" rx="5" fill="var(--bg-elevated)" stroke="var(--border-subtle)" stroke-width="1"/>
                                                <line x1="40" y1="4" x2="40" y2="48" stroke="var(--border-default)" stroke-width="1" stroke-dasharray="2 2"/>
                                                <circle cx="22" cy="26" r="6"  fill="#FBBF24"/>
                                                <line x1="22" y1="14" x2="22" y2="16" stroke="#FBBF24" stroke-width="1.5" stroke-linecap="round"/>
                                                <line x1="22" y1="36" x2="22" y2="38" stroke="#FBBF24" stroke-width="1.5" stroke-linecap="round"/>
                                                <line x1="10" y1="26" x2="12" y2="26" stroke="#FBBF24" stroke-width="1.5" stroke-linecap="round"/>
                                                <line x1="32" y1="26" x2="34" y2="26" stroke="#FBBF24" stroke-width="1.5" stroke-linecap="round"/>
                                                <line x1="14" y1="18" x2="15.4" y2="19.4" stroke="#FBBF24" stroke-width="1.5" stroke-linecap="round"/>
                                                <line x1="28.6" y1="32.6" x2="30" y2="34" stroke="#FBBF24" stroke-width="1.5" stroke-linecap="round"/>
                                                <line x1="30" y1="18" x2="28.6" y2="19.4" stroke="#FBBF24" stroke-width="1.5" stroke-linecap="round"/>
                                                <line x1="15.4" y1="32.6" x2="14" y2="34" stroke="#FBBF24" stroke-width="1.5" stroke-linecap="round"/>
                                                <circle cx="58" cy="26" r="8" fill="#818CF8"/>
                                                <circle cx="63" cy="21" r="6" fill="var(--bg-elevated)"/>
                                            </svg>
                                            @endif
                                        </div>
                                        <span class="cashier-opt-label">{{ __('settings.cashier.themes.'.$TH) }}</span>
                                    </label>
                                    @endforeach
                                </div>
                                <span class="field-help">{{ __('settings.cashier.fields.theme_help') }}</span>
                            </div>

                            <label class="field">
                                <span class="field-label">{{ __('settings.cashier.fields.default_category') }}</span>
                                <select name="default_category" class="pos-input" x-data="enhancedSelect()">
                                    <option value="all" @selected($v['default_category'] === 'all')>{{ __('settings.cashier.fields.default_category_all') }}</option>
                                    @foreach ($categories as $cat)
                                        <option value="{{ $cat->id }}" @selected((string) $v['default_category'] === (string) $cat->id)>{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                                <span class="field-help">{{ __('settings.cashier.fields.default_category_help') }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Totals & badges --}}
                <div class="card lg:col-span-2">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('settings.cashier.sections.show') }}</div>
                        <div class="card-title-sub">{{ __('settings.cashier.sections.show_sub') }}</div>
                    </div></div>
                    <div class="card-body">
                        <div class="form-stack">
                            @foreach ([
                                ['name' => 'show_tax_line',    'val' => $v['show_tax_line'],    'params' => []],
                                ['name' => 'show_from_prefix', 'val' => $v['show_from_prefix'], 'params' => ['symbol' => app_currency()['symbol'] ?? '$']],
                                ['name' => 'show_quick_picks', 'val' => $v['show_quick_picks'], 'params' => []],
                                ['name' => 'sound_on_add',     'val' => $v['sound_on_add'],     'params' => []],
                            ] as $t)
                                <label class="field-toggle">
                                    <input type="hidden" name="{{ $t['name'] }}" value="0">
                                    <input type="checkbox" name="{{ $t['name'] }}" value="1" @checked($t['val'])>
                                    <span>{{ __('settings.cashier.fields.'.$t['name'], $t['params']) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Stock policy --}}
                <div class="card lg:col-span-2">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('settings.cashier.sections.stock') }}</div>
                        <div class="card-title-sub">{{ __('settings.cashier.sections.stock_sub') }}</div>
                    </div></div>
                    <div class="card-body">
                        <div class="form-stack">
                            <label class="field-toggle">
                                <input type="hidden" name="allow_negative_stock" value="0">
                                <input type="checkbox" name="allow_negative_stock" value="1" @checked($v['allow_negative_stock'])>
                                <span>{{ __('settings.cashier.fields.allow_negative_stock') }}</span>
                            </label>
                            <span class="field-help">{{ __('settings.cashier.fields.allow_negative_stock_help') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>
