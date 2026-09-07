<x-admin-layout
    active="stock-transfers"
    :title="$transfer->number"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('inventory.transfers.crumb_parent')],
        ['label' => __('inventory.transfers.title'), 'href' => route('admin.inventory.transfers.index')],
        ['label' => $transfer->number],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ route('admin.inventory.transfers.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('inventory.transfers.title') }}">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title">
                        {{ __('inventory.transfers.view.header', ['number' => $transfer->number]) }}
                        @if ($transfer->isReceived())
                            <span class="prod-badge prod-badge-positive ms-2">{{ __('inventory.transfers.status.received') }}</span>
                        @elseif ($transfer->isInTransit())
                            <span class="prod-badge prod-badge-warning ms-2">{{ __('inventory.transfers.status.in_transit') }}</span>
                        @elseif ($transfer->isCancelled())
                            <span class="prod-badge prod-badge-negative ms-2">{{ __('inventory.transfers.status.cancelled') }}</span>
                        @else
                            <span class="prod-badge prod-badge-muted ms-2">{{ __('inventory.transfers.status.draft') }}</span>
                        @endif
                    </h1>
                    <p class="page-sub">
                        @if ($transfer->isReceived())
                            {{ __('inventory.transfers.view.received_sub', ['date' => format_date($transfer->received_date)]) }}
                        @elseif ($transfer->isInTransit())
                            {{ __('inventory.transfers.view.transit_sub', ['from' => $transfer->fromStore?->name, 'to' => $transfer->toStore?->name]) }}
                        @elseif ($transfer->isCancelled())
                            {{ __('inventory.transfers.view.cancelled_sub') }}
                        @else
                            {{ __('inventory.transfers.view.draft_sub') }}
                        @endif
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2" x-data>
                @if ($transfer->isDraft())
                    @can('delete', $transfer)
                        <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost pos-btn-danger"
                                @click="$store.confirm.show({
                                    title: {{ \Illuminate\Support\Js::from(__('inventory.transfers.confirm_delete.title', ['number' => $transfer->number])) }},
                                    message: {{ \Illuminate\Support\Js::from(__('inventory.transfers.confirm_delete.message')) }},
                                    intent: 'danger',
                                    confirmLabel: {{ \Illuminate\Support\Js::from(__('inventory.transfers.confirm_delete.confirm')) }},
                                    onConfirm: () => $deleteForm({{ \Illuminate\Support\Js::from(route('admin.inventory.transfers.destroy', $transfer)) }}),
                                })">
                            <x-icon name="trash" class="w-4 h-4" />
                            {{ __('inventory.transfers.actions.delete') }}
                        </button>
                    @endcan
                    @can('cancel', $transfer)
                        <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost"
                                @click="$store.confirm.show({
                                    title: {{ \Illuminate\Support\Js::from(__('inventory.transfers.confirm_cancel.title', ['number' => $transfer->number])) }},
                                    message: {{ \Illuminate\Support\Js::from(__('inventory.transfers.confirm_cancel.message_draft')) }},
                                    intent: 'warning',
                                    confirmLabel: {{ \Illuminate\Support\Js::from(__('inventory.transfers.confirm_cancel.confirm')) }},
                                    onConfirm: () => $submitForm({{ \Illuminate\Support\Js::from(route('admin.inventory.transfers.cancel', $transfer)) }}),
                                })">
                            <x-icon name="x" class="w-4 h-4" />
                            {{ __('inventory.transfers.actions.cancel') }}
                        </button>
                    @endcan
                    @can('update', $transfer)
                        <a href="{{ route('admin.inventory.transfers.edit', $transfer) }}" class="pos-btn pos-btn-sm pos-btn-ghost">
                            <x-icon name="edit" class="w-4 h-4" />
                            {{ __('inventory.transfers.actions.edit') }}
                        </a>
                    @endcan
                    @can('dispatch', $transfer)
                        <button type="button" class="pos-btn pos-btn-sm pos-btn-primary"
                                @click="$store.confirm.show({
                                    title: {{ \Illuminate\Support\Js::from(__('inventory.transfers.confirm_dispatch.title', ['number' => $transfer->number])) }},
                                    message: {{ \Illuminate\Support\Js::from(__('inventory.transfers.confirm_dispatch.message', ['from' => $transfer->fromStore?->name])) }},
                                    intent: 'warning',
                                    confirmLabel: {{ \Illuminate\Support\Js::from(__('inventory.transfers.confirm_dispatch.confirm')) }},
                                    onConfirm: () => $submitForm({{ \Illuminate\Support\Js::from(route('admin.inventory.transfers.dispatch', $transfer)) }}),
                                })">
                            <x-icon name="truck" class="w-4 h-4" />
                            {{ __('inventory.transfers.actions.dispatch') }}
                        </button>
                    @endcan
                @elseif ($transfer->isInTransit())
                    @can('cancel', $transfer)
                        <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost pos-btn-danger"
                                @click="$store.confirm.show({
                                    title: {{ \Illuminate\Support\Js::from(__('inventory.transfers.confirm_cancel.title', ['number' => $transfer->number])) }},
                                    message: {{ \Illuminate\Support\Js::from(__('inventory.transfers.confirm_cancel.message_transit', ['from' => $transfer->fromStore?->name])) }},
                                    intent: 'danger',
                                    confirmLabel: {{ \Illuminate\Support\Js::from(__('inventory.transfers.confirm_cancel.confirm')) }},
                                    onConfirm: () => $submitForm({{ \Illuminate\Support\Js::from(route('admin.inventory.transfers.cancel', $transfer)) }}),
                                })">
                            <x-icon name="x" class="w-4 h-4" />
                            {{ __('inventory.transfers.actions.cancel') }}
                        </button>
                    @endcan
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_300px] gap-5 items-start">
            <div class="space-y-5">
                {{-- Header meta card --}}
                <div class="card">
                    <div class="card-body">
                        <dl class="inv-meta-grid">
                            <div>
                                <dt>{{ __('inventory.transfers.fields.from_store') }}</dt>
                                <dd>{{ $transfer->fromStore?->name ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt>{{ __('inventory.transfers.fields.to_store') }}</dt>
                                <dd>{{ $transfer->toStore?->name ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt>{{ __('inventory.transfers.fields.transfer_date') }}</dt>
                                <dd>{{ format_date($transfer->transfer_date) }}</dd>
                            </div>
                            @if ($transfer->expected_arrival_date)
                                <div>
                                    <dt>{{ __('inventory.transfers.fields.expected_arrival_date') }}</dt>
                                    <dd>{{ format_date($transfer->expected_arrival_date) }}</dd>
                                </div>
                            @endif
                            @if ($transfer->received_date)
                                <div>
                                    <dt>{{ __('inventory.transfers.columns.status') }}</dt>
                                    <dd>{{ format_date($transfer->received_date) }}</dd>
                                </div>
                            @endif
                            @if ($transfer->notes)
                                <div class="inv-meta-full">
                                    <dt>{{ __('inventory.transfers.fields.notes') }}</dt>
                                    <dd class="whitespace-pre-wrap">{{ $transfer->notes }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                </div>

                {{-- Items table --}}
                <div class="card card-pad-0">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('inventory.transfers.items.title') }}</div>
                    </div></div>

                    @if ($transfer->items->isEmpty())
                        <div class="dt-empty">
                            <span class="dt-empty-icon"><x-icon name="box" class="w-5 h-5" /></span>
                            <div class="dt-empty-title">{{ __('inventory.transfers.items.empty') }}</div>
                        </div>
                    @else
                        <table class="dt-table">
                            <thead>
                                <tr>
                                    <th>{{ __('inventory.transfers.items.col_product') }}</th>
                                    <th class="num">{{ __('inventory.transfers.items.col_qty') }}</th>
                                    @if ($transfer->isReceived())
                                        <th class="num">{{ __('inventory.transfers.items.col_received') }}</th>
                                        <th class="num">{{ __('inventory.transfers.items.col_returned') }}</th>
                                    @endif
                                    <th class="num">{{ __('inventory.transfers.items.col_cost') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($transfer->items as $item)
                                    <tr>
                                        <td>
                                            <div class="store-row-name-line">
                                                <span class="store-row-name">{{ $item->product?->name }}</span>
                                                @if ($item->variant)
                                                    <span class="fg-tertiary"> — {{ $item->variant->label }}</span>
                                                @endif
                                            </div>
                                            <span class="mono fg-tertiary">{{ $item->variant?->sku ?: $item->product?->sku }}</span>
                                        </td>
                                        <td class="num tnum">{{ number_format((float) $item->requested_quantity, 4) }}</td>
                                        @if ($transfer->isReceived())
                                            <td class="num tnum">
                                                @if ($item->received_quantity !== null)
                                                    {{ number_format((float) $item->received_quantity, 4) }}
                                                @else
                                                    <span class="fg-tertiary">—</span>
                                                @endif
                                            </td>
                                            <td class="num tnum">
                                                @php $returned = max(0, (float) $item->requested_quantity - (float) ($item->received_quantity ?? 0)); @endphp
                                                @if ($returned > 0)
                                                    <span class="fg-warning">{{ number_format($returned, 4) }}</span>
                                                @else
                                                    <span class="fg-tertiary">—</span>
                                                @endif
                                            </td>
                                        @endif
                                        <td class="num tnum">
                                            @if ($item->unit_cost !== null)
                                                {{ format_money($item->unit_cost) }}
                                            @else
                                                <span class="fg-tertiary">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif

                    @php
                        $anyShort = $transfer->isReceived() && $transfer->items->contains(
                            fn ($i) => (float) $i->requested_quantity > (float) ($i->received_quantity ?? 0)
                        );
                    @endphp
                    @if ($anyShort)
                        <div class="card-footer">
                            <p class="text-sm fg-secondary">{{ __('inventory.transfers.items.returned_note', ['store' => $transfer->fromStore?->name]) }}</p>
                        </div>
                    @endif
                </div>

                {{-- Receive form (in_transit only) --}}
                @if ($transfer->isInTransit())
                    @can('receive', $transfer)
                        <div class="card card-pad-0" x-data="{ open: false }">
                            <div class="card-header cursor-pointer" @click="open = !open">
                                <div>
                                    <div class="card-title">{{ __('inventory.transfers.receive.title') }}</div>
                                    <div class="card-title-sub">{{ __('inventory.transfers.receive.sub') }}</div>
                                </div>
                                <x-icon name="chevron" class="w-4 h-4 fg-tertiary transition-transform" ::class="open ? 'rotate-180' : ''" />
                            </div>

                            <div x-show="open" x-cloak>
                                <form method="POST"
                                      action="{{ route('admin.inventory.transfers.receive', $transfer) }}"
                                      novalidate data-ajax-form>
                                    @csrf

                                    <table class="dt-table">
                                        <thead>
                                            <tr>
                                                <th>{{ __('inventory.transfers.items.col_product') }}</th>
                                                <th class="num">{{ __('inventory.transfers.items.col_qty') }}</th>
                                                <th class="num">{{ __('inventory.transfers.items.col_received') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($transfer->items as $i => $item)
                                                <tr>
                                                    <td>
                                                        <div class="store-row-name-line">
                                                            <span class="store-row-name">{{ $item->product?->name }}</span>
                                                            @if ($item->variant)
                                                                <span class="fg-tertiary"> — {{ $item->variant->label }}</span>
                                                            @endif
                                                        </div>
                                                        <span class="mono fg-tertiary">{{ $item->variant?->sku ?: $item->product?->sku }}</span>
                                                        <input type="hidden" name="items[{{ $i }}][id]" value="{{ $item->id }}">
                                                    </td>
                                                    <td class="num tnum">{{ number_format((float) $item->requested_quantity, 4) }}</td>
                                                    <td class="num">
                                                        <input type="number" step="0.0001" min="0"
                                                               name="items[{{ $i }}][received_quantity]"
                                                               value="{{ number_format((float) $item->requested_quantity, 4) }}"
                                                               class="pos-input pos-input-sm tnum">
                                                        <p class="field-help text-end">{{ __('inventory.transfers.receive.qty_help') }}</p>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>

                                    <div class="card-footer flex justify-end gap-2">
                                        <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="open = false">
                                            {{ __('inventory.transfers.actions.discard') }}
                                        </button>
                                        <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                                            <x-icon name="check" class="w-4 h-4" />
                                            {{ __('inventory.transfers.actions.receive') }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endcan
                @endif
            </div>

            {{-- ── Right: meta sidebar ─────────────────────────────────── --}}
            <div class="space-y-5 lg:sticky lg:top-4">
                <div class="card">
                    <div class="card-body">
                        <dl class="space-y-3 text-sm">
                            <div>
                                <dt class="fg-tertiary text-xs uppercase tracking-wide font-semibold mb-1">{{ __('inventory.adjustments.columns.by') }}</dt>
                                <dd>{{ $transfer->creator?->name ?? '—' }}</dd>
                            </div>
                            @if ($transfer->updater && $transfer->updater->id !== $transfer->creator?->id)
                                <div>
                                    <dt class="fg-tertiary text-xs uppercase tracking-wide font-semibold mb-1">{{ __('inventory.adjustments.columns.by') }}</dt>
                                    <dd>{{ $transfer->updater->name }}</dd>
                                </div>
                            @endif
                            <div>
                                <dt class="fg-tertiary text-xs uppercase tracking-wide font-semibold mb-1">{{ __('inventory.transfers.columns.items') }}</dt>
                                <dd class="tnum font-semibold">{{ $transfer->items->count() }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
