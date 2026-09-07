{{--
    Table-view rows for the Sales list.

    Rendered inline by `admin.sales.index` (first page) and standalone by
    `SaleController@rows` (JSON `html`) for every subsequent page / filter /
    search. Read-only rows (row links to the sale detail) plus the view /
    print / refund / void action menu.

    Expects: $sales (iterable of Sale, with store/customer/cashier/payments/
    returns loaded).
--}}
@foreach ($sales as $sale)
    @php
        // Payment method summary — first row's method name plus "+N" when the
        // sale was split-tendered. Held / pure-credit sales have no payments → "—".
        $methodNames = $sale->payments
            ->map(fn ($p) => $p->paymentMethod?->name)
            ->filter()
            ->unique()
            ->values();
        $methodLabel = $methodNames->isEmpty()
            ? '—'
            : ($methodNames->count() === 1
                ? $methodNames->first()
                : $methodNames->first().' +'.($methodNames->count() - 1));
    @endphp
    <tr class="prod-row" data-dt-row
        data-dt-name="{{ trim(($sale->number ?? '').' '.($sale->pickup_code ?? '')) }}"
        data-dt-id="{{ $sale->id }}"
        @click="window.location.href = {{ \Illuminate\Support\Js::from(route('admin.sales.show', $sale)) }}">
        <td class="mono">
            {{ $sale->number }}
            @if ($sale->pickup_code)
                <div class="text-[11.5px] fg-tertiary tnum">{{ $sale->pickup_code }}</div>
            @endif
        </td>
        <td>{{ format_datetime($sale->sale_datetime) }}</td>
        <td>{{ $sale->store?->name ?: '—' }}</td>
        <td>{{ $sale->customer?->name ?: __('sales.walk_in') }}</td>
        <td>{{ $sale->cashier?->name ?: '—' }}</td>
        <td>{{ $methodLabel }}</td>
        <td>
            @php
                $badge = match ($sale->status) {
                    'completed'           => 'positive',
                    'held'                => 'info',
                    'placed'              => 'info',
                    'voided'              => 'muted',
                    'partially_refunded',
                    'refunded'            => 'warning',
                    default               => 'muted',
                };
            @endphp
            <span class="prod-badge prod-badge-{{ $badge }}">
                {{ __('sales.statuses.'.$sale->status) }}
            </span>
            @php
                $gwRev = $sale->returns->contains('gateway_refund_status', \App\Models\SaleReturn::GATEWAY_REFUND_SUCCEEDED)
                    ? 'succeeded'
                    : ($sale->returns->contains('gateway_refund_status', \App\Models\SaleReturn::GATEWAY_REFUND_FAILED) ? 'failed' : null);
            @endphp
            @if ($gwRev === 'succeeded')
                <span class="prod-badge prod-badge-positive" title="{{ __('sales.gateway_refund.reversed') }}">{{ __('sales.gateway_refund.reversed_short') }}</span>
            @elseif ($gwRev === 'failed')
                <span class="prod-badge prod-badge-negative" title="{{ __('sales.gateway_refund.failed') }}">{{ __('sales.gateway_refund.failed_short') }}</span>
            @endif
        </td>
        <td class="num tnum">{{ format_money($sale->grand_total) }}</td>
        <td>
            @php
                $shiftOpen = $sale->shift_id ? (\App\Models\Shift::query()->find($sale->shift_id)?->isOpen() ?? false) : true;
                $voidable  = $sale->status === \App\Models\Sale::STATUS_COMPLETED
                    && $sale->returns->isEmpty()
                    && $shiftOpen;
            @endphp
            <x-admin.row-actions>
                <x-admin.row-action :href="route('admin.sales.show', $sale)" icon="eye" :label="__('table.action.view')" />
                <x-admin.row-action :href="route('admin.sales.receipt', $sale)" target="_blank" icon="receipt" :label="__('sales.actions.print')" />

                @can ('refund', $sale)
                    @if (in_array($sale->status, [\App\Models\Sale::STATUS_COMPLETED, \App\Models\Sale::STATUS_PARTIALLY_REFUNDED], true))
                        <x-admin.row-action :href="route('admin.sales.refund.form', $sale)" icon="refund" :label="__('sales.actions.refund')" />
                    @endif
                @endcan

                @can ('void', $sale)
                    @if ($voidable)
                        <div class="row-action-sep"></div>
                        <x-admin.row-action icon="x" variant="danger" :label="__('sales.actions.void')"
                            @click="$store.confirm.show({
                                title:        {{ \Illuminate\Support\Js::from(__('sales.confirm_void.title', ['number' => $sale->number])) }},
                                message:      {{ \Illuminate\Support\Js::from(__('sales.confirm_void.message')) }},
                                intent:       'danger',
                                confirmLabel: {{ \Illuminate\Support\Js::from(__('sales.confirm_void.confirm')) }},
                                cancelLabel:  {{ \Illuminate\Support\Js::from(__('sales.confirm_void.cancel')) }},
                                onConfirm:    () => $submitForm({{ \Illuminate\Support\Js::from(route('admin.sales.void', $sale)) }}),
                            })" />
                    @endif
                @endcan
            </x-admin.row-actions>
        </td>
    </tr>
@endforeach
