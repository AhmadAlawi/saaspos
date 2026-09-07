<x-admin-layout
    active="stock-adjustments"
    :title="__('inventory.adjustments.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('inventory.adjustments.crumb_parent')],
        ['label' => __('inventory.adjustments.title')],
    ]">

    {{-- Server-paginated. x-data is page-wide so it wraps the summary cards it
         rewrites on every filter. Self-managed filter form → server mixin. --}}
    <div class="page-wide" x-data="stockAdjustmentsPage({{ \Illuminate\Support\Js::from([
        'endpoint'   => route('admin.inventory.adjustments.rows'),
        'perPage'    => $perPage,
        'total'      => $total,
        'page'       => 1,
        'totalPages' => $totalPages,
    ]) }})">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('inventory.adjustments.title') }}</h1>
                <p class="page-sub">{{ __('inventory.adjustments.sub') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <div class="dropdown" x-data="dropdown">
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            @click="toggle()"
                            :aria-expanded="open">
                        <x-icon name="download" class="w-4 h-4" />
                        {{ __('inventory.adjustments.actions.export') }}
                        <x-icon name="chevron" class="w-4 h-4" />
                    </button>
                    <div class="dropdown-panel"
                         x-show="open"
                         x-cloak
                         @click.outside="close()"
                         @keydown.escape.window="close()">
                        <a href="{{ route('admin.inventory.adjustments.export', ['format' => 'csv']) }}"
                           class="dropdown-item"
                           @click="close()">
                            <span class="dropdown-item-label">{{ __('inventory.adjustments.actions.export_csv') }}</span>
                        </a>
                        <a href="{{ route('admin.inventory.adjustments.export', ['format' => 'xlsx']) }}"
                           class="dropdown-item"
                           @click="close()">
                            <span class="dropdown-item-label">{{ __('inventory.adjustments.actions.export_xlsx') }}</span>
                        </a>
                    </div>
                </div>

                @can('create', App\Models\StockAdjustment::class)
                    <a href="{{ route('admin.inventory.adjustments.create') }}" class="pos-btn pos-btn-sm pos-btn-primary">
                        <x-icon name="plus" class="w-4 h-4" />
                        {{ __('inventory.adjustments.new') }}
                    </a>
                @endcan
            </div>
        </div>

        <x-admin.summary-cards :cards="$summaryCards" />

        <div class="card card-pad-0">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">
                    {{ __('inventory.adjustments.title') }} (<span x-text="matchedCount.toLocaleString()">{{ number_format($total) }}</span>)
                </div>
                <x-admin.dt-toolbar-actions :show-sort="false" />
            </div>
            {{-- Self-managed filter form: every change calls applyFilters(). --}}
            <form method="GET" action="{{ route('admin.inventory.adjustments.index') }}" class="inv-filter"
                  x-ref="filterForm" @submit.prevent="applyFilters()">
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('inventory.adjustments.columns.store') }}</span>
                    <select name="store_id" class="pos-input" x-data="enhancedSelect()" @change="applyFilters()">
                        <option value="">{{ __('inventory.adjustments.filter.store_all') }}</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" @selected($filters['storeId'] === $store->id)>{{ $store->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('inventory.adjustments.columns.status') }}</span>
                    <select name="status" class="pos-input" x-data="enhancedSelect()" @change="applyFilters()">
                        <option value="">{{ __('inventory.adjustments.filter.status_all') }}</option>
                        <option value="draft"  @selected($filters['status'] === 'draft')>{{ __('inventory.adjustments.status.draft') }}</option>
                        <option value="posted" @selected($filters['status'] === 'posted')>{{ __('inventory.adjustments.status.posted') }}</option>
                    </select>
                </label>
                <label class="field inv-filter-date">
                    <span class="field-label">{{ __('inventory.adjustments.filter.date_from') }}</span>
                    <input type="text" name="from" value="{{ $filters['from'] }}"
                           class="pos-input js-datepicker"
                           placeholder="{{ __('table.filter_date_from_placeholder') }}"
                           data-fp-submit-on-change="1">
                </label>
                <label class="field inv-filter-date">
                    <span class="field-label">{{ __('inventory.adjustments.filter.date_to') }}</span>
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
                               placeholder="{{ __('inventory.adjustments.filter.search') }}"
                               @input.debounce.400ms="applyFilters()">
                    </span>
                </label>
                <button type="button" class="inv-filter-reset" @click="resetFilters()"
                        title="{{ __('table.filter_reset') }}">
                    <x-icon name="x" class="w-3.5 h-3.5" />
                    <span>{{ __('table.filter_reset') }}</span>
                </button>
            </form>

            {{-- No adjustments match the current filters. --}}
            <div class="dt-empty" x-show="dtReady && isEmpty" x-cloak>
                <span class="dt-empty-icon"><x-icon name="edit" class="w-5 h-5" /></span>
                <div class="dt-empty-title">{{ __('inventory.adjustments.empty_state.title') }}</div>
                <div class="dt-empty-sub">{{ __('inventory.adjustments.empty_state.sub') }}</div>
            </div>

            <div class="dt-scroll" x-show="!isEmpty">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('inventory.adjustments.columns.number') }}</th>
                            <th>{{ __('inventory.adjustments.columns.date') }}</th>
                            <th>{{ __('inventory.adjustments.columns.store') }}</th>
                            <th>{{ __('inventory.adjustments.columns.reason') }}</th>
                            <th class="num">{{ __('inventory.adjustments.columns.items') }}</th>
                            <th>{{ __('inventory.adjustments.columns.status') }}</th>
                            <th>{{ __('inventory.adjustments.columns.by') }}</th>
                            <th class="dt-actions-col"><span class="sr-only">{{ __('inventory.adjustments.columns.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody data-dt-rows="table">
                        @include('admin.inventory.adjustments._rows', ['adjustments' => $adjustments])
                    </tbody>
                </table>
            </div>
            <x-admin.dt-pager />
        </div>
    </div>
</x-admin-layout>
