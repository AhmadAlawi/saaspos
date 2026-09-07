{{--
    Table-view rows for the Supplier payments list.

    Rendered inline by `admin.supplier-payments.index` (first page) and standalone
    by `SupplierPaymentController@rows` (JSON `html`) for every subsequent page /
    filter / search.

    Expects: $payments (iterable of PurchasePayment, with supplier/paymentMethod/
    purchase loaded).
--}}
@foreach ($payments as $p)
    <tr class="prod-row {{ $p->isVoided() ? 'is-inactive' : '' }}" data-dt-row
        data-dt-name="{{ $p->supplier->name ?? $p->reference ?? '' }}"
        data-dt-id="{{ $p->id }}"
        @click="window.location.href = {{ \Illuminate\Support\Js::from(route('admin.supplier-payments.show', $p)) }}">
        <td>{{ format_date($p->payment_date) }}</td>
        <td>{{ $p->supplier?->name ?: '—' }}</td>
        <td class="mono">
            @if ($p->purchase)
                {{ $p->purchase->number }}
            @elseif (! $p->isVoided())
                <span class="fg-tertiary">{{ __('supplier_payments.list.unallocated_credit') }}</span>
            @else
                —
            @endif
        </td>
        <td>{{ $p->paymentMethod?->name ?: '—' }}</td>
        <td class="mono fg-tertiary">{{ $p->reference ?: '—' }}</td>
        <td class="num tnum">
            {{ format_money($p->amount) }}
            @if ($p->isVoided())
                <span class="prod-badge prod-badge-muted ms-1">{{ __('supplier_payments.badges.voided') }}</span>
            @endif
        </td>
        <td>
            <x-admin.row-actions>
                <x-admin.row-action :href="route('admin.supplier-payments.show', $p)" icon="eye" :label="__('table.action.view')" />
                @can('void', $p)
                    @unless ($p->isVoided())
                        <div class="row-action-sep"></div>
                        <x-admin.row-action icon="x" variant="danger" :label="__('supplier_payments.void.action')"
                            @click="$store.confirm.show({
                                title:        {{ \Illuminate\Support\Js::from(__('supplier_payments.void.confirm_title')) }},
                                message:      {{ \Illuminate\Support\Js::from(__('supplier_payments.void.confirm_body')) }},
                                intent:       'danger',
                                confirmLabel: {{ \Illuminate\Support\Js::from(__('supplier_payments.void.action')) }},
                                cancelLabel:  {{ \Illuminate\Support\Js::from(__('supplier_payments.actions.discard')) }},
                                onConfirm:    () => $submitForm({{ \Illuminate\Support\Js::from(route('admin.supplier-payments.void', $p)) }}),
                            })" />
                    @endunless
                @endcan
            </x-admin.row-actions>
        </td>
    </tr>
@endforeach
