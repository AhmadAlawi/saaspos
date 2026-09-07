<x-admin-layout
    active="aged-receivables"
    :title="__('reports.aged_receivables.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('reports.nav.section')],
        ['label' => __('reports.aged_receivables.title')],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('reports.aged_receivables.title') }}</h1>
                <p class="page-sub">{{ __('reports.aged_receivables.sub') }}</p>
            </div>
            @if ($rows->isNotEmpty())
                <div class="flex items-center gap-2">
                    <div class="dropdown" x-data="dropdown">
                        <button type="button"
                                class="pos-btn pos-btn-sm pos-btn-ghost"
                                @click="toggle()"
                                :aria-expanded="open">
                            <x-icon name="download" class="w-4 h-4" />
                            {{ __('reports.aged_receivables.actions.export') }}
                            <x-icon name="chevron" class="w-4 h-4" />
                        </button>
                        <div class="dropdown-panel"
                             x-show="open"
                             x-cloak
                             @click.outside="close()"
                             @keydown.escape.window="close()">
                            <a href="{{ route('admin.reports.aged-receivables.export', array_filter(['format' => 'csv', 'store_id' => $storeId, 'as_of' => $asOf->toDateString()])) }}"
                               class="dropdown-item" @click="close()">
                                <span class="dropdown-item-label">{{ __('reports.aged_receivables.actions.export_csv') }}</span>
                            </a>
                            <a href="{{ route('admin.reports.aged-receivables.export', array_filter(['format' => 'xlsx', 'store_id' => $storeId, 'as_of' => $asOf->toDateString()])) }}"
                               class="dropdown-item" @click="close()">
                                <span class="dropdown-item-label">{{ __('reports.aged_receivables.actions.export_xlsx') }}</span>
                            </a>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- KPI cards: per-bucket totals + grand total. Same shape as
             the Customer-show page (card > card-body > cust-kpi-*) so
             padding + typography match. Lights up amber once anything
             past 30 days is owed; red beyond 60. --}}
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
            <div class="card">
                <div class="card-body">
                    <div class="cust-kpi-label">{{ __('reports.aged_receivables.kpis.total') }}</div>
                    <div class="cust-kpi-value num tnum">{{ format_money($summary['total']) }}</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="cust-kpi-label">{{ __('reports.aged_receivables.kpis.b0_30') }}</div>
                    <div class="cust-kpi-value num tnum">{{ format_money($summary['b0_30']) }}</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="cust-kpi-label">{{ __('reports.aged_receivables.kpis.b31_60') }}</div>
                    <div class="cust-kpi-value num tnum @if (bccomp($summary['b31_60'], '0', 4) > 0) text-amber-600 dark:text-amber-400 @endif">{{ format_money($summary['b31_60']) }}</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="cust-kpi-label">{{ __('reports.aged_receivables.kpis.b61_90') }}</div>
                    <div class="cust-kpi-value num tnum @if (bccomp($summary['b61_90'], '0', 4) > 0) text-rose-600 dark:text-rose-400 @endif">{{ format_money($summary['b61_90']) }}</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="cust-kpi-label">{{ __('reports.aged_receivables.kpis.b90_plus') }}</div>
                    <div class="cust-kpi-value num tnum @if (bccomp($summary['b90_plus'], '0', 4) > 0) text-rose-700 dark:text-rose-500 font-semibold @endif">{{ format_money($summary['b90_plus']) }}</div>
                </div>
            </div>
        </div>

        <div class="card card-pad-0"
             x-data="dataTable({ rowsSelector: 'tbody > tr[data-dt-row]' })">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">{{ __('reports.aged_receivables.list_title') }} (<span x-text="rowCount">{{ $rows->count() }}</span>)</div>
                <x-admin.dt-toolbar-actions />
            </div>
            <form method="GET" action="{{ route('admin.reports.aged-receivables.index') }}" class="inv-filter">
                <label class="field inv-filter-date">
                    <span class="field-label">{{ __('reports.aged_receivables.filter.as_of') }}</span>
                    <input type="text" name="as_of" value="{{ $asOf->toDateString() }}"
                           class="pos-input js-datepicker" data-fp-submit-on-change="1">
                </label>
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('reports.aged_receivables.filter.store') }}</span>
                    <select name="store_id" class="pos-input" x-data="enhancedSelect()" onchange="this.form.submit()">
                        <option value="">{{ __('reports.aged_receivables.filter.store_all') }}</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" @selected($storeId === $store->id)>{{ $store->name }}</option>
                        @endforeach
                    </select>
                </label>
                {{-- Client-side search bound to the dataTable mixin's
                     `search` state (same as <x-admin.dt-search/>) but
                     styled inline with the other filter fields so the
                     toolbar layout matches the purchases / sales index.

                     `data-no-live-search` opts out of the global
                     inv-search-live auto-submit handler — typing must
                     stay client-only; submitting would reload the page
                     and wipe the filter state. --}}
                <label class="field inv-filter-search">
                    <span class="field-label">&nbsp;</span>
                    <span class="inv-search">
                        <span class="inv-search-icon"><x-icon name="search" class="w-4 h-4" /></span>
                        <input type="search"
                               x-model.debounce.150ms="search"
                               data-no-live-search
                               class="pos-input"
                               placeholder="{{ __('reports.aged_receivables.filter.search') }}"
                               aria-label="{{ __('reports.aged_receivables.filter.search') }}"
                               @keydown.enter.prevent>
                    </span>
                </label>
                <x-admin.filter-reset route="admin.reports.aged-receivables.index" />
            </form>

            @if ($rows->isEmpty())
                <div class="dt-empty">
                    <span class="dt-empty-icon"><x-icon name="check" class="w-5 h-5" /></span>
                    <div class="dt-empty-title">{{ __('reports.aged_receivables.empty.title') }}</div>
                    <div class="dt-empty-sub">{{ __('reports.aged_receivables.empty.sub') }}</div>
                </div>
            @else
                <div class="dt-scroll">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('reports.aged_receivables.columns.customer') }}</th>
                            <th class="num">{{ __('reports.aged_receivables.columns.b0_30') }}</th>
                            <th class="num">{{ __('reports.aged_receivables.columns.b31_60') }}</th>
                            <th class="num">{{ __('reports.aged_receivables.columns.b61_90') }}</th>
                            <th class="num">{{ __('reports.aged_receivables.columns.b90_plus') }}</th>
                            <th class="num">{{ __('reports.aged_receivables.columns.total') }}</th>
                            <th class="num">{{ __('reports.aged_receivables.columns.oldest') }}</th>
                            <th class="num">{{ __('reports.aged_receivables.columns.sales_count') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr data-dt-row
                                data-dt-name="{{ $row['customer_name'] }}"
                                class="prod-row"
                                @click="window.location.href = {{ \Illuminate\Support\Js::from(route('admin.customers.show', $row['customer_id'])) }}">
                                <td>
                                    <div class="store-row-name-line">
                                        <span class="store-row-name">{{ $row['customer_name'] }}</span>
                                        @if ($row['customer_code'])
                                            <span class="fg-tertiary mono text-xs">{{ $row['customer_code'] }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="num tnum">{{ format_money($row['b0_30']) }}</td>
                                <td class="num tnum @if (bccomp($row['b31_60'], '0', 4) > 0) text-amber-600 dark:text-amber-400 @endif">{{ format_money($row['b31_60']) }}</td>
                                <td class="num tnum @if (bccomp($row['b61_90'], '0', 4) > 0) text-rose-600 dark:text-rose-400 @endif">{{ format_money($row['b61_90']) }}</td>
                                <td class="num tnum @if (bccomp($row['b90_plus'], '0', 4) > 0) text-rose-700 dark:text-rose-500 font-semibold @endif">{{ format_money($row['b90_plus']) }}</td>
                                <td class="num tnum font-semibold">{{ format_money($row['total']) }}</td>
                                <td class="num tnum fg-tertiary">{{ $row['oldest_days'] }}</td>
                                <td class="num tnum fg-tertiary">{{ $row['sales_count'] }}</td>
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
