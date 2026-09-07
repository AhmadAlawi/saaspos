<x-admin-layout
    active="supplier-payments"
    :title="__('supplier_payments.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('supplier_payments.crumb_parent')],
        ['label' => __('supplier_payments.title')],
    ]">

    {{-- Server-paginated. Filters + paging fetch one page from
         `admin.supplier-payments.rows`. Self-managed filter form (reuses the
         generic salesIndexPage factory). --}}
    <div class="page-wide" x-data="supplierPaymentsPage({{ \Illuminate\Support\Js::from([
        'endpoint'   => route('admin.supplier-payments.rows'),
        'perPage'    => $perPage,
        'total'      => $total,
        'page'       => 1,
        'totalPages' => $totalPages,
    ]) }})">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('supplier_payments.title') }}</h1>
                <p class="page-sub">{{ __('supplier_payments.sub') }}</p>
            </div>
            @can('create', App\Models\PurchasePayment::class)
                <a href="{{ route('admin.supplier-payments.create') }}" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="plus" class="w-4 h-4" />
                    {{ __('supplier_payments.new') }}
                </a>
            @endcan
        </div>

        <x-admin.summary-cards :cards="$summaryCards" />

        <div class="card card-pad-0">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">
                    {{ __('supplier_payments.title') }} (<span x-text="matchedCount.toLocaleString()">{{ number_format($total) }}</span>)
                </div>
                <x-admin.dt-toolbar-actions :show-sort="false" />
            </div>
            {{-- Self-managed filter form: every change calls applyFilters(). --}}
            <form method="GET" action="{{ route('admin.supplier-payments.index') }}" class="inv-filter"
                  x-ref="filterForm" @submit.prevent="applyFilters()">
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('supplier_payments.columns.supplier') }}</span>
                    <select name="supplier_id" class="pos-input" x-data="enhancedSelect()" @change="applyFilters()">
                        <option value="">{{ __('supplier_payments.filter.supplier_all') }}</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected($filters['supplierId'] === $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('supplier_payments.columns.method') }}</span>
                    <select name="payment_method_id" class="pos-input" x-data="enhancedSelect()" @change="applyFilters()">
                        <option value="">{{ __('supplier_payments.filter.method_all') }}</option>
                        @foreach ($methods as $m)
                            <option value="{{ $m->id }}" @selected($filters['paymentMethodId'] === $m->id)>{{ $m->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field inv-filter-date">
                    <span class="field-label">{{ __('supplier_payments.filter.from') }}</span>
                    <input type="text" name="from" value="{{ $filters['from'] }}"
                           class="pos-input js-datepicker" data-fp-submit-on-change="1"
                           placeholder="{{ __('table.filter_date_from_placeholder') }}">
                </label>
                <label class="field inv-filter-date">
                    <span class="field-label">{{ __('supplier_payments.filter.to') }}</span>
                    <input type="text" name="to" value="{{ $filters['to'] }}"
                           class="pos-input js-datepicker" data-fp-submit-on-change="1"
                           placeholder="{{ __('table.filter_date_to_placeholder') }}">
                </label>
                <label class="field inv-filter-search">
                    <span class="field-label">&nbsp;</span>
                    <span class="inv-search">
                        <span class="inv-search-icon"><x-icon name="search" class="w-4 h-4" /></span>
                        <input type="search" name="q" value="{{ $filters['q'] }}" class="pos-input"
                               placeholder="{{ __('supplier_payments.filter.search') }}"
                               @input.debounce.400ms="applyFilters()">
                    </span>
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
                <div class="dt-empty-title">{{ __('supplier_payments.empty_state.title') }}</div>
                <div class="dt-empty-sub">{{ __('supplier_payments.empty_state.sub') }}</div>
            </div>

            <div class="dt-scroll" x-show="!isEmpty">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('supplier_payments.columns.date') }}</th>
                            <th>{{ __('supplier_payments.columns.supplier') }}</th>
                            <th>{{ __('supplier_payments.columns.purchase') }}</th>
                            <th>{{ __('supplier_payments.columns.method') }}</th>
                            <th>{{ __('supplier_payments.columns.reference') }}</th>
                            <th class="num">{{ __('supplier_payments.columns.amount') }}</th>
                            <th class="dt-actions-col"><span class="sr-only">{{ __('table.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody data-dt-rows="table">
                        @include('admin.supplier-payments._rows', ['payments' => $payments])
                    </tbody>
                </table>
            </div>
            <x-admin.dt-pager />
        </div>
    </div>
</x-admin-layout>
