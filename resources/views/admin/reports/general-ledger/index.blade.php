<x-admin-layout
    active="general-ledger"
    :title="__('reports.general_ledger.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('reports.nav.section'), 'href' => route('admin.reports.index')],
        ['label' => __('reports.general_ledger.title')],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('reports.general_ledger.title') }}</h1>
                <p class="page-sub">{{ __('reports.general_ledger.sub') }}</p>
            </div>
            @if ($result['account'])
                <div class="dropdown" x-data="dropdown">
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="toggle()" :aria-expanded="open">
                        <x-icon name="download" class="w-4 h-4" />
                        {{ __('reports.actions.export') }}
                        <x-icon name="chevron" class="w-4 h-4" />
                    </button>
                    <div class="dropdown-panel" x-show="open" x-cloak @click.outside="close()" @keydown.escape.window="close()">
                        @foreach (['csv' => __('reports.actions.export_csv'), 'xlsx' => __('reports.actions.export_xlsx'), 'pdf' => __('reports.actions.export_pdf')] as $fmt => $label)
                            <a href="{{ route('admin.reports.general-ledger.export', array_filter(['format' => $fmt, 'account_id' => $accountId, 'store_id' => $storeId, 'from' => $from->toDateString(), 'to' => $to->toDateString()])) }}"
                               class="dropdown-item" @click="close()">
                                <span class="dropdown-item-label">{{ $label }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="card card-pad-0" x-data="dataTable({ rowsSelector: 'tbody > tr[data-dt-row]' })">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">
                    @if ($result['account'])
                        <span class="mono">{{ $result['account']->code }}</span> {{ $result['account']->name }}
                    @else
                        {{ __('reports.general_ledger.list_title') }}
                    @endif
                </div>
                <x-admin.dt-toolbar-actions />
            </div>
            <form method="GET" action="{{ route('admin.reports.general-ledger.index') }}" class="inv-filter">
                <label class="field inv-filter-account">
                    <span class="field-label">{{ __('reports.general_ledger.filter.account') }}</span>
                    <select name="account_id" class="pos-input" x-data="enhancedSelect()" onchange="this.form.submit()">
                        <option value="">{{ __('reports.general_ledger.filter.account_placeholder') }}</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}" @selected($accountId === $account->id)>{{ $account->code }} — {{ $account->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field inv-filter-date">
                    <span class="field-label">{{ __('reports.filter.from') }}</span>
                    <input type="text" name="from" value="{{ $from->toDateString() }}" class="pos-input js-datepicker" data-fp-submit-on-change="1">
                </label>
                <label class="field inv-filter-date">
                    <span class="field-label">{{ __('reports.filter.to') }}</span>
                    <input type="text" name="to" value="{{ $to->toDateString() }}" class="pos-input js-datepicker" data-fp-submit-on-change="1">
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
                <x-admin.filter-reset route="admin.reports.general-ledger.index" />
            </form>

            @if ($result['account'])
                @php
                    $glDebits = '0'; $glCredits = '0';
                    foreach ($result['rows'] as $glRow) {
                        $glDebits  = bcadd($glDebits, (string) $glRow['debit'], 4);
                        $glCredits = bcadd($glCredits, (string) $glRow['credit'], 4);
                    }
                    $glCards = [
                        ['label' => __('reports.general_ledger.opening_balance'), 'value' => format_money($result['opening'])],
                        ['label' => __('reports.general_ledger.total_debits'),    'value' => format_money($glDebits)],
                        ['label' => __('reports.general_ledger.total_credits'),   'value' => format_money($glCredits)],
                        ['label' => __('reports.general_ledger.closing_balance'), 'value' => format_money($result['closing'])],
                    ];
                @endphp
                <div class="px-4 pt-4">
                    <x-admin.summary-cards :cards="$glCards" />
                </div>
            @endif

            @if (! $result['account'])
                <div class="dt-empty">
                    <span class="dt-empty-icon"><x-icon name="list" class="w-5 h-5" /></span>
                    <div class="dt-empty-title">{{ __('reports.general_ledger.pick.title') }}</div>
                    <div class="dt-empty-sub">{{ __('reports.general_ledger.pick.sub') }}</div>
                </div>
            @else
                <div class="dt-scroll">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('reports.general_ledger.columns.date') }}</th>
                            <th>{{ __('reports.general_ledger.columns.entry') }}</th>
                            <th>{{ __('reports.general_ledger.columns.source') }}</th>
                            <th>{{ __('reports.general_ledger.columns.description') }}</th>
                            <th class="num">{{ __('reports.general_ledger.columns.debit') }}</th>
                            <th class="num">{{ __('reports.general_ledger.columns.credit') }}</th>
                            <th class="num">{{ __('reports.general_ledger.columns.balance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="fg-tertiary">
                            <td colspan="4">{{ __('reports.general_ledger.opening_balance') }}</td>
                            <td class="num"></td>
                            <td class="num"></td>
                            <td class="num tnum">{{ format_money($result['opening']) }}</td>
                        </tr>
                        @foreach ($result['rows'] as $row)
                            <tr data-dt-row data-dt-name="{{ $row['number'].' '.$row['description'] }}">
                                <td class="fg-tertiary tnum">{{ $row['date'] }}</td>
                                <td class="mono">{{ $row['number'] }}</td>
                                <td><x-accounting.source-badge :source="$row['source']" /></td>
                                <td>{{ $row['description'] }}</td>
                                <td class="num tnum">{{ bccomp($row['debit'], '0', 4) > 0 ? format_money($row['debit']) : '' }}</td>
                                <td class="num tnum">{{ bccomp($row['credit'], '0', 4) > 0 ? format_money($row['credit']) : '' }}</td>
                                <td class="num tnum">{{ format_money($row['balance']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="dt-total-row font-semibold">
                            <td colspan="6">{{ __('reports.general_ledger.closing_balance') }}</td>
                            <td class="num tnum">{{ format_money($result['closing']) }}</td>
                        </tr>
                    </tfoot>
                </table>
                </div>
                <x-admin.dt-pager />
            @endif
        </div>
    </div>
</x-admin-layout>
