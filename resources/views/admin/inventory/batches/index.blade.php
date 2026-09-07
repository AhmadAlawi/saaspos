<x-admin-layout
    active="batches"
    :title="__('batches.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('batches.crumb_parent')],
        ['label' => __('batches.title')],
    ]">

    {{-- Server-paginated. Status tabs are page-nav links; store/days/search
         self-manage. x-data is page-wide so it wraps the summary cards. --}}
    <div class="page-wide" x-data="batchesPage({{ \Illuminate\Support\Js::from([
        'endpoint'   => route('admin.inventory.batches.rows'),
        'perPage'    => $perPage,
        'total'      => $total,
        'page'       => 1,
        'totalPages' => $totalPages,
    ]) }})">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('batches.title') }}</h1>
                <p class="page-sub">{{ __('batches.sub') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <div class="dropdown" x-data="dropdown">
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            @click="toggle()"
                            :aria-expanded="open">
                        <x-icon name="download" class="w-4 h-4" />
                        {{ __('batches.actions.export') }}
                        <x-icon name="chevron" class="w-4 h-4" />
                    </button>
                    <div class="dropdown-panel"
                         x-show="open" x-cloak
                         @click.outside="close()"
                         @keydown.escape.window="close()">
                        <a href="{{ route('admin.inventory.batches.export', array_merge(request()->query(), ['format' => 'csv'])) }}"
                           class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('batches.actions.export_csv') }}</span>
                        </a>
                        <a href="{{ route('admin.inventory.batches.export', array_merge(request()->query(), ['format' => 'xlsx'])) }}"
                           class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('batches.actions.export_xlsx') }}</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Status tabs — quick filters with live counts driven by the
             controller. Clicking a tab refreshes the page with the
             matching `?status=` value. --}}
        <div class="batches-tabs mb-4">
            @php
                $tabs = [
                    ''               => ['label' => __('batches.tabs.all'),           'count' => $counts['all']],
                    'live'           => ['label' => __('batches.tabs.live'),          'count' => $counts['live']],
                    'expiring_soon'  => ['label' => __('batches.tabs.expiring_soon'), 'count' => $counts['expiring_soon']],
                    'expired'        => ['label' => __('batches.tabs.expired'),       'count' => $counts['expired']],
                    // Soft-deleted batches live only here — every other tab
                    // filters them out, so this is the way back to one.
                    'archived'       => ['label' => __('batches.tabs.archived'),      'count' => $counts['archived']],
                ];
            @endphp
            @foreach ($tabs as $key => $tab)
                <a href="{{ route('admin.inventory.batches.index', array_merge(request()->query(), ['status' => $key])) }}"
                   class="batches-tab {{ $filters['status'] === $key ? 'is-active' : '' }}">
                    <span>{{ $tab['label'] }}</span>
                    <span class="batches-tab-count tnum">{{ $tab['count'] }}</span>
                </a>
            @endforeach
        </div>

        <x-admin.summary-cards :cards="$summaryCards" />

        <div class="card card-pad-0">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">
                    {{ __('batches.list_title') }} (<span x-text="matchedCount.toLocaleString()">{{ number_format($total) }}</span>)
                </div>
                <x-admin.dt-toolbar-actions :show-sort="false" />
            </div>

            {{-- Self-managed: hidden `status` (set by the tabs) rides along in
                 every fetch; store / days / search changes call applyFilters(). --}}
            <form method="GET" action="{{ route('admin.inventory.batches.index') }}" class="inv-filter"
                  x-ref="filterForm" @submit.prevent="applyFilters()">
                <input type="hidden" name="status" value="{{ $filters['status'] }}">
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('batches.columns.store') }}</span>
                    <select name="store_id" class="pos-input" x-data="enhancedSelect()" @change="applyFilters()">
                        <option value="">{{ __('batches.filter.store_all') }}</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" @selected($filters['storeId'] === $store->id)>{{ $store->name }}</option>
                        @endforeach
                    </select>
                </label>
                @if ($filters['status'] === 'expiring_soon')
                    <label class="field inv-filter-select">
                        <span class="field-label">{{ __('batches.filter.window') }}</span>
                        <select name="days" class="pos-input" x-data="enhancedSelect()" @change="applyFilters()">
                            @foreach ([7, 14, 30, 60, 90] as $d)
                                <option value="{{ $d }}" @selected($filters['days'] === $d)>{{ __('batches.filter.days', ['n' => $d]) }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <label class="field inv-filter-search">
                    <span class="field-label">&nbsp;</span>
                    <span class="inv-search">
                        <span class="inv-search-icon"><x-icon name="search" class="w-4 h-4" /></span>
                        <input type="search" name="q" value="{{ $filters['q'] }}" class="pos-input"
                               placeholder="{{ __('batches.filter.search') }}"
                               @input.debounce.400ms="applyFilters()">
                    </span>
                </label>
                <button type="button" class="inv-filter-reset" @click="resetFilters()"
                        title="{{ __('table.filter_reset') }}">
                    <x-icon name="x" class="w-3.5 h-3.5" />
                    <span>{{ __('table.filter_reset') }}</span>
                </button>
            </form>

            {{-- No batches match the current tab / filters. --}}
            <div class="dt-empty" x-show="dtReady && isEmpty" x-cloak>
                <span class="dt-empty-icon"><x-icon name="tag" class="w-5 h-5" /></span>
                <div class="dt-empty-title">{{ __('batches.empty.title') }}</div>
                <div class="dt-empty-sub">{{ __('batches.empty.sub') }}</div>
            </div>

            <div class="dt-scroll" x-show="!isEmpty">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('batches.columns.product') }}</th>
                            <th>{{ __('batches.columns.store') }}</th>
                            <th>{{ __('batches.columns.batch') }}</th>
                            <th>{{ __('batches.columns.mfg') }}</th>
                            <th>{{ __('batches.columns.expiry') }}</th>
                            <th class="num">{{ __('batches.columns.on_hand') }}</th>
                            <th>{{ __('batches.columns.status') }}</th>
                            <th class="dt-actions-col">{{ __('batches.columns.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody data-dt-rows="table">
                        @include('admin.inventory.batches._rows', ['batches' => $batches])
                    </tbody>
                </table>
            </div>
            <x-admin.dt-pager />
        </div>
    </div>
</x-admin-layout>
