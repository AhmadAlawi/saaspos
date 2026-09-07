<x-admin-layout
    active="sales-by-product"
    :title="__('reports.sales_by_product.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('reports.nav.section'), 'href' => route('admin.reports.index')],
        ['label' => __('reports.sales_by_product.title')],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('reports.sales_by_product.title') }}</h1>
                <p class="page-sub">{{ __('reports.sales_by_product.sub') }}</p>
            </div>
            @if ($rows->isNotEmpty())
                <div class="flex items-center gap-2">
                    <div class="dropdown" x-data="dropdown">
                        <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost"
                                @click="toggle()" :aria-expanded="open">
                            <x-icon name="download" class="w-4 h-4" />
                            {{ __('reports.actions.export') }}
                            <x-icon name="chevron" class="w-4 h-4" />
                        </button>
                        <div class="dropdown-panel" x-show="open" x-cloak
                             @click.outside="close()" @keydown.escape.window="close()">
                            <a href="{{ route('admin.reports.sales-by-product.export', array_filter(['format' => 'csv', 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'store_id' => $storeId])) }}"
                               class="dropdown-item" @click="close()">
                                <span class="dropdown-item-label">{{ __('reports.actions.export_csv') }}</span>
                            </a>
                            <a href="{{ route('admin.reports.sales-by-product.export', array_filter(['format' => 'xlsx', 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'store_id' => $storeId])) }}"
                               class="dropdown-item" @click="close()">
                                <span class="dropdown-item-label">{{ __('reports.actions.export_xlsx') }}</span>
                            </a>
                            <a href="{{ route('admin.reports.sales-by-product.export', array_filter(['format' => 'pdf', 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'store_id' => $storeId])) }}"
                               class="dropdown-item" @click="close()">
                                <span class="dropdown-item-label">{{ __('reports.actions.export_pdf') }}</span>
                            </a>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- KPI cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
            <div class="card">
                <div class="card-body">
                    <div class="cust-kpi-label">{{ __('reports.sales_by_product.kpis.products') }}</div>
                    <div class="cust-kpi-value num tnum">{{ number_format($summary['products']) }}</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="cust-kpi-label">{{ __('reports.sales_by_product.kpis.qty_sold') }}</div>
                    <div class="cust-kpi-value num tnum">{{ rtrim(rtrim(number_format((float) $summary['qty_sold'], 4, '.', ','), '0'), '.') }}</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="cust-kpi-label">{{ __('reports.sales_by_product.kpis.revenue') }}</div>
                    <div class="cust-kpi-value num tnum">{{ format_money($summary['revenue']) }}</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="cust-kpi-label">{{ __('reports.sales_by_product.kpis.profit') }}</div>
                    <div class="cust-kpi-value num tnum @if ((float) $summary['profit'] > 0) text-emerald-600 dark:text-emerald-400 @elseif ((float) $summary['profit'] < 0) text-rose-600 dark:text-rose-400 @endif">
                        {{ format_money($summary['profit']) }}
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="cust-kpi-label">{{ __('reports.sales_by_product.kpis.margin') }}</div>
                    <div class="cust-kpi-value num tnum @if ((float) $summary['margin'] >= 35) text-emerald-600 dark:text-emerald-400 @elseif ((float) $summary['margin'] < 20) text-amber-600 dark:text-amber-400 @endif">
                        {{ number_format((float) $summary['margin'], 1) }}%
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-pad-0"
             x-data="dataTable({ rowsSelector: 'tbody > tr[data-dt-row]' })">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">
                    {{ __('reports.sales_by_product.list_title') }}
                    (<span x-text="rowCount">{{ $rows->count() }}</span>)
                </div>
                <x-admin.dt-toolbar-actions />
            </div>
            <x-admin.report-filter-bar
                route="admin.reports.sales-by-product.index"
                :search-placeholder="__('reports.sales_by_product.filter.search')"
                :period="$period"
                :from="$from"
                :to="$to"
                :store-id="$storeId"
                :stores="$stores" />

            @if ($rows->isEmpty())
                <div class="dt-empty">
                    <span class="dt-empty-icon"><x-icon name="box" class="w-5 h-5" /></span>
                    <div class="dt-empty-title">{{ __('reports.sales_by_product.empty.title') }}</div>
                    <div class="dt-empty-sub">{{ __('reports.sales_by_product.empty.sub') }}</div>
                </div>
            @else
                <div class="dt-scroll">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('reports.sales_by_product.columns.product') }}</th>
                            <th>{{ __('reports.sales_by_product.columns.sku') }}</th>
                            <th class="num">{{ __('reports.sales_by_product.columns.qty_sold') }}</th>
                            <th class="num">{{ __('reports.sales_by_product.columns.revenue') }}</th>
                            <th class="num">{{ __('reports.sales_by_product.columns.cost') }}</th>
                            <th class="num">{{ __('reports.sales_by_product.columns.profit') }}</th>
                            <th class="num">{{ __('reports.sales_by_product.columns.margin') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr data-dt-row>
                                <td class="font-medium">{{ $row['name'] }}</td>
                                <td class="mono text-xs fg-tertiary">{{ $row['sku'] }}</td>
                                <td class="num tnum">{{ $row['qty_sold'] }}</td>
                                <td class="num tnum">{{ format_money($row['revenue']) }}</td>
                                <td class="num tnum fg-tertiary">{{ format_money($row['cost']) }}</td>
                                <td class="num tnum @if ((float) $row['profit'] < 0) text-rose-600 dark:text-rose-400 @endif">
                                    {{ format_money($row['profit']) }}
                                </td>
                                <td class="num tnum">
                                    @php $m = (float) $row['margin_pct']; @endphp
                                    <span class="@if ($m >= 35) text-emerald-600 dark:text-emerald-400 @elseif ($m < 20) text-amber-600 dark:text-amber-400 @endif">
                                        {{ number_format($m, 1) }}%
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
                <x-admin.dt-pager />
            @endif
        </div>
    </div>
</x-admin-layout>
