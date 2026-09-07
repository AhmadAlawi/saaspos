{{--
    Table-view rows for the Purchases list.

    Rendered inline by `admin.purchases.index` (first page) and standalone by
    `PurchaseController@rows` (JSON `html`) for every subsequent page / filter /
    search. Read-only rows (row links to the purchase detail) plus the action menu.

    Expects: $purchases (iterable of Purchase, with supplier/store loaded and
    attachments_count present).
--}}
@foreach ($purchases as $p)
    @php
        $statusBadge = match ($p->status) {
            'draft'           => 'prod-badge-muted',
            'submitted'       => 'prod-badge-muted',
            'received'        => 'prod-badge-positive',
            'partially_paid'  => 'prod-badge-positive',
            'paid'            => 'prod-badge-positive',
            'cancelled'       => 'prod-badge-muted',
            default           => 'prod-badge-muted',
        };
    @endphp
    <tr class="prod-row" data-dt-row
        data-dt-name="{{ $p->number ?? '' }}"
        data-dt-id="{{ $p->id }}"
        @click="window.location.href = {{ \Illuminate\Support\Js::from(route('admin.purchases.show', $p)) }}">
        <td class="mono">
            {{ $p->number }}
            @if (($p->attachments_count ?? 0) > 0)
                <span class="fg-tertiary inline-flex align-middle ms-1" title="{{ __('purchases.sections.attachment') }}">
                    <x-icon name="note" class="w-3.5 h-3.5" />
                </span>
            @endif
        </td>
        <td>{{ $p->supplier?->name ?: '—' }}</td>
        <td>{{ $p->store?->name ?: '—' }}</td>
        <td>{{ format_date($p->purchase_date) }}</td>
        <td>{{ $p->due_date ? format_date($p->due_date) : '—' }}</td>
        <td class="num tnum">{{ format_money($p->grand_total, null, $p->currency_code) }}</td>
        <td class="num tnum">{{ format_money($p->balance_due, null, $p->currency_code) }}</td>
        <td>
            <span class="prod-badge {{ $statusBadge }}">{{ __('purchases.status.'.$p->status) }}</span>
        </td>
        <td>
            <x-admin.row-actions>
                @can('view', $p)
                    <x-admin.row-action :href="route('admin.purchases.show', $p)" icon="eye" :label="__('purchases.actions.view')" />
                @endcan

                {{-- Post-receive actions — mirror the purchase show page CTAs. --}}
                @if (in_array($p->status, ['received', 'partially_paid', 'paid'], true))
                    @can('update', $p)
                        <x-admin.row-action :href="route('admin.purchase-returns.create', $p)" icon="refund" :label="__('purchases.actions.create_return')" />
                    @endcan
                    @can('create', App\Models\PurchasePayment::class)
                        <x-admin.row-action :href="route('admin.supplier-payments.create', [
                                'supplier_id' => $p->supplier_id,
                                'store_id'    => $p->store_id,
                                'from'        => 'purchase',
                            ])" icon="cash" :label="__('purchases.actions.record_payment')" />
                    @endcan
                @endif

                @if ($p->status === 'draft')
                    @can('delete', $p)
                        <div class="row-action-sep"></div>
                        <x-admin.row-action icon="trash" variant="danger" :label="__('purchases.actions.delete')"
                            @click="$store.confirm.show({
                                title: {{ \Illuminate\Support\Js::from(__('purchases.confirm_delete.title', ['number' => $p->number])) }},
                                message: {{ \Illuminate\Support\Js::from(__('purchases.confirm_delete.message')) }},
                                intent: 'danger',
                                confirmLabel: {{ \Illuminate\Support\Js::from(__('purchases.confirm_delete.confirm')) }},
                                onConfirm: () => $deleteForm({{ \Illuminate\Support\Js::from(route('admin.purchases.destroy', $p)) }}),
                            })" />
                    @endcan
                @endif
            </x-admin.row-actions>
        </td>
    </tr>
@endforeach
