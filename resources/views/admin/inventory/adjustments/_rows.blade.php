{{--
    Table-view rows for the Stock adjustments list.

    Rendered inline by `admin.inventory.adjustments.index` (first page) and
    standalone by `StockAdjustmentController@rows` (JSON `html`) for every
    subsequent page / filter / search.

    Expects: $adjustments (iterable of StockAdjustment, with store/reasonCode/
    creator/items loaded and items_count present).
--}}
@foreach ($adjustments as $adj)
    <tr class="prod-row" data-dt-row
        data-dt-name="{{ $adj->number ?? '' }}"
        data-dt-id="{{ $adj->id }}"
        @click="window.location.href = {{ \Illuminate\Support\Js::from(route('admin.inventory.adjustments.show', $adj)) }}">
        <td class="mono">{{ $adj->number }}</td>
        <td>{{ format_date($adj->adjustment_date) }}</td>
        <td>{{ $adj->store?->name }}</td>
        <td>{{ $adj->reasonCode?->name ?: ($adj->reason ?: '—') }}</td>
        <td class="num tnum">
            {{-- Peek the lines inline — saves a round-trip to the document. --}}
            <x-admin.cell-peek
                :label="$adj->items_count"
                :disabled="$adj->items_count === 0"
                :title="__('inventory.adjustments.peek.title')"
                :sr-label="__('inventory.adjustments.peek.sr_label', ['number' => $adj->number])">
                @foreach ($adj->items as $item)
                    @php $d = (float) $item->quantity_delta; @endphp
                    <div class="cell-peek-row">
                        <span class="cell-peek-name">
                            {{ $item->product?->name ?? __('inventory.adjustments.peek.unknown_product') }}
                            @if ($item->variant?->label)
                                <span class="cell-peek-meta">{{ $item->variant->label }}</span>
                            @endif
                        </span>
                        <span class="cell-peek-value {{ $d >= 0 ? 'inv-delta-pos' : 'inv-delta-neg' }}">
                            {{ $d >= 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($d, 4, '.', ''), '0'), '.') ?: '0' }}
                        </span>
                    </div>
                @endforeach
            </x-admin.cell-peek>
        </td>
        <td>
            @if ($adj->isPosted())
                <span class="prod-badge prod-badge-positive">{{ __('inventory.adjustments.status.posted') }}</span>
            @else
                <span class="prod-badge prod-badge-muted">{{ __('inventory.adjustments.status.draft') }}</span>
            @endif
        </td>
        <td>{{ $adj->creator?->name ?? '—' }}</td>
        <td>
            <x-admin.row-actions>
                @if ($adj->isDraft())
                    <x-admin.row-action :href="route('admin.inventory.adjustments.edit', $adj)" icon="edit" :label="__('inventory.adjustments.actions.edit')" />
                    <div class="row-action-sep"></div>
                    <x-admin.row-action icon="trash" variant="danger" :label="__('inventory.adjustments.actions.delete')"
                        @click="$store.confirm.show({
                            title: {{ \Illuminate\Support\Js::from(__('inventory.adjustments.confirm_delete.title', ['number' => $adj->number])) }},
                            message: {{ \Illuminate\Support\Js::from(__('inventory.adjustments.confirm_delete.message')) }},
                            intent: 'danger',
                            confirmLabel: {{ \Illuminate\Support\Js::from(__('inventory.adjustments.confirm_delete.confirm')) }},
                            onConfirm: () => $deleteForm({{ \Illuminate\Support\Js::from(route('admin.inventory.adjustments.destroy', $adj)) }}),
                        })" />
                @else
                    <x-admin.row-action :href="route('admin.inventory.adjustments.show', $adj)" icon="eye" :label="__('inventory.adjustments.actions.view')" />
                @endif
            </x-admin.row-actions>
        </td>
    </tr>
@endforeach
