<x-admin-layout
    active="stock-levels"
    :title="__('inventory.levels.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('inventory.nav.section')],
        ['label' => __('inventory.levels.title')],
    ]">

    {{-- Server-paginated. x-data is page-wide so it wraps the summary cards it
         rewrites on every filter. Self-managed filter form → server mixin. --}}
    <div class="page-wide" x-data="stockLevelsPage({{ \Illuminate\Support\Js::from([
        'endpoint'   => route('admin.inventory.levels.rows'),
        'perPage'    => $perPage,
        'total'      => $total,
        'page'       => 1,
        'totalPages' => $totalPages,
    ]) }})">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('inventory.levels.title') }}</h1>
                <p class="page-sub">{{ __('inventory.levels.sub') }}</p>
            </div>
            <div class="dropdown" x-data="dropdown">
                <button type="button"
                        class="pos-btn pos-btn-sm pos-btn-ghost"
                        @click="toggle()"
                        :aria-expanded="open">
                    <x-icon name="download" class="w-4 h-4" />
                    {{ __('inventory.levels.actions.export') }}
                    <x-icon name="chevron" class="w-4 h-4" />
                </button>
                <div class="dropdown-panel"
                     x-show="open"
                     x-cloak
                     @click.outside="close()"
                     @keydown.escape.window="close()">
                    <a href="{{ route('admin.inventory.levels.export', ['format' => 'csv']) }}"
                       class="dropdown-item"
                       @click="close()">
                        <span class="dropdown-item-label">{{ __('inventory.levels.actions.export_csv') }}</span>
                    </a>
                    <a href="{{ route('admin.inventory.levels.export', ['format' => 'xlsx']) }}"
                       class="dropdown-item"
                       @click="close()">
                        <span class="dropdown-item-label">{{ __('inventory.levels.actions.export_xlsx') }}</span>
                    </a>
                </div>
            </div>
        </div>

        <x-admin.summary-cards :cards="$summaryCards" />

        <div class="card card-pad-0">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">
                    {{ __('inventory.levels.title') }} (<span x-text="matchedCount.toLocaleString()">{{ number_format($total) }}</span>)
                </div>
                <x-admin.dt-toolbar-actions :show-sort="false" />
            </div>
            {{-- Self-managed filter form: every change calls applyFilters(). --}}
            <form method="GET" action="{{ route('admin.inventory.levels.index') }}" class="inv-filter"
                  x-ref="filterForm" @submit.prevent="applyFilters()">
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('inventory.levels.columns.store') }}</span>
                    <select name="store_id" class="pos-input" x-data="enhancedSelect()" @change="applyFilters()">
                        <option value="">{{ __('inventory.levels.filter.store_all') }}</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" @selected($storeId === $store->id)>{{ $store->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('inventory.levels.filter.stock_status') }}</span>
                    <select name="stock_status" class="pos-input" x-data="enhancedSelect()" @change="applyFilters()">
                        <option value="all" @selected($stockStatus === 'all')>{{ __('inventory.levels.filter.stock_status_all') }}</option>
                        <option value="in" @selected($stockStatus === 'in')>{{ __('inventory.levels.filter.stock_status_in') }}</option>
                        <option value="out" @selected($stockStatus === 'out')>{{ __('inventory.levels.filter.stock_status_out') }}</option>
                    </select>
                </label>
                <label class="field inv-filter-search">
                    <span class="field-label">&nbsp;</span>
                    <span class="inv-search">
                        <span class="inv-search-icon"><x-icon name="search" class="w-4 h-4" /></span>
                        <input type="search" name="q" value="{{ $q }}" class="pos-input"
                               placeholder="{{ __('inventory.levels.filter.search') }}"
                               @input.debounce.400ms="applyFilters()">
                    </span>
                </label>
                <button type="button" class="inv-filter-reset" @click="resetFilters()"
                        title="{{ __('table.filter_reset') }}">
                    <x-icon name="x" class="w-3.5 h-3.5" />
                    <span>{{ __('table.filter_reset') }}</span>
                </button>
            </form>

            {{-- No stock levels match the current filters. --}}
            <div class="dt-empty" x-show="dtReady && isEmpty" x-cloak>
                <span class="dt-empty-icon"><x-icon name="box" class="w-5 h-5" /></span>
                <div class="dt-empty-title">{{ __('inventory.levels.empty') }}</div>
            </div>

            <div class="dt-scroll" x-show="!isEmpty">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('inventory.levels.columns.product') }}</th>
                            <th>{{ __('inventory.levels.columns.sku') }}</th>
                            <th>{{ __('inventory.levels.columns.store') }}</th>
                            <th class="num">{{ __('inventory.levels.columns.quantity') }}</th>
                            <th class="num">{{ __('inventory.levels.columns.reserved') }}</th>
                            <th class="num">{{ __('inventory.levels.columns.wac') }}</th>
                            <th class="num">{{ __('inventory.levels.columns.reorder') }}</th>
                            <th>{{ __('inventory.levels.columns.last_move') }}</th>
                        </tr>
                    </thead>
                    <tbody data-dt-rows="table">
                        @include('admin.inventory.levels._rows', ['levels' => $levels])
                    </tbody>
                </table>
            </div>
            <x-admin.dt-pager />
        </div>
    </div>
</x-admin-layout>
