{{--
    Table-view rows for the Purchase returns list.

    Rendered inline by `admin.purchase-returns.index` (first page) and standalone
    by `PurchaseReturnController@rows` (JSON `html`) for every subsequent page /
    filter / search.

    Expects: $returns (iterable of PurchaseReturn, with purchase/supplier loaded).
--}}
@foreach ($returns as $return)
    @php
        $badge = $return->status === 'posted' ? 'prod-badge-positive' : 'prod-badge-muted';
    @endphp
    <tr data-dt-row class="cursor-pointer hover:bg-hover"
        onclick="window.location='{{ route('admin.purchase-returns.show', $return) }}'">
        <td class="mono font-medium">{{ $return->number }}</td>
        <td>
            <a href="{{ route('admin.purchases.show', $return->purchase_id) }}"
               class="link mono"
               onclick="event.stopPropagation()">
                {{ $return->purchase?->number }}
            </a>
        </td>
        <td>{{ $return->supplier?->name ?: '—' }}</td>
        <td>{{ format_date($return->return_date) }}</td>
        <td class="num tnum">{{ format_money($return->grand_total) }}</td>
        <td>
            <span class="prod-badge {{ $badge }}">
                {{ __('purchases.returns.status.'.$return->status) }}
            </span>
        </td>
        <td>
            <x-admin.row-actions>
                <x-admin.row-action :href="route('admin.purchase-returns.show', $return)" icon="eye" :label="__('table.action.view')" />
                <x-admin.row-action :href="route('admin.purchases.show', $return->purchase_id)" icon="cart" :label="__('purchases.returns.actions.view_purchase')" />
            </x-admin.row-actions>
        </td>
    </tr>
@endforeach
