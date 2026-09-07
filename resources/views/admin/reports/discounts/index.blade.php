<x-admin-layout
    active="discounts"
    :title="__('reports.discounts.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('reports.nav.section'), 'href' => route('admin.reports.index')],
        ['label' => __('reports.discounts.title')],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('reports.discounts.title') }}</h1>
                <p class="page-sub">{{ __('reports.discounts.sub') }}</p>
            </div>
            @if ($rows->isNotEmpty())
                <div class="dropdown" x-data="dropdown">
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="toggle()" :aria-expanded="open">
                        <x-icon name="download" class="w-4 h-4" />
                        {{ __('reports.actions.export') }}
                        <x-icon name="chevron" class="w-4 h-4" />
                    </button>
                    <div class="dropdown-panel" x-show="open" x-cloak @click.outside="close()" @keydown.escape.window="close()">
                        <a href="{{ route('admin.reports.discounts.export', array_filter(['format' => 'csv', 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'store_id' => $storeId])) }}" class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('reports.actions.export_csv') }}</span>
                        </a>
                        <a href="{{ route('admin.reports.discounts.export', array_filter(['format' => 'xlsx', 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'store_id' => $storeId])) }}" class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('reports.actions.export_xlsx') }}</span>
                        </a>
                        <a href="{{ route('admin.reports.discounts.export', array_filter(['format' => 'pdf', 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'store_id' => $storeId])) }}" class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('reports.actions.export_pdf') }}</span>
                        </a>
                    </div>
                </div>
            @endif
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <div class="card"><div class="card-body">
                <div class="cust-kpi-label">{{ __('reports.discounts.kpis.count') }}</div>
                <div class="cust-kpi-value num tnum">{{ number_format($summary['count']) }}</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="cust-kpi-label">{{ __('reports.discounts.kpis.total') }}</div>
                <div class="cust-kpi-value num tnum text-amber-600 dark:text-amber-400">{{ format_money($summary['total']) }}</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="cust-kpi-label">{{ __('reports.discounts.kpis.avg') }}</div>
                <div class="cust-kpi-value num tnum">{{ format_money($summary['avg']) }}</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="cust-kpi-label">{{ __('reports.discounts.kpis.approved') }}</div>
                <div class="cust-kpi-value num tnum">{{ number_format($summary['approved']) }}</div>
            </div></div>
        </div>

        <div class="card card-pad-0" x-data="dataTable({ rowsSelector: 'tbody > tr[data-dt-row]' })">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">
                    {{ __('reports.discounts.list_title') }} (<span x-text="rowCount">{{ $rows->count() }}</span>)
                </div>
                <x-admin.dt-toolbar-actions />
            </div>
            <x-admin.report-filter-bar
                route="admin.reports.discounts.index"
                :search-placeholder="__('reports.discounts.filter.search')"
                :period="$period"
                :from="$from"
                :to="$to"
                :store-id="$storeId"
                :stores="$stores" />

            @if ($rows->isEmpty())
                <div class="dt-empty">
                    <span class="dt-empty-icon"><x-icon name="tag" class="w-5 h-5" /></span>
                    <div class="dt-empty-title">{{ __('reports.discounts.empty.title') }}</div>
                    <div class="dt-empty-sub">{{ __('reports.discounts.empty.sub') }}</div>
                </div>
            @else
                <div class="dt-scroll">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('reports.discounts.columns.sale') }}</th>
                            <th>{{ __('reports.discounts.columns.date') }}</th>
                            <th>{{ __('reports.discounts.columns.cashier') }}</th>
                            <th>{{ __('reports.discounts.columns.reason') }}</th>
                            <th class="num">{{ __('reports.discounts.columns.discount') }}</th>
                            <th>{{ __('reports.discounts.columns.approved_by') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="prod-row" data-dt-row @click="window.location.href = {{ \Illuminate\Support\Js::from(route('admin.sales.show', $row['sale_id'])) }}">
                                <td class="mono">{{ $row['number'] }}</td>
                                <td class="fg-tertiary">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d M Y') }}</td>
                                <td>{{ $row['cashier'] }}</td>
                                <td>
                                    @if ($row['reason_category'])
                                        <span class="prod-badge prod-badge-muted">{{ __('cashier.discount.reason_categories.'.$row['reason_category']) }}</span>
                                    @endif
                                    @if ($row['reason'])
                                        <span class="fg-tertiary text-xs">{{ $row['reason'] }}</span>
                                    @endif
                                    @if (! $row['reason_category'] && ! $row['reason'])—@endif
                                </td>
                                <td class="num tnum text-amber-600 dark:text-amber-400">
                                    −{{ format_money($row['amount']) }}
                                    <span class="fg-tertiary text-xs">({{ $row['type'] === 'pct' ? rtrim(rtrim($row['value'], '0'), '.').'%' : format_money($row['value']) }})</span>
                                </td>
                                <td>
                                    @if ($row['approver'])
                                        <span class="prod-badge prod-badge-warning">{{ $row['approver'] }}</span>
                                    @else
                                        <span class="fg-tertiary">—</span>
                                    @endif
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
