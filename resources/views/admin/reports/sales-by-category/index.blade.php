<x-admin-layout
    active="sales-by-category"
    :title="__('reports.sales_by_category.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('reports.nav.section'), 'href' => route('admin.reports.index')],
        ['label' => __('reports.sales_by_category.title')],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('reports.sales_by_category.title') }}</h1>
                <p class="page-sub">{{ __('reports.sales_by_category.sub') }}</p>
            </div>
            @if ($rows->isNotEmpty())
                <div class="dropdown" x-data="dropdown">
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="toggle()" :aria-expanded="open">
                        <x-icon name="download" class="w-4 h-4" />
                        {{ __('reports.actions.export') }}
                        <x-icon name="chevron" class="w-4 h-4" />
                    </button>
                    <div class="dropdown-panel" x-show="open" x-cloak @click.outside="close()" @keydown.escape.window="close()">
                        <a href="{{ route('admin.reports.sales-by-category.export', array_filter(['format' => 'csv', 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'store_id' => $storeId])) }}" class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('reports.actions.export_csv') }}</span>
                        </a>
                        <a href="{{ route('admin.reports.sales-by-category.export', array_filter(['format' => 'xlsx', 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'store_id' => $storeId])) }}" class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('reports.actions.export_xlsx') }}</span>
                        </a>
                        <a href="{{ route('admin.reports.sales-by-category.export', array_filter(['format' => 'pdf', 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'store_id' => $storeId])) }}" class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('reports.actions.export_pdf') }}</span>
                        </a>
                    </div>
                </div>
            @endif
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <div class="card"><div class="card-body">
                <div class="cust-kpi-label">{{ __('reports.sales_by_category.kpis.categories') }}</div>
                <div class="cust-kpi-value num tnum">{{ number_format($summary['categories']) }}</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="cust-kpi-label">{{ __('reports.sales_by_category.kpis.revenue') }}</div>
                <div class="cust-kpi-value num tnum">{{ format_money($summary['revenue']) }}</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="cust-kpi-label">{{ __('reports.sales_by_category.kpis.profit') }}</div>
                <div class="cust-kpi-value num tnum @if ((float) $summary['profit'] > 0) text-emerald-600 dark:text-emerald-400 @elseif ((float) $summary['profit'] < 0) text-rose-600 dark:text-rose-400 @endif">{{ format_money($summary['profit']) }}</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="cust-kpi-label">{{ __('reports.sales_by_category.kpis.margin') }}</div>
                <div class="cust-kpi-value num tnum">{{ number_format((float) $summary['margin'], 1) }}%</div>
            </div></div>
        </div>

        <div class="card card-pad-0" x-data="dataTable({ rowsSelector: 'tbody > tr[data-dt-row]' })">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">
                    {{ __('reports.sales_by_category.list_title') }} (<span x-text="rowCount">{{ $rows->count() }}</span>)
                </div>
                <x-admin.dt-toolbar-actions />
            </div>
            <x-admin.report-filter-bar
                route="admin.reports.sales-by-category.index"
                :search-placeholder="__('reports.sales_by_category.filter.search')"
                :period="$period"
                :from="$from"
                :to="$to"
                :store-id="$storeId"
                :stores="$stores" />

            @if ($rows->isEmpty())
                <div class="dt-empty">
                    <span class="dt-empty-icon"><x-icon name="tree" class="w-5 h-5" /></span>
                    <div class="dt-empty-title">{{ __('reports.sales_by_category.empty.title') }}</div>
                    <div class="dt-empty-sub">{{ __('reports.sales_by_category.empty.sub') }}</div>
                </div>
            @else
                <div class="dt-scroll">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('reports.sales_by_category.columns.category') }}</th>
                            <th class="num">{{ __('reports.sales_by_category.columns.qty_sold') }}</th>
                            <th class="num">{{ __('reports.sales_by_category.columns.revenue') }}</th>
                            <th class="num">{{ __('reports.sales_by_category.columns.cost') }}</th>
                            <th class="num">{{ __('reports.sales_by_category.columns.profit') }}</th>
                            <th class="num">{{ __('reports.sales_by_category.columns.margin') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr data-dt-row>
                                <td class="font-medium">{{ $row['name'] }}</td>
                                <td class="num tnum fg-tertiary">{{ $row['qty_sold'] }}</td>
                                <td class="num tnum">{{ format_money($row['revenue']) }}</td>
                                <td class="num tnum fg-tertiary">{{ format_money($row['cost']) }}</td>
                                <td class="num tnum @if ((float) $row['profit'] < 0) text-rose-600 dark:text-rose-400 @endif">{{ format_money($row['profit']) }}</td>
                                <td class="num tnum">
                                    @php $m = (float) $row['margin_pct']; @endphp
                                    <span class="@if ($m >= 35) text-emerald-600 dark:text-emerald-400 @elseif ($m < 20) text-amber-600 dark:text-amber-400 @endif">{{ number_format($m, 1) }}%</span>
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
