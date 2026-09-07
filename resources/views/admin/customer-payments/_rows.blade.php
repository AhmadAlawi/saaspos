{{--
    Table-view rows for the Customer payments list.

    Rendered inline by `admin.customer-payments.index` (first page) and standalone
    by `CustomerPaymentController@rows` (JSON `html`) for every subsequent page /
    filter.

    Expects: $payments (iterable of SalePayment, with customer/paymentMethod/sale
    loaded).
--}}
@foreach ($payments as $p)
    <tr class="prod-row" data-dt-row
        data-dt-name="{{ $p->customer->name ?? $p->reference ?? '' }}"
        data-dt-id="{{ $p->id }}"
        @click="window.location.href = {{ \Illuminate\Support\Js::from(route('admin.customer-payments.show', $p)) }}">
        <td>{{ format_date($p->paid_at) }}</td>
        <td>{{ $p->customer?->name ?: '—' }}</td>
        <td class="mono">{{ $p->sale?->number ?: __('customer_payments.unallocated_credit') }}</td>
        <td>{{ $p->paymentMethod?->name ?: '—' }}</td>
        <td class="mono">{{ $p->reference ?: '—' }}</td>
        <td class="num tnum">{{ format_money($p->amount) }}</td>
    </tr>
@endforeach
