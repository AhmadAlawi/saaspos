<x-admin-layout
    active="top-suppliers"
    :title="__('reports.top_suppliers.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('reports.nav.section'), 'href' => route('admin.reports.index')],
        ['label' => __('reports.top_suppliers.title')],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('reports.top_suppliers.title') }}</h1>
                <p class="page-sub">{{ __('reports.top_suppliers.sub') }}</p>
            </div>
            @if ($rows->isNotEmpty())
                <div class="dropdown" x-data="dropdown">
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="toggle()" :aria-expanded="open">
                        <x-icon name="download" class="w-4 h-4" />
                        {{ __('reports.actions.export') }}
                        <x-icon name="chevron" class="w-4 h-4" />
                    </button>
                    <div class="dropdown-panel" x-show="open" x-cloak @click.outside="close()" @keydown.escape.window="close()">
                        <a href="{{ route('admin.reports.top-suppliers.export', array_filter(['format' => 'csv', 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'store_id' => $storeId])) }}" class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('reports.actions.export_csv') }}</span>
                        </a>
                        <a href="{{ route('admin.reports.top-suppliers.export', array_filter(['format' => 'xlsx', 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'store_id' => $storeId])) }}" class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('reports.actions.export_xlsx') }}</span>
                        </a>
                        <a href="{{ route('admin.reports.top-suppliers.export', array_filter(['format' => 'pdf', 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'store_id' => $storeId])) }}" class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('reports.actions.export_pdf') }}</span>
                        </a>
                    </div>
                </div>
            @endif
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <div class="card"><div class="card-body">
                <div class="cust-kpi-label">{{ __('reports.top_suppliers.kpis.suppliers') }}</div>
                <div class="cust-kpi-value num tnum">{{ number_format($summary['suppliers']) }}</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="cust-kpi-label">{{ __('reports.top_suppliers.kpis.purchased') }}</div>
                <div class="cust-kpi-value num tnum">{{ format_money($summary['purchased']) }}</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="cust-kpi-label">{{ __('reports.top_suppliers.kpis.paid') }}</div>
                <div class="cust-kpi-value num tnum text-emerald-600 dark:text-emerald-400">{{ format_money($summary['paid']) }}</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="cust-kpi-label">{{ __('reports.top_suppliers.kpis.balance') }}</div>
                <div class="cust-kpi-value num tnum @if ((float) $summary['balance'] > 0) text-amber-600 dark:text-amber-400 @endif">{{ format_money($summary['balance']) }}</div>
            </div></div>
        </div>

        <div class="card card-pad-0" x-data="dataTable({ rowsSelector: 'tbody > tr[data-dt-row]' })">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">
                    {{ __('reports.top_suppliers.list_title') }} (<span x-text="rowCount">{{ $rows->count() }}</span>)
                </div>
                <x-admin.dt-toolbar-actions />
            </div>
            <x-admin.report-filter-bar
                route="admin.reports.top-suppliers.index"
                :search-placeholder="__('reports.top_suppliers.filter.search')"
                :period="$period"
                :from="$from"
                :to="$to"
                :store-id="$storeId"
                :stores="$stores" />

            @if ($rows->isEmpty())
                <div class="dt-empty">
                    <span class="dt-empty-icon"><x-icon name="truck" class="w-5 h-5" /></span>
                    <div class="dt-empty-title">{{ __('reports.top_suppliers.empty.title') }}</div>
                    <div class="dt-empty-sub">{{ __('reports.top_suppliers.empty.sub') }}</div>
                </div>
            @else
                <div class="dt-scroll">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('reports.top_suppliers.columns.supplier') }}</th>
                            <th class="num">{{ __('reports.top_suppliers.columns.purchases') }}</th>
                            <th class="num">{{ __('reports.top_suppliers.columns.purchased') }}</th>
                            <th class="num">{{ __('reports.top_suppliers.columns.paid') }}</th>
                            <th class="num">{{ __('reports.top_suppliers.columns.balance') }}</th>
                            <th>{{ __('reports.top_suppliers.columns.last_purchase') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="prod-row" data-dt-row @click="window.location.href = {{ \Illuminate\Support\Js::from(route('admin.suppliers.show', $row['supplier_id'])) }}">
                                <td class="font-medium">
                                    {{ $row['name'] }}
                                    @if ($row['code'])
                                        <span class="fg-tertiary text-xs mono">{{ $row['code'] }}</span>
                                    @endif
                                </td>
                                <td class="num tnum fg-tertiary">{{ number_format($row['purchases_count']) }}</td>
                                <td class="num tnum">{{ format_money($row['total_purchased']) }}</td>
                                <td class="num tnum fg-tertiary">{{ format_money($row['paid']) }}</td>
                                <td class="num tnum @if ((float) $row['balance'] > 0) text-amber-600 dark:text-amber-400 @endif">{{ format_money($row['balance']) }}</td>
                                <td class="fg-tertiary">{{ \Illuminate\Support\Carbon::parse($row['last_purchase'])->format('d M Y') }}</td>
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
