<x-admin-layout
    active="purchases"
    :title="__('purchases.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('purchases.crumb_parent')],
        ['label' => __('purchases.title')],
    ]">

    {{-- Server-paginated. Only the first page renders inline; filters + paging
         fetch one page from `admin.purchases.rows`. Self-managed filter form
         (reuses the generic salesIndexPage factory). --}}
    <div class="page-wide" x-data="purchasesIndexPage({{ \Illuminate\Support\Js::from([
        'endpoint'   => route('admin.purchases.rows'),
        'perPage'    => $perPage,
        'total'      => $total,
        'page'       => 1,
        'totalPages' => $totalPages,
    ]) }})">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('purchases.title') }}</h1>
                <p class="page-sub">{{ __('purchases.sub') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <div class="dropdown" x-data="dropdown">
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            @click="toggle()"
                            :aria-expanded="open">
                        <x-icon name="download" class="w-4 h-4" />
                        {{ __('purchases.actions.export') }}
                        <x-icon name="chevron" class="w-4 h-4" />
                    </button>
                    <div class="dropdown-panel"
                         x-show="open"
                         x-cloak
                         @click.outside="close()"
                         @keydown.escape.window="close()">
                        <a href="{{ route('admin.purchases.export', ['format' => 'csv']) }}"
                           class="dropdown-item"
                           @click="close()">
                            <span class="dropdown-item-label">{{ __('purchases.actions.export_csv') }}</span>
                        </a>
                        <a href="{{ route('admin.purchases.export', ['format' => 'xlsx']) }}"
                           class="dropdown-item"
                           @click="close()">
                            <span class="dropdown-item-label">{{ __('purchases.actions.export_xlsx') }}</span>
                        </a>
                    </div>
                </div>

                @can('create', App\Models\Purchase::class)
                    <a href="{{ route('admin.purchases.create') }}" class="pos-btn pos-btn-sm pos-btn-primary">
                        <x-icon name="plus" class="w-4 h-4" />
                        {{ __('purchases.new') }}
                    </a>
                @endcan
            </div>
        </div>

        <x-admin.summary-cards :cards="$summaryCards" />

        <div class="card card-pad-0">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">
                    {{ __('purchases.title') }} (<span x-text="matchedCount.toLocaleString()">{{ number_format($total) }}</span>)
                </div>
                <x-admin.dt-toolbar-actions :show-sort="false" />
            </div>
            {{-- Self-managed filter form: every change calls applyFilters(),
                 which fetches one page of rows via the server mixin. --}}
            <form method="GET" action="{{ route('admin.purchases.index') }}" class="inv-filter"
                  x-ref="filterForm" @submit.prevent="applyFilters()">
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('purchases.columns.status') }}</span>
                    <select name="status" class="pos-input" x-data="enhancedSelect()" @change="applyFilters()">
                        <option value="all"             @selected($filters['status'] === 'all')>{{ __('purchases.filter.status_all') }}</option>
                        <option value="draft"           @selected($filters['status'] === 'draft')>{{ __('purchases.status.draft') }}</option>
                        <option value="submitted"       @selected($filters['status'] === 'submitted')>{{ __('purchases.status.submitted') }}</option>
                        <option value="received"        @selected($filters['status'] === 'received')>{{ __('purchases.status.received') }}</option>
                        <option value="partially_paid"  @selected($filters['status'] === 'partially_paid')>{{ __('purchases.status.partially_paid') }}</option>
                        <option value="paid"            @selected($filters['status'] === 'paid')>{{ __('purchases.status.paid') }}</option>
                        <option value="cancelled"       @selected($filters['status'] === 'cancelled')>{{ __('purchases.status.cancelled') }}</option>
                    </select>
                </label>
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('purchases.columns.supplier') }}</span>
                    <select name="supplier_id" class="pos-input" x-data="enhancedSelect()" @change="applyFilters()">
                        <option value="">{{ __('purchases.filter.supplier_all') }}</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected($filters['supplierId'] === $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field inv-filter-date">
                    <span class="field-label">{{ __('purchases.filter.from') }}</span>
                    <input type="text" name="from" value="{{ $filters['from'] }}"
                           class="pos-input js-datepicker" data-fp-submit-on-change="1"
                           placeholder="{{ __('table.filter_date_from_placeholder') }}">
                </label>
                <label class="field inv-filter-date">
                    <span class="field-label">{{ __('purchases.filter.to') }}</span>
                    <input type="text" name="to" value="{{ $filters['to'] }}"
                           class="pos-input js-datepicker" data-fp-submit-on-change="1"
                           placeholder="{{ __('table.filter_date_to_placeholder') }}">
                </label>
                <label class="field inv-filter-search">
                    <span class="field-label">&nbsp;</span>
                    <span class="inv-search">
                        <span class="inv-search-icon"><x-icon name="search" class="w-4 h-4" /></span>
                        <input type="search" name="q" value="{{ $filters['q'] }}" class="pos-input"
                               placeholder="{{ __('purchases.filter.search') }}"
                               @input.debounce.400ms="applyFilters()">
                    </span>
                </label>
                <button type="button" class="inv-filter-reset" @click="resetFilters()"
                        title="{{ __('table.filter_reset') }}">
                    <x-icon name="x" class="w-3.5 h-3.5" />
                    <span>{{ __('table.filter_reset') }}</span>
                </button>
            </form>

            {{-- No purchases match the current filters. --}}
            <div class="dt-empty" x-show="dtReady && isEmpty" x-cloak>
                <span class="dt-empty-icon"><x-icon name="truck" class="w-5 h-5" /></span>
                <div class="dt-empty-title">{{ __('purchases.empty_state.title') }}</div>
                <div class="dt-empty-sub">{{ __('purchases.empty_state.sub') }}</div>
            </div>

            <div class="dt-scroll" x-show="!isEmpty">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('purchases.columns.number') }}</th>
                            <th>{{ __('purchases.columns.supplier') }}</th>
                            <th>{{ __('purchases.columns.store') }}</th>
                            <th>{{ __('purchases.columns.date') }}</th>
                            <th>{{ __('purchases.columns.due_date') }}</th>
                            <th class="num">{{ __('purchases.columns.grand_total') }}</th>
                            <th class="num">{{ __('purchases.columns.balance') }}</th>
                            <th>{{ __('purchases.columns.status') }}</th>
                            <th class="dt-actions-col"><span class="sr-only">{{ __('purchases.columns.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody data-dt-rows="table">
                        @include('admin.purchases._rows', ['purchases' => $purchases])
                    </tbody>
                </table>
            </div>
            <x-admin.dt-pager />
        </div>
    </div>
</x-admin-layout>
