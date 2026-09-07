{{--
    Table-view rows for the Stock transfers list.

    Rendered inline by `admin.inventory.transfers.index` (first page) and
    standalone by `StockTransferController@rows` (JSON `html`) for every
    subsequent page / filter / search.

    Expects: $transfers (iterable of StockTransfer, with fromStore/toStore/
    creator loaded and items_count present).
--}}
@foreach ($transfers as $t)
    <tr class="prod-row" data-dt-row
        data-dt-name="{{ $t->number ?? '' }}"
        data-dt-id="{{ $t->id }}"
        @click="window.location.href = {{ \Illuminate\Support\Js::from(route('admin.inventory.transfers.show', $t)) }}">
        <td class="mono">{{ $t->number }}</td>
        <td>{{ format_date($t->transfer_date) }}</td>
        <td>{{ $t->fromStore?->name ?? '—' }}</td>
        <td>{{ $t->toStore?->name ?? '—' }}</td>
        <td class="num tnum">{{ $t->items_count }}</td>
        <td>
            @if ($t->isReceived())
                <span class="prod-badge prod-badge-positive">{{ __('inventory.transfers.status.received') }}</span>
            @elseif ($t->isInTransit())
                <span class="prod-badge prod-badge-warning">{{ __('inventory.transfers.status.in_transit') }}</span>
            @elseif ($t->isCancelled())
                <span class="prod-badge prod-badge-negative">{{ __('inventory.transfers.status.cancelled') }}</span>
            @else
                <span class="prod-badge prod-badge-muted">{{ __('inventory.transfers.status.draft') }}</span>
            @endif
        </td>
        <td>{{ $t->creator?->name ?? '—' }}</td>
        <td>
            <x-admin.row-actions>
                @if ($t->isDraft())
                    <x-admin.row-action :href="route('admin.inventory.transfers.edit', $t)" icon="edit" :label="__('inventory.transfers.actions.edit')" />
                    <div class="row-action-sep"></div>
                    <x-admin.row-action icon="trash" variant="danger" :label="__('inventory.transfers.actions.delete')"
                        @click="$store.confirm.show({
                            title: {{ \Illuminate\Support\Js::from(__('inventory.transfers.confirm_delete.title', ['number' => $t->number])) }},
                            message: {{ \Illuminate\Support\Js::from(__('inventory.transfers.confirm_delete.message')) }},
                            intent: 'danger',
                            confirmLabel: {{ \Illuminate\Support\Js::from(__('inventory.transfers.confirm_delete.confirm')) }},
                            onConfirm: () => $deleteForm({{ \Illuminate\Support\Js::from(route('admin.inventory.transfers.destroy', $t)) }}),
                        })" />
                @else
                    <x-admin.row-action :href="route('admin.inventory.transfers.show', $t)" icon="eye" :label="__('inventory.transfers.actions.view')" />
                @endif
            </x-admin.row-actions>
        </td>
    </tr>
@endforeach
