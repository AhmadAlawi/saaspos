<x-admin-layout
    active="shifts-by-cashier"
    :title="__('reports.shifts_by_cashier.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('reports.nav.section'), 'href' => route('admin.reports.index')],
        ['label' => __('reports.shifts_by_cashier.title')],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('reports.shifts_by_cashier.title') }}</h1>
                <p class="page-sub">{{ __('reports.shifts_by_cashier.sub') }}</p>
            </div>
            @if ($rows->isNotEmpty())
                <div class="dropdown" x-data="dropdown">
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="toggle()" :aria-expanded="open">
                        <x-icon name="download" class="w-4 h-4" />
                        {{ __('reports.actions.export') }}
                        <x-icon name="chevron" class="w-4 h-4" />
                    </button>
                    <div class="dropdown-panel" x-show="open" x-cloak @click.outside="close()" @keydown.escape.window="close()">
                        <a href="{{ route('admin.reports.shifts-by-cashier.export', array_filter(['format' => 'csv', 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'store_id' => $storeId])) }}" class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('reports.actions.export_csv') }}</span>
                        </a>
                        <a href="{{ route('admin.reports.shifts-by-cashier.export', array_filter(['format' => 'xlsx', 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'store_id' => $storeId])) }}" class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('reports.actions.export_xlsx') }}</span>
                        </a>
                        <a href="{{ route('admin.reports.shifts-by-cashier.export', array_filter(['format' => 'pdf', 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'store_id' => $storeId])) }}" class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('reports.actions.export_pdf') }}</span>
                        </a>
                    </div>
                </div>
            @endif
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <div class="card"><div class="card-body">
                <div class="cust-kpi-label">{{ __('reports.shifts_by_cashier.kpis.cashiers') }}</div>
                <div class="cust-kpi-value num tnum">{{ number_format($summary['cashiers']) }}</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="cust-kpi-label">{{ __('reports.shifts_by_cashier.kpis.shifts') }}</div>
                <div class="cust-kpi-value num tnum">{{ number_format($summary['shifts_count']) }}</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="cust-kpi-label">{{ __('reports.shifts_by_cashier.kpis.hours') }}</div>
                <div class="cust-kpi-value num tnum">{{ number_format((float) $summary['total_hours'], 1) }}</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="cust-kpi-label">{{ __('reports.shifts_by_cashier.kpis.variance') }}</div>
                @php $sv = (float) $summary['total_variance']; @endphp
                <div class="cust-kpi-value num tnum @if ($sv < 0) text-rose-600 dark:text-rose-400 @elseif ($sv > 0) text-emerald-600 dark:text-emerald-400 @endif">{{ format_money($summary['total_variance']) }}</div>
            </div></div>
        </div>

        <div class="card card-pad-0" x-data="dataTable({ rowsSelector: 'tbody > tr[data-dt-row]' })">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">
                    {{ __('reports.shifts_by_cashier.list_title') }} (<span x-text="rowCount">{{ $rows->count() }}</span>)
                </div>
                <x-admin.dt-toolbar-actions />
            </div>
            <x-admin.report-filter-bar
                route="admin.reports.shifts-by-cashier.index"
                :search-placeholder="__('reports.shifts_by_cashier.filter.search')"
                :period="$period"
                :from="$from"
                :to="$to"
                :store-id="$storeId"
                :stores="$stores" />

            @if ($rows->isEmpty())
                <div class="dt-empty">
                    <span class="dt-empty-icon"><x-icon name="clock" class="w-5 h-5" /></span>
                    <div class="dt-empty-title">{{ __('reports.shifts_by_cashier.empty.title') }}</div>
                    <div class="dt-empty-sub">{{ __('reports.shifts_by_cashier.empty.sub') }}</div>
                </div>
            @else
                <div class="dt-scroll">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('reports.shifts_by_cashier.columns.cashier') }}</th>
                            <th class="num">{{ __('reports.shifts_by_cashier.columns.shifts') }}</th>
                            <th class="num">{{ __('reports.shifts_by_cashier.columns.hours') }}</th>
                            <th class="num">{{ __('reports.shifts_by_cashier.columns.avg_duration') }}</th>
                            <th class="num">{{ __('reports.shifts_by_cashier.columns.sales') }}</th>
                            <th class="num">{{ __('reports.shifts_by_cashier.columns.variance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr data-dt-row>
                                <td class="font-medium">{{ $row['name'] }}</td>
                                <td class="num tnum fg-tertiary">{{ number_format($row['shifts_count']) }}</td>
                                <td class="num tnum">{{ number_format((float) $row['total_hours'], 1) }}</td>
                                <td class="num tnum fg-tertiary">{{ number_format((float) $row['avg_duration'], 1) }}</td>
                                <td class="num tnum">{{ format_money($row['total_sales']) }}</td>
                                <td class="num tnum">
                                    @php $v = (float) $row['total_variance']; @endphp
                                    <span class="@if ($v < 0) text-rose-600 dark:text-rose-400 @elseif ($v > 0) text-emerald-600 dark:text-emerald-400 @endif">{{ format_money($row['total_variance']) }}</span>
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
