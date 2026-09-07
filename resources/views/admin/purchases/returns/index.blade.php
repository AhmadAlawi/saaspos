<x-admin-layout
    active="purchase-returns"
    :title="__('purchases.returns.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('purchases.crumb_parent')],
        ['label' => __('purchases.returns.title')],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('purchases.returns.title') }}</h1>
                <p class="page-sub">{{ __('purchases.returns.sub') }}</p>
            </div>
        </div>

        {{-- Server-paginated. Self-managed filter form (reuses the generic
             salesIndexPage factory). No summary cards, so x-data stays on the card. --}}
        <div class="card card-pad-0" x-data="purchaseReturnsPage({{ \Illuminate\Support\Js::from([
            'endpoint'   => route('admin.purchase-returns.rows'),
            'perPage'    => $perPage,
            'total'      => $total,
            'page'       => 1,
            'totalPages' => $totalPages,
        ]) }})">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">
                    {{ __('purchases.returns.title') }} (<span x-text="matchedCount.toLocaleString()">{{ number_format($total) }}</span>)
                </div>
                <x-admin.dt-toolbar-actions :show-sort="false" />
            </div>

            {{-- Self-managed filter form: every change calls applyFilters(). --}}
            <form method="GET" action="{{ route('admin.purchase-returns.index') }}" class="inv-filter"
                  x-ref="filterForm" @submit.prevent="applyFilters()">
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('purchases.returns.columns.status') }}</span>
                    <select name="status" class="pos-input" x-data="enhancedSelect()" @change="applyFilters()">
                        <option value="all" @selected($filters['status'] === 'all')>{{ __('purchases.returns.filter.status_all') }}</option>
                        @foreach (['posted', 'draft'] as $s)
                            <option value="{{ $s }}" @selected($filters['status'] === $s)>{{ __('purchases.returns.status.'.$s) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field inv-filter-date">
                    <span class="field-label">{{ __('purchases.returns.filter.from') }}</span>
                    <input type="text" name="from" value="{{ $filters['from'] }}"
                           class="pos-input js-datepicker" data-fp-submit-on-change="1"
                           placeholder="{{ __('table.filter_date_from_placeholder') }}">
                </label>
                <label class="field inv-filter-date">
                    <span class="field-label">{{ __('purchases.returns.filter.to') }}</span>
                    <input type="text" name="to" value="{{ $filters['to'] }}"
                           class="pos-input js-datepicker" data-fp-submit-on-change="1"
                           placeholder="{{ __('table.filter_date_to_placeholder') }}">
                </label>
                <label class="field inv-filter-search">
                    <span class="field-label">&nbsp;</span>
                    <span class="inv-search">
                        <span class="inv-search-icon"><x-icon name="search" class="w-4 h-4" /></span>
                        <input type="search" name="q" value="{{ $filters['q'] }}" class="pos-input"
                               placeholder="{{ __('purchases.returns.filter.search') }}"
                               @input.debounce.400ms="applyFilters()">
                    </span>
                </label>
                <button type="button" class="inv-filter-reset" @click="resetFilters()"
                        title="{{ __('table.filter_reset') }}">
                    <x-icon name="x" class="w-3.5 h-3.5" />
                    <span>{{ __('table.filter_reset') }}</span>
                </button>
            </form>

            {{-- No returns match the current filters. --}}
            <div class="dt-empty" x-show="dtReady && isEmpty" x-cloak>
                <span class="dt-empty-icon"><x-icon name="search" class="w-5 h-5" /></span>
                <div class="dt-empty-title">{{ __('purchases.returns.empty_state.title') }}</div>
                <div class="dt-empty-sub">{{ __('purchases.returns.empty_state.sub') }}</div>
            </div>

            <div class="dt-scroll" x-show="!isEmpty">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('purchases.returns.columns.number') }}</th>
                            <th>{{ __('purchases.returns.columns.purchase') }}</th>
                            <th>{{ __('purchases.returns.columns.supplier') }}</th>
                            <th>{{ __('purchases.returns.columns.date') }}</th>
                            <th class="num">{{ __('purchases.returns.columns.total') }}</th>
                            <th>{{ __('purchases.returns.columns.status') }}</th>
                            <th class="dt-actions-col"><span class="sr-only">{{ __('table.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody data-dt-rows="table">
                        @include('admin.purchases.returns._rows', ['returns' => $returns])
                    </tbody>
                </table>
            </div>
            <x-admin.dt-pager />
        </div>
    </div>
</x-admin-layout>
