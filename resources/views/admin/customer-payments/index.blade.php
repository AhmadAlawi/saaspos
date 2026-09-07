<x-admin-layout
    active="customer-payments"
    :title="__('customer_payments.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('customer_payments.crumb_parent')],
        ['label' => __('customer_payments.title')],
    ]">

    {{-- Server-paginated. Filters + paging fetch one page from
         `admin.customer-payments.rows`. Self-managed filter form (reuses the
         generic salesIndexPage factory). --}}
    <div class="page-wide" x-data="customerPaymentsPage({{ \Illuminate\Support\Js::from([
        'endpoint'   => route('admin.customer-payments.rows'),
        'perPage'    => $perPage,
        'total'      => $total,
        'page'       => 1,
        'totalPages' => $totalPages,
    ]) }})">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('customer_payments.title') }}</h1>
                <p class="page-sub">{{ __('customer_payments.sub') }}</p>
            </div>
            @can('create', App\Models\SalePayment::class)
                <a href="{{ route('admin.customer-payments.create') }}" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="plus" class="w-4 h-4" />
                    {{ __('customer_payments.new') }}
                </a>
            @endcan
        </div>

        <x-admin.summary-cards :cards="$summaryCards" />

        <div class="card card-pad-0">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">
                    {{ __('customer_payments.title') }} (<span x-text="matchedCount.toLocaleString()">{{ number_format($total) }}</span>)
                </div>
                <x-admin.dt-toolbar-actions :show-sort="false" />
            </div>
            {{-- Self-managed filter form: every change calls applyFilters(). --}}
            <form method="GET" action="{{ route('admin.customer-payments.index') }}" class="inv-filter"
                  x-ref="filterForm" @submit.prevent="applyFilters()">
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('customer_payments.columns.customer') }}</span>
                    <select name="customer_id" class="pos-input" x-data="enhancedSelect()" @change="applyFilters()">
                        <option value="">{{ __('customer_payments.filter.customer_all') }}</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected($filters['customerId'] === $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field inv-filter-date">
                    <span class="field-label">{{ __('customer_payments.filter.from') }}</span>
                    <input type="text" name="from" value="{{ $filters['from'] }}"
                           class="pos-input js-datepicker" data-fp-submit-on-change="1"
                           placeholder="{{ __('table.filter_date_from_placeholder') }}">
                </label>
                <label class="field inv-filter-date">
                    <span class="field-label">{{ __('customer_payments.filter.to') }}</span>
                    <input type="text" name="to" value="{{ $filters['to'] }}"
                           class="pos-input js-datepicker" data-fp-submit-on-change="1"
                           placeholder="{{ __('table.filter_date_to_placeholder') }}">
                </label>
                <button type="button" class="inv-filter-reset" @click="resetFilters()"
                        title="{{ __('table.filter_reset') }}">
                    <x-icon name="x" class="w-3.5 h-3.5" />
                    <span>{{ __('table.filter_reset') }}</span>
                </button>
            </form>

            {{-- No payments match the current filters. --}}
            <div class="dt-empty" x-show="dtReady && isEmpty" x-cloak>
                <span class="dt-empty-icon"><x-icon name="cash" class="w-5 h-5" /></span>
                <div class="dt-empty-title">{{ __('customer_payments.empty_state.title') }}</div>
                <div class="dt-empty-sub">{{ __('customer_payments.empty_state.sub') }}</div>
            </div>

            <div class="dt-scroll" x-show="!isEmpty">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('customer_payments.columns.date') }}</th>
                            <th>{{ __('customer_payments.columns.customer') }}</th>
                            <th>{{ __('customer_payments.columns.sale') }}</th>
                            <th>{{ __('customer_payments.columns.method') }}</th>
                            <th>{{ __('customer_payments.columns.reference') }}</th>
                            <th class="num">{{ __('customer_payments.columns.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody data-dt-rows="table">
                        @include('admin.customer-payments._rows', ['payments' => $payments])
                    </tbody>
                </table>
            </div>
            <x-admin.dt-pager />
        </div>
    </div>
</x-admin-layout>
