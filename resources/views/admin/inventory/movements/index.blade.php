<x-admin-layout
    active="stock-movements"
    :title="__('inventory.movements.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('inventory.nav.section')],
        ['label' => __('inventory.movements.title')],
    ]">

    {{-- Server-paginated. x-data is page-wide so it wraps the summary cards it
         rewrites on every filter. Self-managed filter form → server mixin. --}}
    <div class="page-wide" x-data="stockMovementsPage({{ \Illuminate\Support\Js::from([
        'endpoint'   => route('admin.inventory.movements.rows'),
        'perPage'    => $perPage,
        'total'      => $total,
        'page'       => 1,
        'totalPages' => $totalPages,
    ]) }})">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('inventory.movements.title') }}</h1>
                <p class="page-sub">{{ __('inventory.movements.sub') }}</p>
            </div>
        </div>

        <x-admin.summary-cards :cards="$summaryCards" />

        <div class="card card-pad-0">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">
                    {{ __('inventory.movements.title') }} (<span x-text="matchedCount.toLocaleString()">{{ number_format($total) }}</span>)
                </div>
                <x-admin.dt-toolbar-actions :show-sort="false" />
            </div>
            {{-- Self-managed filter form: every change calls applyFilters(). --}}
            <form method="GET" action="{{ route('admin.inventory.movements.index') }}" class="inv-filter"
                  x-ref="filterForm" @submit.prevent="applyFilters()">
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('inventory.movements.columns.store') }}</span>
                    <select name="store_id" class="pos-input" x-data="enhancedSelect()" @change="applyFilters()">
                        <option value="">{{ __('inventory.movements.filter.store_all') }}</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" @selected($filters['storeId'] === $store->id)>{{ $store->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('inventory.movements.columns.type') }}</span>
                    <select name="type" class="pos-input" x-data="enhancedSelect()" @change="applyFilters()">
                        <option value="">{{ __('inventory.movements.filter.type_all') }}</option>
                        @foreach ($types as $type)
                            <option value="{{ $type }}" @selected($filters['type'] === $type)>{{ __("inventory.movements.types.$type") }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field inv-filter-date">
                    <span class="field-label">{{ __('inventory.movements.filter.date_from') }}</span>
                    <input type="text" name="from" value="{{ $filters['from'] }}"
                           class="pos-input js-datepicker"
                           placeholder="{{ __('table.filter_date_from_placeholder') }}"
                           data-fp-submit-on-change="1">
                </label>
                <label class="field inv-filter-date">
                    <span class="field-label">{{ __('inventory.movements.filter.date_to') }}</span>
                    <input type="text" name="to" value="{{ $filters['to'] }}"
                           class="pos-input js-datepicker"
                           placeholder="{{ __('table.filter_date_to_placeholder') }}"
                           data-fp-submit-on-change="1">
                </label>
                <label class="field inv-filter-search">
                    <span class="field-label">&nbsp;</span>
                    <span class="inv-search">
                        <span class="inv-search-icon"><x-icon name="search" class="w-4 h-4" /></span>
                        <input type="search" name="q" value="{{ $filters['q'] }}" class="pos-input"
                               placeholder="{{ __('inventory.movements.filter.search') }}"
                               @input.debounce.400ms="applyFilters()">
                    </span>
                </label>
                <button type="button" class="inv-filter-reset" @click="resetFilters()"
                        title="{{ __('table.filter_reset') }}">
                    <x-icon name="x" class="w-3.5 h-3.5" />
                    <span>{{ __('table.filter_reset') }}</span>
                </button>
            </form>

            {{-- No movements match the current filters. --}}
            <div class="dt-empty" x-show="dtReady && isEmpty" x-cloak>
                <span class="dt-empty-icon"><x-icon name="list" class="w-5 h-5" /></span>
                <div class="dt-empty-title">{{ __('inventory.movements.empty') }}</div>
            </div>

            <div class="dt-scroll" x-show="!isEmpty">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('inventory.movements.columns.when') }}</th>
                            <th>{{ __('inventory.movements.columns.product') }}</th>
                            <th>{{ __('inventory.movements.columns.store') }}</th>
                            <th>{{ __('inventory.movements.columns.type') }}</th>
                            <th class="num">{{ __('inventory.movements.columns.delta') }}</th>
                            <th class="num">{{ __('inventory.movements.columns.after') }}</th>
                            <th class="num">{{ __('inventory.movements.columns.unit_cost') }}</th>
                            <th>{{ __('inventory.movements.columns.reference') }}</th>
                            <th>{{ __('inventory.movements.columns.by') }}</th>
                        </tr>
                    </thead>
                    <tbody data-dt-rows="table">
                        @include('admin.inventory.movements._rows', ['movements' => $movements])
                    </tbody>
                </table>
            </div>
            <x-admin.dt-pager />
        </div>
    </div>
</x-admin-layout>
