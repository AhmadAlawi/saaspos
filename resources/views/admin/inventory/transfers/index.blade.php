<x-admin-layout
    active="stock-transfers"
    :title="__('inventory.transfers.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('inventory.transfers.crumb_parent')],
        ['label' => __('inventory.transfers.title')],
    ]">

    {{-- Server-paginated. x-data is page-wide so it wraps the summary cards it
         rewrites on every filter. Self-managed filter form → server mixin. --}}
    <div class="page-wide" x-data="stockTransfersPage({{ \Illuminate\Support\Js::from([
        'endpoint'   => route('admin.inventory.transfers.rows'),
        'perPage'    => $perPage,
        'total'      => $total,
        'page'       => 1,
        'totalPages' => $totalPages,
    ]) }})">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('inventory.transfers.title') }}</h1>
                <p class="page-sub">{{ __('inventory.transfers.sub') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <div class="dropdown" x-data="dropdown">
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            @click="toggle()"
                            :aria-expanded="open">
                        <x-icon name="download" class="w-4 h-4" />
                        {{ __('inventory.transfers.actions.export') }}
                        <x-icon name="chevron" class="w-4 h-4" />
                    </button>
                    <div class="dropdown-panel"
                         x-show="open"
                         x-cloak
                         @click.outside="close()"
                         @keydown.escape.window="close()">
                        <a href="{{ route('admin.inventory.transfers.export', ['format' => 'csv']) }}"
                           class="dropdown-item"
                           @click="close()">
                            <span class="dropdown-item-label">{{ __('inventory.transfers.actions.export_csv') }}</span>
                        </a>
                        <a href="{{ route('admin.inventory.transfers.export', ['format' => 'xlsx']) }}"
                           class="dropdown-item"
                           @click="close()">
                            <span class="dropdown-item-label">{{ __('inventory.transfers.actions.export_xlsx') }}</span>
                        </a>
                    </div>
                </div>

                @can('create', App\Models\StockTransfer::class)
                    <a href="{{ route('admin.inventory.transfers.create') }}" class="pos-btn pos-btn-sm pos-btn-primary">
                        <x-icon name="plus" class="w-4 h-4" />
                        {{ __('inventory.transfers.new') }}
                    </a>
                @endcan
            </div>
        </div>

        <x-admin.summary-cards :cards="$summaryCards" />

        <div class="card card-pad-0">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">
                    {{ __('inventory.transfers.title') }} (<span x-text="matchedCount.toLocaleString()">{{ number_format($total) }}</span>)
                </div>
                <x-admin.dt-toolbar-actions :show-sort="false" />
            </div>

            {{-- Self-managed filter form: every change calls applyFilters(). --}}
            <form method="GET" action="{{ route('admin.inventory.transfers.index') }}" class="inv-filter"
                  x-ref="filterForm" @submit.prevent="applyFilters()">
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('inventory.transfers.columns.from_store') }}</span>
                    <select name="from_store_id" class="pos-input" x-data="enhancedSelect()" @change="applyFilters()">
                        <option value="">{{ __('inventory.transfers.filter.from_store_all') }}</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" @selected($filters['fromStoreId'] === $store->id)>{{ $store->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('inventory.transfers.columns.to_store') }}</span>
                    <select name="to_store_id" class="pos-input" x-data="enhancedSelect()" @change="applyFilters()">
                        <option value="">{{ __('inventory.transfers.filter.to_store_all') }}</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" @selected($filters['toStoreId'] === $store->id)>{{ $store->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('inventory.transfers.columns.status') }}</span>
                    <select name="status" class="pos-input" x-data="enhancedSelect()" @change="applyFilters()">
                        <option value="">{{ __('inventory.transfers.filter.status_all') }}</option>
                        <option value="draft"      @selected($filters['status'] === 'draft')>{{ __('inventory.transfers.status.draft') }}</option>
                        <option value="in_transit" @selected($filters['status'] === 'in_transit')>{{ __('inventory.transfers.status.in_transit') }}</option>
                        <option value="received"   @selected($filters['status'] === 'received')>{{ __('inventory.transfers.status.received') }}</option>
                        <option value="cancelled"  @selected($filters['status'] === 'cancelled')>{{ __('inventory.transfers.status.cancelled') }}</option>
                    </select>
                </label>
                <label class="field inv-filter-date">
                    <span class="field-label">{{ __('inventory.transfers.filter.date_from') }}</span>
                    <input type="text" name="from" value="{{ $filters['from'] }}"
                           class="pos-input js-datepicker"
                           placeholder="{{ __('table.filter_date_from_placeholder') }}"
                           data-fp-submit-on-change="1">
                </label>
                <label class="field inv-filter-date">
                    <span class="field-label">{{ __('inventory.transfers.filter.date_to') }}</span>
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
                               placeholder="{{ __('inventory.transfers.filter.search') }}"
                               @input.debounce.400ms="applyFilters()">
                    </span>
                </label>
                <button type="button" class="inv-filter-reset" @click="resetFilters()"
                        title="{{ __('table.filter_reset') }}">
                    <x-icon name="x" class="w-3.5 h-3.5" />
                    <span>{{ __('table.filter_reset') }}</span>
                </button>
            </form>

            {{-- No transfers match the current filters. --}}
            <div class="dt-empty" x-show="dtReady && isEmpty" x-cloak>
                <span class="dt-empty-icon"><x-icon name="truck" class="w-5 h-5" /></span>
                <div class="dt-empty-title">{{ __('inventory.transfers.empty_state.title') }}</div>
                <div class="dt-empty-sub">{{ __('inventory.transfers.empty_state.sub') }}</div>
            </div>

            <div class="dt-scroll" x-show="!isEmpty">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('inventory.transfers.columns.number') }}</th>
                            <th>{{ __('inventory.transfers.columns.date') }}</th>
                            <th>{{ __('inventory.transfers.columns.from_store') }}</th>
                            <th>{{ __('inventory.transfers.columns.to_store') }}</th>
                            <th class="num">{{ __('inventory.transfers.columns.items') }}</th>
                            <th>{{ __('inventory.transfers.columns.status') }}</th>
                            <th>{{ __('inventory.transfers.columns.by') }}</th>
                            <th class="dt-actions-col"><span class="sr-only">{{ __('inventory.transfers.columns.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody data-dt-rows="table">
                        @include('admin.inventory.transfers._rows', ['transfers' => $transfers])
                    </tbody>
                </table>
            </div>
            <x-admin.dt-pager />
        </div>
    </div>
</x-admin-layout>
