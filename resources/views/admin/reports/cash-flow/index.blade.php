<x-admin-layout
    active="cash-flow"
    :title="__('reports.cash_flow.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('reports.nav.section'), 'href' => route('admin.reports.index')],
        ['label' => __('reports.cash_flow.title')],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('reports.cash_flow.title') }}</h1>
                <p class="page-sub">{{ __('reports.cash_flow.sub') }}</p>
            </div>
            <div class="dropdown" x-data="dropdown">
                <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="toggle()" :aria-expanded="open">
                    <x-icon name="download" class="w-4 h-4" />
                    {{ __('reports.actions.export') }}
                    <x-icon name="chevron" class="w-4 h-4" />
                </button>
                <div class="dropdown-panel" x-show="open" x-cloak @click.outside="close()" @keydown.escape.window="close()">
                    @foreach (['csv' => __('reports.actions.export_csv'), 'xlsx' => __('reports.actions.export_xlsx'), 'pdf' => __('reports.actions.export_pdf')] as $fmt => $label)
                        <a href="{{ route('admin.reports.cash-flow.export', array_filter(['format' => $fmt, 'period' => $period, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'store_id' => $storeId])) }}"
                           class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ $label }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <x-admin.report-filter-bar route="admin.reports.cash-flow.index"
            :period="$period" :from="$from" :to="$to" :store-id="$storeId" :stores="$stores" :searchable="false" />

        @php
            $cfCards = [
                ['label' => __('reports.cash_flow.net_change'), 'value' => format_money($data['net_change']),
                 'tone'  => bccomp((string) $data['net_change'], '0', 4) >= 0 ? 'positive' : 'danger'],
                ['label' => __('reports.cash_flow.operating'), 'value' => format_money($data['operating']['total'])],
                ['label' => __('reports.cash_flow.investing'), 'value' => format_money($data['investing']['total'])],
                ['label' => __('reports.cash_flow.financing'), 'value' => format_money($data['financing']['total'])],
            ];
        @endphp

        <div class="mt-4">
            <x-admin.summary-cards :cards="$cfCards" />
        </div>

        <div class="card mt-4">
            <div class="card-body">
                <table class="stmt-table">
                    <tbody>
                        @foreach ([
                            'operating' => [__('reports.cash_flow.operating'), __('reports.cash_flow.net_operating')],
                            'investing' => [__('reports.cash_flow.investing'), __('reports.cash_flow.net_investing')],
                            'financing' => [__('reports.cash_flow.financing'), __('reports.cash_flow.net_financing')],
                        ] as $key => [$sectionLabel, $netLabel])
                            <tr class="stmt-section-row"><td colspan="2">{{ $sectionLabel }}</td></tr>
                            @forelse ($data[$key]['rows'] as $row)
                                <tr class="stmt-acct-row">
                                    <td>{{ $row['label'] }}</td>
                                    <td class="stmt-num">{{ format_money($row['amount']) }}</td>
                                </tr>
                            @empty
                                <tr class="stmt-acct-row"><td class="fg-tertiary">{{ __('reports.cash_flow.none') }}</td><td class="stmt-num"></td></tr>
                            @endforelse
                            <tr class="stmt-subtotal-row">
                                <td>{{ $netLabel }}</td>
                                <td class="stmt-num">{{ format_money($data[$key]['total']) }}</td>
                            </tr>
                        @endforeach

                        <tr class="stmt-total-row is-final">
                            <td>{{ __('reports.cash_flow.net_change') }}</td>
                            <td class="stmt-num">{{ format_money($data['net_change']) }}</td>
                        </tr>
                        <tr class="stmt-acct-row">
                            <td>{{ __('reports.cash_flow.opening') }}</td>
                            <td class="stmt-num">{{ format_money($data['opening']) }}</td>
                        </tr>
                        <tr class="stmt-subtotal-row">
                            <td>{{ __('reports.cash_flow.closing') }}</td>
                            <td class="stmt-num">{{ format_money($data['closing']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-admin-layout>
