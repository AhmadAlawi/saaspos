{{--
    All kiosk-order rows — the full history, whatever became of each order.

    Rendered inline by `admin.kiosk-orders.index` (first page) and standalone by
    `KioskOrderController@allRows` (JSON `html`) for every subsequent page.

    Unlike the pending queue this is READ-ONLY: an order here may already be
    paid, collected or voided, so the row links to the sale detail rather than
    carrying actions. Live work belongs on the Pending / Collect tabs.

    Expects: $allOrders (iterable of Sale, customer loaded, items_count present).
--}}
@foreach ($allOrders as $order)
    <tr class="prod-row" data-dt-row
        data-dt-name="{{ trim(($order->number ?? '').' '.($order->pickup_code ?? '')) }}"
        data-dt-id="{{ $order->id }}"
        @click="window.location.href = {{ \Illuminate\Support\Js::from(route('admin.sales.show', $order)) }}">
        <td>
            <div class="font-semibold tnum">{{ $order->pickup_code ?: '—' }}</div>
            <div class="text-[11.5px] fg-tertiary mono">{{ $order->number }}</div>
        </td>
        <td class="fg-secondary">{{ format_datetime($order->sale_datetime) }}</td>
        <td>{{ $order->customer?->name ?? __('kiosk-orders.walk_in') }}</td>
        <td>
            @php
                // Mirrors the badge tones on the sales list so a kiosk order
                // reads the same in both places.
                $badge = match ($order->status) {
                    'completed'           => 'positive',
                    'held', 'placed'      => 'info',
                    'voided'              => 'muted',
                    'partially_refunded',
                    'refunded'            => 'warning',
                    default               => 'muted',
                };
            @endphp
            <span class="prod-badge prod-badge-{{ $badge }}">
                {{ __('sales.statuses.'.$order->status) }}
            </span>
            {{-- Paid but still behind the counter — the one open state this
                 tab can show that `status` alone can't express. --}}
            @if ($order->status === \App\Models\Sale::STATUS_COMPLETED && $order->pickup_code && ! $order->kiosk_collected_at)
                <span class="prod-badge prod-badge-warning ms-1">{{ __('kiosk-orders.all.uncollected') }}</span>
            @endif
        </td>
        <td class="num tnum fg-tertiary">{{ $order->items_count }}</td>
        <td class="num tnum font-semibold">{{ format_money($order->grand_total) }}</td>
    </tr>
@endforeach
