<x-admin-layout
    active="sales-by-payment-method"
    :title="__('reports.sales_by_payment_method.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('reports.nav.section'), 'href' => route('admin.reports.index')],
        ['label' => __('reports.sales_by_payment_method.title')],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('reports.sales_by_payment_method.title') }}</h1>
                <p class="page-sub">{{ __('reports.sales_by_payment_method.sub') }}</p>
            </div>
            @if ($rows->isNotEmpty())
                <div class="dropdown" x-data="dropdown">
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="toggle()" :aria-expanded="open">
                        <x-icon name="download" class="w-4 h-4" />
                        {{ __('reports.actions.export') }}
                        <x-icon name="chevron" class="w-4 h-4" />
                    </button>
                    <div class="dropdown-panel" x-show="open" x-cloak @click.outside="close()" @keydown.escape.window="close()">
                        <a href="{{ route('admin.reports.sales-by-payment-method.export', array_filter(['format' => 'csv', 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'store_id' => $storeId])) }}" class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('reports.actions.export_csv') }}</span>
                        </a>
                        <a href="{{ route('admin.reports.sales-by-payment-method.export', array_filter(['format' => 'xlsx', 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'store_id' => $storeId])) }}" class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('reports.actions.export_xlsx') }}</span>
                        </a>
                        <a href="{{ route('admin.reports.sales-by-payment-method.export', array_filter(['format' => 'pdf', 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'store_id' => $storeId])) }}" class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('reports.actions.export_pdf') }}</span>
                        </a>
                    </div>
                </div>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-3 mb-5">
            <div class="card"><div class="card-body">
                <div class="cust-kpi-label">{{ __('reports.sales_by_payment_method.kpis.methods') }}</div>
                <div class="cust-kpi-value num tnum">{{ number_format($summary['methods']) }}</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="cust-kpi-label">{{ __('reports.sales_by_payment_method.kpis.total') }}</div>
                <div class="cust-kpi-value num tnum">{{ format_money($summary['total']) }}</div>
            </div></div>
        </div>

        <div class="card card-pad-0" x-data="dataTable({ rowsSelector: 'tbody > tr[data-dt-row]' })">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">
                    {{ __('reports.sales_by_payment_method.list_title') }} (<span x-text="rowCount">{{ $rows->count() }}</span>)
                </div>
                <x-admin.dt-toolbar-actions />
            </div>
            <x-admin.report-filter-bar
                route="admin.reports.sales-by-payment-method.index"
                :searchable="false"
                :period="$period"
                :from="$from"
                :to="$to"
                :store-id="$storeId"
                :stores="$stores" />

            @if ($rows->isEmpty())
                <div class="dt-empty">
                    <span class="dt-empty-icon"><x-icon name="cash" class="w-5 h-5" /></span>
                    <div class="dt-empty-title">{{ __('reports.sales_by_payment_method.empty.title') }}</div>
                    <div class="dt-empty-sub">{{ __('reports.sales_by_payment_method.empty.sub') }}</div>
                </div>
            @else
                @php $grand = (float) $summary['total']; @endphp
                <div class="dt-scroll">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('reports.sales_by_payment_method.columns.method') }}</th>
                            <th class="num">{{ __('reports.sales_by_payment_method.columns.sales') }}</th>
                            <th class="num">{{ __('reports.sales_by_payment_method.columns.total') }}</th>
                            <th class="num">{{ __('reports.sales_by_payment_method.columns.share') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr data-dt-row>
                                <td class="font-medium">{{ $row['name'] }}</td>
                                <td class="num tnum">{{ number_format($row['sales_count']) }}</td>
                                <td class="num tnum">{{ format_money($row['total']) }}</td>
                                <td class="num tnum fg-tertiary">{{ $grand > 0 ? number_format(((float) $row['total'] / $grand) * 100, 1) : '0.0' }}%</td>
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
