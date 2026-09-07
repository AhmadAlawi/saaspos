<x-admin-layout
    active="sales"
    :title="__('sales.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('sales.title')],
    ]">

    {{-- Server-paginated. Only the first page renders inline; the filters and
         paging fetch one page from `admin.sales.rows`. The filter form is
         self-managed (Alpine drives it), so inv-filter-ajax leaves it alone. --}}
    <div class="page-wide" x-data="salesIndexPage({{ \Illuminate\Support\Js::from([
        'endpoint'   => route('admin.sales.rows'),
        'perPage'    => $perPage,
        'total'      => $total,
        'page'       => 1,
        'totalPages' => $totalPages,
    ]) }})">

        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('sales.title') }}</h1>
                <p class="page-sub">{{ __('sales.sub') }}</p>
            </div>
            @can('create', App\Models\Sale::class)
                <a href="{{ route('cashier.index') }}" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="pos" class="w-4 h-4" />
                    {{ __('sales.actions.open_cashier') }}
                </a>
            @endcan
        </div>

        <x-admin.summary-cards :cards="$summaryCards" />

        <div class="card card-pad-0">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">
                    {{ __('sales.title') }} (<span x-text="matchedCount.toLocaleString()">{{ number_format($total) }}</span>)
                </div>
                <x-admin.dt-toolbar-actions :show-sort="false" />
            </div>
            {{-- Self-managed filter form: every change calls applyFilters(),
                 which fetches one page of rows via the server mixin. --}}
            <form method="GET" action="{{ route('admin.sales.index') }}" class="inv-filter"
                  x-ref="filterForm" @submit.prevent="applyFilters()">
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('sales.columns.status') }}</span>
                    <select name="status" class="pos-input" x-data="enhancedSelect()" @change="applyFilters()">
                        <option value="all" @selected($filters['status'] === 'all')>{{ __('sales.filter.status_all') }}</option>
                        @foreach (['completed', 'held', 'placed', 'voided', 'partially_refunded', 'refunded'] as $s)
                            <option value="{{ $s }}" @selected($filters['status'] === $s)>{{ __('sales.statuses.'.$s) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('sales.columns.method') }}</span>
                    <select name="payment_method_id" class="pos-input" x-data="enhancedSelect()" @change="applyFilters()">
                        <option value="">{{ __('sales.filter.method_all') }}</option>
                        @foreach ($methods as $m)
                            <option value="{{ $m->id }}" @selected($filters['paymentMethodId'] === $m->id)>{{ $m->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('sales.filter.customer') }}</span>
                    {{-- Remote customer picker (server-side search) — never
                         preloads the full customer list. The current value's
                         label is seeded so it shows correctly on load. --}}
                    <select x-data="remoteSelect({
                                url: @js(route('admin.customers.search')),
                                value: @js($filters['customerId']),
                                label: @js($filters['customerLabel']),
                                placeholder: @js(__('sales.filter.customer_all')),
                            })"
                            name="customer_id"
                            class="pos-input"
                            @change="applyFilters()"></select>
                </label>
                <label class="field inv-filter-date">
                    <span class="field-label">{{ __('sales.filter.from') }}</span>
                    <input type="text" name="from" value="{{ $filters['from'] }}"
                           class="pos-input js-datepicker" data-fp-submit-on-change="1"
                           placeholder="{{ __('table.filter_date_from_placeholder') }}">
                </label>
                <label class="field inv-filter-date">
                    <span class="field-label">{{ __('sales.filter.to') }}</span>
                    <input type="text" name="to" value="{{ $filters['to'] }}"
                           class="pos-input js-datepicker" data-fp-submit-on-change="1"
                           placeholder="{{ __('table.filter_date_to_placeholder') }}">
                </label>
                <label class="field inv-filter-search">
                    <span class="field-label">&nbsp;</span>
                    <span class="inv-search">
                        <span class="inv-search-icon"><x-icon name="search" class="w-4 h-4" /></span>
                        <input type="search" name="q" value="{{ $filters['q'] }}" class="pos-input"
                               placeholder="{{ __('sales.filter.search') }}"
                               @input.debounce.400ms="applyFilters()">
                    </span>
                </label>
                <button type="button" class="inv-filter-reset" @click="resetFilters()"
                        title="{{ __('table.filter_reset') }}">
                    <x-icon name="x" class="w-3.5 h-3.5" />
                    <span>{{ __('table.filter_reset') }}</span>
                </button>
            </form>

            {{-- No sales match the current filters. --}}
            <div class="dt-empty" x-show="dtReady && isEmpty" x-cloak>
                <span class="dt-empty-icon"><x-icon name="cart" class="w-5 h-5" /></span>
                <div class="dt-empty-title">{{ __('sales.empty.title') }}</div>
                <div class="dt-empty-sub">{{ __('sales.empty.sub') }}</div>
            </div>

            <div class="dt-scroll" x-show="!isEmpty">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('sales.columns.number') }}</th>
                            <th>{{ __('sales.columns.date') }}</th>
                            <th>{{ __('sales.columns.store') }}</th>
                            <th>{{ __('sales.columns.customer') }}</th>
                            <th>{{ __('sales.columns.cashier') }}</th>
                            <th>{{ __('sales.columns.method') }}</th>
                            <th>{{ __('sales.columns.status') }}</th>
                            <th class="num">{{ __('sales.columns.grand_total') }}</th>
                            <th class="dt-actions-col"><span class="sr-only">{{ __('sales.columns.actions') ?? 'Actions' }}</span></th>
                        </tr>
                    </thead>
                    <tbody data-dt-rows="table">
                        @include('admin.sales._rows', ['sales' => $sales])
                    </tbody>
                </table>
            </div>
            <x-admin.dt-pager />
        </div>
    </div>
</x-admin-layout>
