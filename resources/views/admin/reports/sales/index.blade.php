<x-admin-layout
    active="sales-report"
    :title="__('reports.sales_summary.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('reports.nav.section'), 'href' => route('admin.reports.index')],
        ['label' => __('reports.sales_summary.title')],
    ]">

    @php
        $kpis    = $data['kpis'];
        $byDay   = $data['by_day'];
        $byPay   = $data['by_payment'];
        $totals  = $data['totals'];
        $hasData = $byDay->isNotEmpty();
    @endphp

    <div class="page-wide">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('reports.sales_summary.title') }}</h1>
                <p class="page-sub">{{ __('reports.sales_summary.sub') }}</p>
            </div>
            @if ($hasData)
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
                            <a href="{{ route('admin.reports.sales.export', array_filter(['format' => 'csv', 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'store_id' => $storeId])) }}"
                               class="dropdown-item" @click="close()">
                                <span class="dropdown-item-label">{{ __('reports.actions.export_csv') }}</span>
                            </a>
                            <a href="{{ route('admin.reports.sales.export', array_filter(['format' => 'xlsx', 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'store_id' => $storeId])) }}"
                               class="dropdown-item" @click="close()">
                                <span class="dropdown-item-label">{{ __('reports.actions.export_xlsx') }}</span>
                            </a>
                            <a href="{{ route('admin.reports.sales.export', array_filter(['format' => 'pdf', 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'store_id' => $storeId])) }}"
                               class="dropdown-item" @click="close()">
                                <span class="dropdown-item-label">{{ __('reports.actions.export_pdf') }}</span>
                            </a>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- KPI cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-6 gap-3 mb-5">
            <div class="card">
                <div class="card-body">
                    <div class="cust-kpi-label">{{ __('reports.sales_summary.kpis.revenue') }}</div>
                    <div class="cust-kpi-value num tnum">{{ format_money($kpis['revenue']) }}</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="cust-kpi-label">{{ __('reports.sales_summary.kpis.transactions') }}</div>
                    <div class="cust-kpi-value num tnum">{{ number_format($kpis['transactions']) }}</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="cust-kpi-label">{{ __('reports.sales_summary.kpis.avg_ticket') }}</div>
                    <div class="cust-kpi-value num tnum">{{ format_money($kpis['avg_ticket']) }}</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="cust-kpi-label">{{ __('reports.sales_summary.kpis.discount') }}</div>
                    <div class="cust-kpi-value num tnum @if (bccomp((string) $kpis['discount'], '0', 4) > 0) text-amber-600 dark:text-amber-400 @endif">
                        {{ format_money($kpis['discount']) }}
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="cust-kpi-label">{{ __('reports.sales_summary.kpis.tax') }}</div>
                    <div class="cust-kpi-value num tnum">{{ format_money($kpis['tax']) }}</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="cust-kpi-label">{{ __('reports.sales_summary.kpis.refunds') }}</div>
                    <div class="cust-kpi-value num tnum @if (bccomp((string) $kpis['refunds'], '0', 4) > 0) text-rose-600 dark:text-rose-400 @endif">
                        {{ format_money($kpis['refunds']) }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="card card-pad-0 mb-0"
             x-data="dataTable({ rowsSelector: 'tbody > tr[data-dt-row]' })">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">{{ __('reports.sales_summary.list_title') }}</div>
            </div>
            <x-admin.report-filter-bar
                route="admin.reports.sales.index"
                :searchable="false"
                :period="$period"
                :from="$from"
                :to="$to"
                :store-id="$storeId"
                :stores="$stores" />

            @if (! $hasData)
                <div class="dt-empty">
                    <span class="dt-empty-icon"><x-icon name="bar" class="w-5 h-5" /></span>
                    <div class="dt-empty-title">{{ __('reports.sales_summary.empty.title') }}</div>
                    <div class="dt-empty-sub">{{ __('reports.sales_summary.empty.sub') }}</div>
                </div>
            @else
                <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_280px] gap-0 divide-y divide-subtle lg:divide-y-0 lg:divide-x">
                    {{-- Daily breakdown --}}
                    <div>
                        <div class="dt-scroll">
                        <table class="dt-table">
                            <thead>
                                <tr>
                                    <th>{{ __('reports.sales_summary.columns.date') }}</th>
                                    <th class="num">{{ __('reports.sales_summary.columns.transactions') }}</th>
                                    <th class="num">{{ __('reports.sales_summary.columns.subtotal') }}</th>
                                    <th class="num">{{ __('reports.sales_summary.columns.discount') }}</th>
                                    <th class="num">{{ __('reports.sales_summary.columns.tax') }}</th>
                                    <th class="num">{{ __('reports.sales_summary.columns.grand_total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($byDay as $row)
                                    <tr data-dt-row>
                                        <td>{{ format_date(\Carbon\CarbonImmutable::parse($row->day)) }}</td>
                                        <td class="num tnum">{{ number_format($row->transactions) }}</td>
                                        <td class="num tnum">{{ format_money($row->subtotal) }}</td>
                                        <td class="num tnum fg-tertiary">{{ format_money($row->discount) }}</td>
                                        <td class="num tnum fg-tertiary">{{ format_money($row->tax) }}</td>
                                        <td class="num tnum font-medium">{{ format_money($row->grand_total) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td>{{ __('reports.totals_row') }}</td>
                                    <td class="num tnum">{{ number_format($kpis['transactions']) }}</td>
                                    <td class="num tnum">{{ format_money($totals['subtotal']) }}</td>
                                    <td class="num tnum fg-tertiary">{{ format_money($totals['discount']) }}</td>
                                    <td class="num tnum fg-tertiary">{{ format_money($totals['tax']) }}</td>
                                    <td class="num tnum">{{ format_money($totals['grand_total']) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                        </div>
                        <x-admin.dt-pager />
                    </div>

                    {{-- Payment method breakdown --}}
                    <div>
                        <div class="p-4 text-sm font-medium fg-secondary border-b border-subtle">
                            {{ __('reports.sales_summary.payment_breakdown') }}
                        </div>
                        @if ($byPay->isEmpty())
                            <p class="p-4 fg-tertiary text-sm">{{ __('reports.sales_summary.payment_empty') }}</p>
                        @else
                            @php $payTotal = $byPay->sum('total'); @endphp
                            <div class="divide-y divide-subtle">
                                @foreach ($byPay as $pm)
                                    @php $pct = $payTotal > 0 ? round(($pm->total / $payTotal) * 100) : 0; @endphp
                                    <div class="px-4 py-3">
                                        <div class="flex items-center justify-between text-sm">
                                            <span class="font-medium">{{ $pm->method_name }}</span>
                                            <span class="num tnum">{{ format_money($pm->total) }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 mt-1.5">
                                            <div class="flex-1 h-1.5 bg-surface rounded-full overflow-hidden">
                                                <div class="h-full rounded-full bg-[var(--accent)]" style="width: {{ $pct }}%"></div>
                                            </div>
                                            <span class="text-xs fg-tertiary tnum w-8 text-end">{{ $pct }}%</span>
                                        </div>
                                        <div class="text-xs fg-tertiary mt-0.5">
                                            {{ number_format($pm->sale_count) }} {{ __('reports.sales_summary.sales_suffix') }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
