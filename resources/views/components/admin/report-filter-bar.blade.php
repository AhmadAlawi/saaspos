@props([
    'route',                    // route name used for both the form action and the reset link
    'searchPlaceholder' => '',  // placeholder for the client-side search box
    'searchable' => true,       // render the client-side search box (needs a dataTable scope)
    'period' => 'this_month',
    'from',                     // CarbonImmutable
    'to',                       // CarbonImmutable
    'storeId' => null,
    'stores' => [],
])

{{--
    Universal report toolbar (docs/features/reports.md §3). A period preset
    selector drives the range server-side; the From/To dates only appear (and
    only matter) when "Custom range" is chosen. Store + client-side search
    mirror the shared inv-filter layout used across the reports.
--}}
<form method="GET" action="{{ route($route) }}" class="inv-filter" x-data="{ period: @js($period) }"
    {{-- lib/inv-filter-ajax.js resets the period <select> silently (no change
         event), so keep Alpine's `period` in step after every apply/reset —
         otherwise the custom From/To stay shown after a reset. --}}
    x-on:dt:recollect="$nextTick(() => { const s = $el.querySelector('select[name=period]'); if (s) period = s.value; })">
    <label class="field inv-filter-select">
        <span class="field-label">{{ __('reports.period.label') }}</span>
        <select name="period" class="pos-input" x-data="enhancedSelect()" x-model="period"
                @change="$event.target.value !== 'custom' && $el.closest('form').submit()">
            @foreach (\App\Http\Controllers\Admin\Concerns\ResolvesReportFilters::reportPeriodPresets() as $preset)
                <option value="{{ $preset }}" @selected($period === $preset)>{{ __('reports.period.presets.'.$preset) }}</option>
            @endforeach
        </select>
    </label>

    <label class="field inv-filter-date" x-show="period === 'custom'" x-cloak>
        <span class="field-label">{{ __('reports.filter.from') }}</span>
        <input type="text" name="from" value="{{ $from->toDateString() }}" class="pos-input js-datepicker" data-fp-submit-on-change="1">
    </label>
    <label class="field inv-filter-date" x-show="period === 'custom'" x-cloak>
        <span class="field-label">{{ __('reports.filter.to') }}</span>
        <input type="text" name="to" value="{{ $to->toDateString() }}" class="pos-input js-datepicker" data-fp-submit-on-change="1">
    </label>

    <label class="field inv-filter-select">
        <span class="field-label">{{ __('reports.filter.store') }}</span>
        <select name="store_id" class="pos-input" x-data="enhancedSelect()" onchange="this.form.submit()">
            <option value="">{{ __('reports.filter.store_all') }}</option>
            @foreach ($stores as $store)
                <option value="{{ $store->id }}" @selected($storeId === $store->id)>{{ $store->name }}</option>
            @endforeach
        </select>
    </label>

    @if ($searchable)
        <label class="field inv-filter-search">
            <span class="field-label">&nbsp;</span>
            <span class="inv-search">
                <span class="inv-search-icon"><x-icon name="search" class="w-4 h-4" /></span>
                <input type="search" x-model.debounce.150ms="search" data-no-live-search class="pos-input" placeholder="{{ $searchPlaceholder }}" @keydown.enter.prevent>
            </span>
        </label>
    @endif

    <x-admin.filter-reset :route="$route" />

    @if ($saveKey = \App\Support\ReportRegistry::keyForRoute($route))
        <x-admin.report-save-button :report-key="$saveKey" :can-share="auth()->user()?->hasPermission('reports.save_shared') ?? false" />
        @if (auth()->user()?->hasPermission('reports.schedule'))
            <x-admin.report-schedule-button :report-key="$saveKey" />
        @endif
    @endif
</form>
