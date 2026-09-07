<x-admin-layout
    active="customers"
    :title="__('customer_statement.title', ['name' => $customer->name])"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('customers.title'), 'href' => route('admin.customers.index')],
        ['label' => $customer->name, 'href' => route('admin.customers.show', $customer)],
        ['label' => __('customer_statement.crumb')],
    ]">

    <div class="page-wide" x-data="{ emailing: false, emailTo: @js($customer->email ?? '') }">
        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ route('admin.customers.show', $customer) }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('customers.title') }}">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title">{{ __('customer_statement.title', ['name' => $customer->name]) }}</h1>
                    <p class="page-sub">{{ __('customer_statement.sub', ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]) }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.customers.statement.print', array_filter(['customer' => $customer->id, 'from' => $from->toDateString(), 'to' => $to->toDateString()])) }}"
                   target="_blank" class="pos-btn pos-btn-sm pos-btn-ghost">
                    <x-icon name="printer" class="w-4 h-4" />
                    {{ __('customer_statement.actions.print') }}
                </a>
                <button type="button" class="pos-btn pos-btn-sm pos-btn-primary"
                        @click="$store.confirm.show({
                            title:        {{ \Illuminate\Support\Js::from(__('customer_statement.confirm_email.title', ['name' => $customer->name])) }},
                            message:      {{ \Illuminate\Support\Js::from(__('customer_statement.confirm_email.message', ['email' => $customer->email ? $customer->display_email : '—'])) }},
                            intent:       'primary',
                            confirmLabel: {{ \Illuminate\Support\Js::from(__('customer_statement.actions.email')) }},
                            cancelLabel:  {{ \Illuminate\Support\Js::from(__('customer_statement.actions.cancel')) }},
                            onConfirm:    () => $submitForm(
                                {{ \Illuminate\Support\Js::from(route('admin.customers.statement.email', $customer)) }},
                                { from: {{ \Illuminate\Support\Js::from($from->toDateString()) }}, to: {{ \Illuminate\Support\Js::from($to->toDateString()) }} },
                            ),
                        })"
                        :disabled="!@js((bool) $customer->email)"
                        :title="@js((bool) $customer->email ? '' : __('customer_statement.errors.no_email'))">
                    <x-icon name="mail" class="w-4 h-4" />
                    {{ __('customer_statement.actions.email') }}
                </button>
            </div>
        </div>

        {{-- KPI strip: opening / debits / credits / closing --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <div class="card">
                <div class="card-body">
                    <div class="cust-kpi-label">{{ __('customer_statement.kpis.opening') }}</div>
                    <div class="cust-kpi-value num tnum">{{ format_money($opening_balance) }}</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="cust-kpi-label">{{ __('customer_statement.kpis.debits') }}</div>
                    <div class="cust-kpi-value num tnum">{{ format_money($debits_total) }}</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="cust-kpi-label">{{ __('customer_statement.kpis.credits') }}</div>
                    <div class="cust-kpi-value num tnum">{{ format_money($credits_total) }}</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="cust-kpi-label">{{ __('customer_statement.kpis.closing') }}</div>
                    <div class="cust-kpi-value num tnum @if (bccomp($closing_balance, '0', 4) > 0) text-amber-600 dark:text-amber-400 font-semibold @endif">
                        {{ format_money($closing_balance) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-pad-0"
             x-data="dataTable({ rowsSelector: 'tbody > tr[data-dt-row]' })">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">{{ __('customer_statement.list_title') }} (<span x-text="rowCount">{{ $events->count() }}</span>)</div>
                <x-admin.dt-toolbar-actions />
            </div>
            <form method="GET" action="{{ route('admin.customers.statement.show', $customer) }}" class="inv-filter">
                <label class="field inv-filter-date">
                    <span class="field-label">{{ __('customer_statement.filter.from') }}</span>
                    <input type="text" name="from" value="{{ $from->toDateString() }}"
                           class="pos-input js-datepicker" data-fp-submit-on-change="1">
                </label>
                <label class="field inv-filter-date">
                    <span class="field-label">{{ __('customer_statement.filter.to') }}</span>
                    <input type="text" name="to" value="{{ $to->toDateString() }}"
                           class="pos-input js-datepicker" data-fp-submit-on-change="1">
                </label>
                {{-- Custom inline reset — filter-reset component takes a
                     route NAME only; this route needs the customer path
                     param so we render the anchor directly with the
                     same look as `<x-admin.filter-reset>`. --}}
                <a href="{{ route('admin.customers.statement.show', $customer) }}"
                   class="inv-filter-reset"
                   title="{{ __('table.filter_reset') }}">
                    <x-icon name="x" class="w-3.5 h-3.5" />
                    <span>{{ __('table.filter_reset') }}</span>
                </a>
            </form>

            @if ($events->isEmpty())
                <div class="dt-empty">
                    <span class="dt-empty-icon"><x-icon name="check" class="w-5 h-5" /></span>
                    <div class="dt-empty-title">{{ __('customer_statement.empty.title') }}</div>
                    <div class="dt-empty-sub">{{ __('customer_statement.empty.sub') }}</div>
                </div>
            @else
                <div class="dt-scroll">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('customer_statement.columns.date') }}</th>
                            <th>{{ __('customer_statement.columns.type') }}</th>
                            <th>{{ __('customer_statement.columns.reference') }}</th>
                            <th class="num">{{ __('customer_statement.columns.debit') }}</th>
                            <th class="num">{{ __('customer_statement.columns.credit') }}</th>
                            <th class="num">{{ __('customer_statement.columns.running') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Opening balance row — always first, even when zero. --}}
                        <tr class="prod-row" data-dt-row data-dt-name="opening">
                            <td>{{ $from->format('Y-m-d') }}</td>
                            <td>
                                <span class="prod-badge prod-badge-muted">{{ __('customer_statement.types.opening') }}</span>
                            </td>
                            <td class="fg-tertiary">—</td>
                            <td class="num tnum fg-tertiary">—</td>
                            <td class="num tnum fg-tertiary">—</td>
                            <td class="num tnum font-semibold">{{ format_money($opening_balance) }}</td>
                        </tr>
                        @foreach ($events as $event)
                            <tr class="prod-row" data-dt-row data-dt-name="{{ $event['reference'] }}"
                                @if (! empty($event['sale_id']))
                                    @click="window.location.href = {{ \Illuminate\Support\Js::from(route('admin.sales.show', $event['sale_id'])) }}"
                                @endif>
                                <td>{{ $event['date'] }}</td>
                                <td>
                                    @php
                                        $badge = match ($event['type']) {
                                            'sale'    => 'prod-badge-muted',
                                            'payment' => 'prod-badge-positive',
                                            'refund'  => 'prod-badge-warning',
                                            'void'    => 'prod-badge-danger',
                                            default   => 'prod-badge-muted',
                                        };
                                    @endphp
                                    <span class="prod-badge {{ $badge }}">{{ __('customer_statement.types.'.$event['type']) }}</span>
                                </td>
                                <td class="mono">{{ $event['reference'] }}</td>
                                <td class="num tnum">{{ bccomp($event['debit'], '0', 4) > 0 ? format_money($event['debit']) : '—' }}</td>
                                <td class="num tnum">{{ bccomp($event['credit'], '0', 4) > 0 ? format_money($event['credit']) : '—' }}</td>
                                <td class="num tnum font-semibold">{{ format_money($event['running_balance']) }}</td>
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
