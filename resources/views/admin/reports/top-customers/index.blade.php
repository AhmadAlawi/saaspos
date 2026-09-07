<x-admin-layout
    active="top-customers"
    :title="__('reports.top_customers.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('reports.nav.section'), 'href' => route('admin.reports.index')],
        ['label' => __('reports.top_customers.title')],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('reports.top_customers.title') }}</h1>
                <p class="page-sub">{{ __('reports.top_customers.sub') }}</p>
            </div>
            @if ($rows->isNotEmpty())
                <div class="dropdown" x-data="dropdown">
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="toggle()" :aria-expanded="open">
                        <x-icon name="download" class="w-4 h-4" />
                        {{ __('reports.actions.export') }}
                        <x-icon name="chevron" class="w-4 h-4" />
                    </button>
                    <div class="dropdown-panel" x-show="open" x-cloak @click.outside="close()" @keydown.escape.window="close()">
                        <a href="{{ route('admin.reports.top-customers.export', array_filter(['format' => 'csv', 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'store_id' => $storeId])) }}" class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('reports.actions.export_csv') }}</span>
                        </a>
                        <a href="{{ route('admin.reports.top-customers.export', array_filter(['format' => 'xlsx', 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'store_id' => $storeId])) }}" class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('reports.actions.export_xlsx') }}</span>
                        </a>
                        <a href="{{ route('admin.reports.top-customers.export', array_filter(['format' => 'pdf', 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'store_id' => $storeId])) }}" class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('reports.actions.export_pdf') }}</span>
                        </a>
                    </div>
                </div>
            @endif
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <div class="card"><div class="card-body">
                <div class="cust-kpi-label">{{ __('reports.top_customers.kpis.customers') }}</div>
                <div class="cust-kpi-value num tnum">{{ number_format($summary['customers']) }}</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="cust-kpi-label">{{ __('reports.top_customers.kpis.revenue') }}</div>
                <div class="cust-kpi-value num tnum">{{ format_money($summary['revenue']) }}</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="cust-kpi-label">{{ __('reports.top_customers.kpis.avg_basket') }}</div>
                <div class="cust-kpi-value num tnum">{{ format_money($summary['avg_basket']) }}</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="cust-kpi-label">{{ __('reports.top_customers.kpis.outstanding') }}</div>
                <div class="cust-kpi-value num tnum @if ((float) $summary['outstanding'] > 0) text-amber-600 dark:text-amber-400 @endif">{{ format_money($summary['outstanding']) }}</div>
            </div></div>
        </div>

        <div class="card card-pad-0" x-data="dataTable({ rowsSelector: 'tbody > tr[data-dt-row]' })">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">
                    {{ __('reports.top_customers.list_title') }} (<span x-text="rowCount">{{ $rows->count() }}</span>)
                </div>
                <x-admin.dt-toolbar-actions />
            </div>
            <x-admin.report-filter-bar
                route="admin.reports.top-customers.index"
                :search-placeholder="__('reports.top_customers.filter.search')"
                :period="$period"
                :from="$from"
                :to="$to"
                :store-id="$storeId"
                :stores="$stores" />

            @if ($rows->isEmpty())
                <div class="dt-empty">
                    <span class="dt-empty-icon"><x-icon name="user" class="w-5 h-5" /></span>
                    <div class="dt-empty-title">{{ __('reports.top_customers.empty.title') }}</div>
                    <div class="dt-empty-sub">{{ __('reports.top_customers.empty.sub') }}</div>
                </div>
            @else
                <div class="dt-scroll">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('reports.top_customers.columns.customer') }}</th>
                            <th class="num">{{ __('reports.top_customers.columns.visits') }}</th>
                            <th class="num">{{ __('reports.top_customers.columns.total_spent') }}</th>
                            <th class="num">{{ __('reports.top_customers.columns.avg_basket') }}</th>
                            <th>{{ __('reports.top_customers.columns.last_visit') }}</th>
                            <th class="num">{{ __('reports.top_customers.columns.outstanding') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="prod-row" data-dt-row @click="window.location.href = {{ \Illuminate\Support\Js::from(route('admin.customers.show', $row['customer_id'])) }}">
                                <td class="font-medium">
                                    {{ $row['name'] }}
                                    @if ($row['group_name'])
                                        <span class="prod-badge prod-badge-muted">{{ $row['group_name'] }}</span>
                                    @endif
                                    @if ($row['code'])
                                        <span class="fg-tertiary text-xs mono">{{ $row['code'] }}</span>
                                    @endif
                                </td>
                                <td class="num tnum fg-tertiary">{{ number_format($row['visits']) }}</td>
                                <td class="num tnum">{{ format_money($row['total_spent']) }}</td>
                                <td class="num tnum fg-tertiary">{{ format_money($row['avg_basket']) }}</td>
                                <td class="fg-tertiary">{{ \Illuminate\Support\Carbon::parse($row['last_visit'])->format('d M Y') }}</td>
                                <td class="num tnum @if ((float) $row['outstanding'] > 0) text-amber-600 dark:text-amber-400 @endif">{{ format_money($row['outstanding']) }}</td>
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
