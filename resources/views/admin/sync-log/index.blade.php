<x-admin-layout
    active="sync-log"
    :title="__('sync_log.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('sync_log.crumb_parent')],
        ['label' => __('sync_log.title')],
    ]">

    {{-- Server-paginated. Result tabs are page-nav links; the date range +
         search self-manage via the shared factory. x-data is page-wide so it
         wraps the summary cards it rewrites on every filter. --}}
    <div class="page-wide" x-data="syncLogPage({{ \Illuminate\Support\Js::from([
        'endpoint'   => route('admin.sync-log.rows'),
        'perPage'    => $perPage,
        'total'      => $total,
        'page'       => 1,
        'totalPages' => $totalPages,
    ]) }})">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('sync_log.title') }}</h1>
                <p class="page-sub">{{ __('sync_log.sub') }}</p>
            </div>
        </div>

        {{-- Status tabs — quick filter with live counts. --}}
        <div class="batches-tabs mb-4">
            @php
                $tabs = [
                    ''         => ['label' => __('sync_log.tabs.all'),      'count' => $counts['all']],
                    'success'  => ['label' => __('sync_log.tabs.success'), 'count' => $counts['success']],
                    'failed'   => ['label' => __('sync_log.tabs.failed'),  'count' => $counts['failed']],
                    'conflict' => ['label' => __('sync_log.tabs.conflict'),'count' => $counts['conflict']],
                ];
            @endphp
            @foreach ($tabs as $key => $tab)
                <a href="{{ route('admin.sync-log.index', array_merge(request()->query(), ['result' => $key])) }}"
                   class="batches-tab {{ $filters['result'] === $key ? 'is-active' : '' }}">
                    <span>{{ $tab['label'] }}</span>
                    <span class="batches-tab-count tnum">{{ $tab['count'] }}</span>
                </a>
            @endforeach
        </div>

        <x-admin.summary-cards :cards="$summaryCards" />

        <div class="card card-pad-0">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">
                    {{ __('sync_log.list_title') }} (<span x-text="matchedCount.toLocaleString()">{{ number_format($total) }}</span>)
                </div>
                <x-admin.dt-toolbar-actions :show-sort="false" />
            </div>

            {{-- Self-managed: the hidden `result` (set by the tabs) rides along in
                 every fetch; date + search changes call applyFilters(). --}}
            <form method="GET" action="{{ route('admin.sync-log.index') }}" class="inv-filter"
                  x-ref="filterForm" @submit.prevent="applyFilters()">
                <input type="hidden" name="result" value="{{ $filters['result'] }}">
                <label class="field inv-filter-date">
                    <span class="field-label">{{ __('sync_log.filter.from') }}</span>
                    <input type="text" name="from" value="{{ $filters['from'] }}"
                           class="pos-input js-datepicker" data-fp-submit-on-change="1">
                </label>
                <label class="field inv-filter-date">
                    <span class="field-label">{{ __('sync_log.filter.to') }}</span>
                    <input type="text" name="to" value="{{ $filters['to'] }}"
                           class="pos-input js-datepicker" data-fp-submit-on-change="1">
                </label>
                <label class="field inv-filter-search">
                    <span class="field-label">&nbsp;</span>
                    <span class="inv-search">
                        <span class="inv-search-icon"><x-icon name="search" class="w-4 h-4" /></span>
                        <input type="search" name="q" value="{{ $filters['q'] }}" class="pos-input"
                               placeholder="{{ __('sync_log.filter.search') }}"
                               @input.debounce.400ms="applyFilters()">
                    </span>
                </label>
                <button type="button" class="inv-filter-reset" @click="resetFilters()"
                        title="{{ __('table.filter_reset') }}">
                    <x-icon name="x" class="w-3.5 h-3.5" />
                    <span>{{ __('table.filter_reset') }}</span>
                </button>
            </form>

            {{-- No log rows match the current tab / filters. --}}
            <div class="dt-empty" x-show="dtReady && isEmpty" x-cloak>
                <span class="dt-empty-icon"><x-icon name="refresh" class="w-5 h-5" /></span>
                <div class="dt-empty-title">{{ __('sync_log.empty.title') }}</div>
                <div class="dt-empty-sub">{{ __('sync_log.empty.sub') }}</div>
            </div>

            <div class="dt-scroll" x-show="!isEmpty">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('sync_log.columns.synced_at') }}</th>
                            <th>{{ __('sync_log.columns.entity') }}</th>
                            <th>{{ __('sync_log.columns.local_uuid') }}</th>
                            <th>{{ __('sync_log.columns.result') }}</th>
                            <th>{{ __('sync_log.columns.user') }}</th>
                            <th>{{ __('sync_log.columns.message') }}</th>
                        </tr>
                    </thead>
                    <tbody data-dt-rows="table">
                        @include('admin.sync-log._rows', ['logs' => $logs])
                    </tbody>
                </table>
            </div>
            <x-admin.dt-pager />
        </div>
    </div>
</x-admin-layout>
