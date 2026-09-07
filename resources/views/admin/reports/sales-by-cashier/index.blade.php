<x-admin-layout
    active="sales-by-cashier"
    :title="__('reports.sales_by_cashier.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('reports.nav.section'), 'href' => route('admin.reports.index')],
        ['label' => __('reports.sales_by_cashier.title')],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('reports.sales_by_cashier.title') }}</h1>
                <p class="page-sub">{{ __('reports.sales_by_cashier.sub') }}</p>
            </div>
            @if ($rows->isNotEmpty())
                <div class="dropdown" x-data="dropdown">
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="toggle()" :aria-expanded="open">
                        <x-icon name="download" class="w-4 h-4" />
                        {{ __('reports.actions.export') }}
                        <x-icon name="chevron" class="w-4 h-4" />
                    </button>
                    <div class="dropdown-panel" x-show="open" x-cloak @click.outside="close()" @keydown.escape.window="close()">
                        <a href="{{ route('admin.reports.sales-by-cashier.export', array_filter(['format' => 'csv', 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'store_id' => $storeId])) }}" class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('reports.actions.export_csv') }}</span>
                        </a>
                        <a href="{{ route('admin.reports.sales-by-cashier.export', array_filter(['format' => 'xlsx', 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'store_id' => $storeId])) }}" class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('reports.actions.export_xlsx') }}</span>
                        </a>
                        <a href="{{ route('admin.reports.sales-by-cashier.export', array_filter(['format' => 'pdf', 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'store_id' => $storeId])) }}" class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('reports.actions.export_pdf') }}</span>
                        </a>
                    </div>
                </div>
            @endif
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <div class="card"><div class="card-body">
                <div class="cust-kpi-label">{{ __('reports.sales_by_cashier.kpis.cashiers') }}</div>
                <div class="cust-kpi-value num tnum">{{ number_format($summary['cashiers']) }}</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="cust-kpi-label">{{ __('reports.sales_by_cashier.kpis.sales') }}</div>
                <div class="cust-kpi-value num tnum">{{ number_format($summary['sales_count']) }}</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="cust-kpi-label">{{ __('reports.sales_by_cashier.kpis.revenue') }}</div>
                <div class="cust-kpi-value num tnum">{{ format_money($summary['revenue']) }}</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="cust-kpi-label">{{ __('reports.sales_by_cashier.kpis.avg_basket') }}</div>
                <div class="cust-kpi-value num tnum">{{ format_money($summary['avg_basket']) }}</div>
            </div></div>
        </div>

        <div class="card card-pad-0" x-data="dataTable({ rowsSelector: 'tbody > tr[data-dt-row]' })">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">
                    {{ __('reports.sales_by_cashier.list_title') }} (<span x-text="rowCount">{{ $rows->count() }}</span>)
                </div>
                <x-admin.dt-toolbar-actions />
            </div>
            <x-admin.report-filter-bar
                route="admin.reports.sales-by-cashier.index"
                :search-placeholder="__('reports.sales_by_cashier.filter.search')"
                :period="$period"
                :from="$from"
                :to="$to"
                :store-id="$storeId"
                :stores="$stores" />

            @if ($rows->isEmpty())
                <div class="dt-empty">
                    <span class="dt-empty-icon"><x-icon name="user" class="w-5 h-5" /></span>
                    <div class="dt-empty-title">{{ __('reports.sales_by_cashier.empty.title') }}</div>
                    <div class="dt-empty-sub">{{ __('reports.sales_by_cashier.empty.sub') }}</div>
                </div>
            @else
                <div class="dt-scroll">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('reports.sales_by_cashier.columns.cashier') }}</th>
                            <th class="num">{{ __('reports.sales_by_cashier.columns.sales') }}</th>
                            <th class="num">{{ __('reports.sales_by_cashier.columns.items') }}</th>
                            <th class="num">{{ __('reports.sales_by_cashier.columns.revenue') }}</th>
                            <th class="num">{{ __('reports.sales_by_cashier.columns.discounts') }}</th>
                            <th class="num">{{ __('reports.sales_by_cashier.columns.avg_basket') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr data-dt-row>
                                <td class="font-medium">{{ $row['name'] }}</td>
                                <td class="num tnum">{{ number_format($row['sales_count']) }}</td>
                                <td class="num tnum fg-tertiary">{{ $row['items_sold'] }}</td>
                                <td class="num tnum">{{ format_money($row['revenue']) }}</td>
                                <td class="num tnum @if ((float) $row['discounts'] > 0) text-amber-600 dark:text-amber-400 @endif">{{ format_money($row['discounts']) }}</td>
                                <td class="num tnum">{{ format_money($row['avg_basket']) }}</td>
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
