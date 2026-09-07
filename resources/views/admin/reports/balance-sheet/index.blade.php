<x-admin-layout
    active="balance-sheet"
    :title="__('reports.balance_sheet.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('reports.nav.section'), 'href' => route('admin.reports.index')],
        ['label' => __('reports.balance_sheet.title')],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('reports.balance_sheet.title') }}</h1>
                <p class="page-sub">{{ __('reports.balance_sheet.sub') }}</p>
            </div>
            <div class="dropdown" x-data="dropdown">
                <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="toggle()" :aria-expanded="open">
                    <x-icon name="download" class="w-4 h-4" />
                    {{ __('reports.actions.export') }}
                    <x-icon name="chevron" class="w-4 h-4" />
                </button>
                <div class="dropdown-panel" x-show="open" x-cloak @click.outside="close()" @keydown.escape.window="close()">
                    @foreach (['csv' => __('reports.actions.export_csv'), 'xlsx' => __('reports.actions.export_xlsx'), 'pdf' => __('reports.actions.export_pdf')] as $fmt => $label)
                        <a href="{{ route('admin.reports.balance-sheet.export', array_filter(['format' => $fmt, 'as_of' => $asOf->toDateString(), 'store_id' => $storeId])) }}"
                           class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ $label }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <form method="GET" action="{{ route('admin.reports.balance-sheet.index') }}" class="inv-filter mb-4">
            <label class="field inv-filter-date">
                <span class="field-label">{{ __('reports.balance_sheet.as_of') }}</span>
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
            <x-admin.filter-reset route="admin.reports.balance-sheet.index" />

            @if ($saveKey = \App\Support\ReportRegistry::keyForRoute('admin.reports.balance-sheet.index'))
                <x-admin.report-save-button :report-key="$saveKey" :can-share="auth()->user()?->hasPermission('reports.save_shared') ?? false" />
                @if (auth()->user()?->hasPermission('reports.schedule'))
                    <x-admin.report-schedule-button :report-key="$saveKey" />
                @endif
            @endif
        </form>

        @php
            $bsCards = [
                ['label' => __('reports.balance_sheet.total_assets'),      'value' => format_money($data['assets']['total'])],
                ['label' => __('reports.balance_sheet.total_liabilities'), 'value' => format_money($data['liabilities']['total'])],
                ['label' => __('reports.balance_sheet.total_equity'),      'value' => format_money($data['total_equity'])],
            ];
        @endphp

        <div class="mb-4">
            <x-admin.summary-cards :cards="$bsCards" />
        </div>

        @unless ($data['balanced'])
            <div class="alert alert-danger mb-4">{{ __('reports.balance_sheet.out_of_balance') }}</div>
        @endunless

        <div class="card">
            <div class="card-body">
                <table class="stmt-table">
                    <tbody>
                        <x-admin.statement-section :label="__('reports.balance_sheet.assets')" :rows="$data['assets']['rows']"
                            :total="$data['assets']['total']" :total-label="__('reports.balance_sheet.total_assets')" />

                        <x-admin.statement-section :label="__('reports.balance_sheet.liabilities')" :rows="$data['liabilities']['rows']"
                            :total="$data['liabilities']['total']" :total-label="__('reports.balance_sheet.total_liabilities')" />

                        {{-- Equity carries the equity accounts plus current earnings. --}}
                        <tr class="stmt-section-row"><td colspan="2">{{ __('reports.balance_sheet.equity') }}</td></tr>
                        @foreach ($data['equity']['rows'] as $r)
                            <tr class="stmt-acct-row">
                                <td><span class="stmt-code mono">{{ $r['code'] }}</span>{{ $r['name'] }}</td>
                                <td class="stmt-num">{{ format_money($r['amount']) }}</td>
                            </tr>
                        @endforeach
                        <tr class="stmt-acct-row">
                            <td>{{ __('reports.balance_sheet.current_earnings') }}</td>
                            <td class="stmt-num">{{ format_money($data['net_income']) }}</td>
                        </tr>
                        <tr class="stmt-subtotal-row">
                            <td>{{ __('reports.balance_sheet.total_equity') }}</td>
                            <td class="stmt-num">{{ format_money($data['total_equity']) }}</td>
                        </tr>

                        <tr class="stmt-total-row is-final">
                            <td>{{ __('reports.balance_sheet.total_liabilities_equity') }}</td>
                            <td class="stmt-num">{{ format_money($data['total_liabilities_equity']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-admin-layout>
