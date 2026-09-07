<x-admin-layout
    active="trial-balance"
    :title="__('reports.trial_balance.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('reports.nav.section'), 'href' => route('admin.reports.index')],
        ['label' => __('reports.trial_balance.title')],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('reports.trial_balance.title') }}</h1>
                <p class="page-sub">{{ __('reports.trial_balance.sub') }}</p>
            </div>
            @if ($rows->isNotEmpty())
                <div class="dropdown" x-data="dropdown">
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="toggle()" :aria-expanded="open">
                        <x-icon name="download" class="w-4 h-4" />
                        {{ __('reports.actions.export') }}
                        <x-icon name="chevron" class="w-4 h-4" />
                    </button>
                    <div class="dropdown-panel" x-show="open" x-cloak @click.outside="close()" @keydown.escape.window="close()">
                        @foreach (['csv' => __('reports.actions.export_csv'), 'xlsx' => __('reports.actions.export_xlsx'), 'pdf' => __('reports.actions.export_pdf')] as $fmt => $label)
                            <a href="{{ route('admin.reports.trial-balance.export', array_filter(['format' => $fmt, 'store_id' => $storeId, 'as_of' => $asOf->toDateString()])) }}"
                               class="dropdown-item" @click="close()">
                                <span class="dropdown-item-label">{{ $label }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Totals + the all-important balanced check. Debit and credit
             totals must be equal; if they drift the badge turns red. --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
            <div class="card">
                <div class="card-body">
                    <div class="cust-kpi-label">{{ __('reports.trial_balance.kpis.total_debit') }}</div>
                    <div class="cust-kpi-value num tnum">{{ format_money($summary['total_debit']) }}</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="cust-kpi-label">{{ __('reports.trial_balance.kpis.total_credit') }}</div>
                    <div class="cust-kpi-value num tnum">{{ format_money($summary['total_credit']) }}</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="cust-kpi-label">{{ __('reports.trial_balance.kpis.status') }}</div>
                    <div class="cust-kpi-value">
                        @if ($summary['balanced'])
                            <span class="prod-badge prod-badge-positive">{{ __('reports.trial_balance.balanced') }}</span>
                        @else
                            <span class="prod-badge prod-badge-negative">{{ __('reports.trial_balance.unbalanced') }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-pad-0" x-data="dataTable({ rowsSelector: 'tbody > tr[data-dt-row]' })">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">{{ __('reports.trial_balance.list_title') }} (<span x-text="rowCount">{{ $rows->count() }}</span>)</div>
                <x-admin.dt-toolbar-actions />
            </div>
            <form method="GET" action="{{ route('admin.reports.trial-balance.index') }}" class="inv-filter">
                <label class="field inv-filter-date">
                    <span class="field-label">{{ __('reports.trial_balance.filter.as_of') }}</span>
                    <input type="text" name="as_of" value="{{ $asOf->toDateString() }}" class="pos-input js-datepicker" data-fp-submit-on-change="1">
                </label>
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('reports.filter.store') }}</span>
                    <select name="store_id" class="pos-input" x-data="enhancedSelect()" onchange="this.form.submit()">
                        <option value="">{{ __('reports.filter.store_all') }}</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" @selected($storeId === $store->id)>{{ $store->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field inv-filter-search">
                    <span class="field-label">&nbsp;</span>
                    <span class="inv-search">
                        <span class="inv-search-icon"><x-icon name="search" class="w-4 h-4" /></span>
                        <input type="search" x-model.debounce.150ms="search" data-no-live-search class="pos-input"
                               placeholder="{{ __('reports.trial_balance.filter.search') }}" @keydown.enter.prevent>
                    </span>
                </label>
                <x-admin.filter-reset route="admin.reports.trial-balance.index" />
            </form>

            @if ($rows->isEmpty())
                <div class="dt-empty">
                    <span class="dt-empty-icon"><x-icon name="calculator" class="w-5 h-5" /></span>
                    <div class="dt-empty-title">{{ __('reports.trial_balance.empty.title') }}</div>
                    <div class="dt-empty-sub">{{ __('reports.trial_balance.empty.sub') }}</div>
                </div>
            @else
                <div class="dt-scroll">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('reports.trial_balance.columns.code') }}</th>
                            <th>{{ __('reports.trial_balance.columns.account') }}</th>
                            <th class="num">{{ __('reports.trial_balance.columns.debit') }}</th>
                            <th class="num">{{ __('reports.trial_balance.columns.credit') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr data-dt-row data-dt-name="{{ $row['code'].' '.$row['name'] }}"
                                class="prod-row"
                                @click="window.location.href = {{ \Illuminate\Support\Js::from(route('admin.reports.general-ledger.index', ['account_id' => $row['account_id']])) }}">
                                <td class="mono fg-tertiary">{{ $row['code'] }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td class="num tnum">{{ bccomp($row['debit'], '0', 4) > 0 ? format_money($row['debit']) : '' }}</td>
                                <td class="num tnum">{{ bccomp($row['credit'], '0', 4) > 0 ? format_money($row['credit']) : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="dt-total-row font-semibold">
                            <td colspan="2">{{ __('reports.totals_row') }}</td>
                            <td class="num tnum">{{ format_money($summary['total_debit']) }}</td>
                            <td class="num tnum">{{ format_money($summary['total_credit']) }}</td>
                        </tr>
                    </tfoot>
                </table>
                </div>
                <x-admin.dt-pager />
            @endif
        </div>
    </div>
</x-admin-layout>
