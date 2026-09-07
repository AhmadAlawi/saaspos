{{--
    Pending kiosk-order rows (placed, awaiting counter payment).

    Rendered inline by `admin.kiosk-orders.index` (first page) and standalone by
    `KioskOrderController@rows` (JSON `html`) for every subsequent page. Runs
    inside the `kioskOrdersPage()` scope, so `openPay(...)` resolves.

    Each row inlines everything the take-payment panel needs, so opening it is
    instant — no second round-trip after paging.

    Expects: $orders (iterable of Sale, with items/customer/kioskPaymentClaimMethod
    loaded and items_count present).
--}}
@php
    // The take-payment payload, keyed by order id — built here so the rows()
    // fragment is self-contained (the inline first page and every paged fetch
    // produce identical markup).
    $ordersForJs = $orders->mapWithKeys(fn ($o) => [$o->id => [
        'id'       => $o->id,
        'code'     => $o->pickup_code,
        'note'     => $o->kiosk_note,
        'customer' => $o->customer?->name,
        'payUrl'   => route('admin.kiosk-orders.pay', $o),
        'claim'    => $o->kioskPaymentClaimMethod ? [
            'method'    => $o->kioskPaymentClaimMethod->name,
            'reference' => $o->upiClaimReference(),
            'amount'    => format_money((float) $o->grand_total),
            'at'        => format_datetime($o->placed_at),
        ] : null,
        'lines'    => $o->items->map(fn ($i) => [
            'id'           => $i->id,
            'productId'    => $i->product_id,
            'name'         => $i->product_name_snapshot,
            'origQty'      => (float) $i->quantity,
            'unitPrice'    => (float) $i->unit_price,
            'lineSubtotal' => (float) $i->line_subtotal,
            'taxAmount'    => (float) $i->tax_amount,
        ])->values(),
    ]]);
@endphp
@foreach ($orders as $order)
    <tr data-dt-row data-order-id="{{ $order->id }}">
        <td>
            <div class="font-semibold tnum">{{ $order->pickup_code }}</div>
            <div class="text-[11.5px] fg-tertiary mono">{{ $order->number }}</div>
            {{-- The shopper scanned the kiosk's UPI QR and says they paid.
                 UNVERIFIED — a static UPI QR has no callback. --}}
            @if ($order->kioskPaymentClaimMethod)
                <span class="prod-badge prod-badge-warning mt-1"
                      title="{{ __('kiosk-orders.claim_hint') }}">
                    {{ __('kiosk-orders.claim_badge', ['method' => $order->kioskPaymentClaimMethod->name]) }}
                </span>
                @if ($ref = $order->upiClaimReference())
                    <div class="text-[11.5px] fg-tertiary mt-0.5">
                        {{ __('kiosk-orders.claim_ref') }}
                        <span class="mono">{{ $ref }}</span>
                    </div>
                @endif
            @endif
            @if ($order->kiosk_note)
                <div class="text-[11.5px] fg-secondary mt-0.5">“{{ $order->kiosk_note }}”</div>
            @endif
        </td>
        <td class="fg-secondary">{{ optional($order->placed_at)->diffForHumans() }}</td>
        <td>{{ $order->customer?->name ?? __('kiosk-orders.walk_in') }}</td>
        <td class="num tnum fg-tertiary">{{ $order->items_count }}</td>
        <td class="num tnum font-semibold">{{ format_money($order->grand_total) }}</td>
        <td class="dt-actions-col">
            <div class="flex items-center justify-end gap-2">
                <button type="button" class="pos-btn pos-btn-sm pos-btn-primary"
                        @click="openPay({{ \Illuminate\Support\Js::from($ordersForJs[$order->id]) }})">
                    <x-icon name="cash" class="w-4 h-4" />
                    {{ __('kiosk-orders.actions.pay') }}
                </button>

                <form method="POST" action="{{ route('admin.kiosk-orders.reject', $order) }}"
                      @submit.prevent="$store.confirm.show({
                          title: {{ \Illuminate\Support\Js::from(__('kiosk-orders.confirm.reject_title', ['code' => $order->pickup_code])) }},
                          message: {{ \Illuminate\Support\Js::from(__('kiosk-orders.confirm.reject_body')) }},
                          intent: 'danger',
                          confirmLabel: {{ \Illuminate\Support\Js::from(__('kiosk-orders.actions.reject')) }},
                          onConfirm: () => $el.submit(),
                      })">
                    @csrf
                    <button type="submit" class="pos-btn pos-btn-sm pos-btn-danger">
                        {{ __('kiosk-orders.actions.reject') }}
                    </button>
                </form>
            </div>
        </td>
    </tr>
@endforeach
