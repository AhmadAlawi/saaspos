<x-admin-layout
    active="profit-and-loss"
    :title="__('reports.pnl.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('reports.nav.section'), 'href' => route('admin.reports.index')],
        ['label' => __('reports.pnl.title')],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('reports.pnl.title') }}</h1>
                <p class="page-sub">{{ __('reports.pnl.sub') }}</p>
            </div>
            <div class="dropdown" x-data="dropdown">
                <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="toggle()" :aria-expanded="open">
                    <x-icon name="download" class="w-4 h-4" />
                    {{ __('reports.actions.export') }}
                    <x-icon name="chevron" class="w-4 h-4" />
                </button>
                <div class="dropdown-panel" x-show="open" x-cloak @click.outside="close()" @keydown.escape.window="close()">
                    @foreach (['csv' => __('reports.actions.export_csv'), 'xlsx' => __('reports.actions.export_xlsx'), 'pdf' => __('reports.actions.export_pdf')] as $fmt => $label)
                        <a href="{{ route('admin.reports.profit-and-loss.export', array_filter(['format' => $fmt, 'period' => $period, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'store_id' => $storeId])) }}"
                           class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ $label }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <x-admin.report-filter-bar route="admin.reports.profit-and-loss.index"
            :period="$period" :from="$from" :to="$to" :store-id="$storeId" :stores="$stores" :searchable="false" />

        @php
            $grossPos = bccomp((string) $data['gross_profit'], '0', 4) >= 0;
            $netPos   = bccomp((string) $data['net_profit'], '0', 4) >= 0;
            $kpiCards = [
                ['label' => __('reports.pnl.total_income'), 'value' => format_money($data['income']['total'])],
                ['label' => __('reports.pnl.cogs'),         'value' => format_money($data['cogs']['total'])],
                ['label' => __('reports.pnl.gross_profit'), 'value' => format_money($data['gross_profit']),
                 'tone'  => $grossPos ? 'positive' : 'danger',
                 'sub'   => $data['gross_margin'] !== null ? __('reports.pnl.margin', ['pct' => $data['gross_margin']]) : null],
                ['label' => __('reports.pnl.net_profit'),   'value' => format_money($data['net_profit']),
                 'tone'  => $netPos ? 'positive' : 'danger',
                 'sub'   => $data['net_margin'] !== null ? __('reports.pnl.margin', ['pct' => $data['net_margin']]) : null],
            ];
        @endphp

        <div class="mt-4">
            <x-admin.summary-cards :cards="$kpiCards" />
        </div>

        <div class="card mt-4">
            <div class="card-body">
                <table class="stmt-table">
                    <tbody>
                        <x-admin.statement-section :label="__('reports.pnl.income')" :rows="$data['income']['rows']"
                            :total="$data['income']['total']" :total-label="__('reports.pnl.total_income')" />

                        <x-admin.statement-section :label="__('reports.pnl.cogs')" :rows="$data['cogs']['rows']"
                            :total="$data['cogs']['total']" :total-label="__('reports.pnl.total_cogs')" />

                        <tr class="stmt-total-row">
                            <td>
                                {{ __('reports.pnl.gross_profit') }}
                                @if ($data['gross_margin'] !== null)
                                    <span class="fg-tertiary font-normal text-xs ms-1">({{ $data['gross_margin'] }}%)</span>
                                @endif
                            </td>
                            <td class="stmt-num {{ $grossPos ? '' : 'stmt-neg' }}">{{ format_money($data['gross_profit']) }}</td>
                        </tr>

                        @if ($data['operating']['rows'])
                            <x-admin.statement-section :label="__('reports.pnl.operating')" :rows="$data['operating']['rows']"
                                :total="$data['operating']['total']" :total-label="__('reports.pnl.total_operating')" />
                        @endif

                        @if ($data['other']['rows'])
                            <x-admin.statement-section :label="__('reports.pnl.other')" :rows="$data['other']['rows']"
                                :total="$data['other']['total']" :total-label="__('reports.pnl.total_other')" />
                        @endif

                        <tr class="stmt-subtotal-row">
                            <td>{{ __('reports.pnl.total_expenses') }}</td>
                            <td class="stmt-num">{{ format_money($data['total_expenses']) }}</td>
                        </tr>

                        <tr class="stmt-total-row is-final">
                            <td>
                                {{ __('reports.pnl.net_profit') }}
                                @if ($data['net_margin'] !== null)
                                    <span class="fg-tertiary font-normal text-xs ms-1">({{ $data['net_margin'] }}%)</span>
                                @endif
                            </td>
                            <td class="stmt-num {{ $netPos ? '' : 'stmt-neg' }}">{{ format_money($data['net_profit']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-admin-layout>
