<x-admin-layout
    active="stock-adjustments"
    :title="$adjustment->number"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('inventory.adjustments.crumb_parent')],
        ['label' => __('inventory.adjustments.title'), 'href' => route('admin.inventory.adjustments.index')],
        ['label' => $adjustment->number],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ route('admin.inventory.adjustments.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('inventory.adjustments.title') }}">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title">
                        {{ __('inventory.adjustments.view.header', ['number' => $adjustment->number]) }}
                        @if ($adjustment->isPosted())
                            <span class="prod-badge prod-badge-positive ms-2">{{ __('inventory.adjustments.status.posted') }}</span>
                        @else
                            <span class="prod-badge prod-badge-muted ms-2">{{ __('inventory.adjustments.status.draft') }}</span>
                        @endif
                    </h1>
                    <p class="page-sub">
                        @if ($adjustment->isPosted())
                            {{ __('inventory.adjustments.view.posted_at', ['when' => format_datetime($adjustment->posted_at)]) }}
                        @else
                            {{ __('inventory.adjustments.view.draft_sub') }}
                        @endif
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2" x-data>
                @if ($adjustment->isDraft())
                    @can('delete', $adjustment)
                        <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost pos-btn-danger"
                                @click="$store.confirm.show({
                                    title: {{ \Illuminate\Support\Js::from(__('inventory.adjustments.confirm_delete.title', ['number' => $adjustment->number])) }},
                                    message: {{ \Illuminate\Support\Js::from(__('inventory.adjustments.confirm_delete.message')) }},
                                    intent: 'danger',
                                    confirmLabel: {{ \Illuminate\Support\Js::from(__('inventory.adjustments.confirm_delete.confirm')) }},
                                    onConfirm: () => $deleteForm({{ \Illuminate\Support\Js::from(route('admin.inventory.adjustments.destroy', $adjustment)) }}),
                                })">
                            <x-icon name="trash" class="w-4 h-4" />
                            {{ __('inventory.adjustments.actions.delete') }}
                        </button>
                    @endcan
                    @can('update', $adjustment)
                        <a href="{{ route('admin.inventory.adjustments.edit', $adjustment) }}" class="pos-btn pos-btn-sm pos-btn-ghost">
                            <x-icon name="edit" class="w-4 h-4" />
                            {{ __('inventory.adjustments.actions.edit') }}
                        </a>
                    @endcan
                    @can('post', $adjustment)
                        {{-- $submitForm POSTs and returns a never-resolving Promise,
                             so the confirm dialog spinner stays up until navigation
                             — same UX as the delete-confirm flow. --}}
                        <button type="button" class="pos-btn pos-btn-sm pos-btn-primary"
                                @click="$store.confirm.show({
                                    title: {{ \Illuminate\Support\Js::from(__('inventory.adjustments.confirm_post.title', ['number' => $adjustment->number])) }},
                                    message: {{ \Illuminate\Support\Js::from(__('inventory.adjustments.confirm_post.message')) }},
                                    intent: 'warning',
                                    confirmLabel: {{ \Illuminate\Support\Js::from(__('inventory.adjustments.confirm_post.confirm')) }},
                                    onConfirm: () => $submitForm({{ \Illuminate\Support\Js::from(route('admin.inventory.adjustments.post', $adjustment)) }}),
                                })">
                            <x-icon name="check" class="w-4 h-4" />
                            {{ __('inventory.adjustments.actions.post') }}
                        </button>
                    @endcan
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start">
            <div class="space-y-5">
                {{-- Header meta --}}
                <div class="card">
                    <div class="card-body">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            <div>
                                <dt class="fg-tertiary">{{ __('inventory.adjustments.columns.store') }}</dt>
                                <dd class="fg-primary">{{ $adjustment->store?->name }}</dd>
                            </div>
                            <div>
                                <dt class="fg-tertiary">{{ __('inventory.adjustments.columns.date') }}</dt>
                                <dd class="fg-primary">{{ format_date($adjustment->adjustment_date) }}</dd>
                            </div>
                            <div>
                                <dt class="fg-tertiary">{{ __('inventory.adjustments.fields.reason_code') }}</dt>
                                <dd class="fg-primary">{{ $adjustment->reasonCode?->name ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="fg-tertiary">{{ __('inventory.adjustments.fields.reason') }}</dt>
                                <dd class="fg-primary">{{ $adjustment->reason ?: '—' }}</dd>
                            </div>
                            @if ($adjustment->notes)
                                <div class="col-span-2">
                                    <dt class="fg-tertiary">{{ __('inventory.adjustments.fields.notes') }}</dt>
                                    <dd class="fg-primary whitespace-pre-wrap">{{ $adjustment->notes }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                </div>

                {{-- Line items --}}
                <div class="card card-pad-0">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('inventory.adjustments.items.title') }}</div>
                    </div></div>
                    @if ($adjustment->items->isEmpty())
                        <div class="dt-empty">
                            <div class="dt-empty-title">{{ __('inventory.adjustments.items.empty') }}</div>
                        </div>
                    @else
                        <table class="dt-table">
                            <thead>
                                <tr>
                                    <th>{{ __('inventory.adjustments.items.col_product') }}</th>
                                    <th>{{ __('inventory.adjustments.items.col_variant') }}</th>
                                    <th>{{ __('inventory.adjustments.items.col_sku') }}</th>
                                    <th class="num">{{ __('inventory.adjustments.items.col_qty') }}</th>
                                    <th class="num">{{ __('inventory.adjustments.items.col_cost') }}</th>
                                    <th>{{ __('inventory.adjustments.items.col_notes') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($adjustment->items as $item)
                                    @php $d = (float) $item->quantity_delta; @endphp
                                    <tr>
                                        <td><span class="store-row-name">{{ $item->product?->name }}</span></td>
                                        <td>{{ $item->variant?->label ?? '—' }}</td>
                                        <td class="mono fg-tertiary">{{ $item->variant?->sku ?? $item->product?->sku }}</td>
                                        <td class="num tnum {{ $d >= 0 ? 'inv-delta-pos' : 'inv-delta-neg' }}">
                                            {{ $d >= 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($d, 4, '.', ''), '0'), '.') ?: '0' }}
                                        </td>
                                        <td class="num tnum">{{ $item->unit_cost !== null ? format_money((float) $item->unit_cost) : '—' }}</td>
                                        <td>{{ $item->notes ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

            {{-- Right column: totals --}}
            <div class="space-y-5 lg:sticky lg:top-4">
                @php
                    $totalIn  = $adjustment->items->reduce(fn ($s, $i) => (float) $i->quantity_delta > 0 ? $s + (float) $i->quantity_delta : $s, 0);
                    $totalOut = $adjustment->items->reduce(fn ($s, $i) => (float) $i->quantity_delta < 0 ? $s + abs((float) $i->quantity_delta) : $s, 0);
                    $totalNet = $totalIn - $totalOut;
                @endphp
                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('inventory.adjustments.items.title') }}</div>
                    </div></div>
                    <div class="card-body">
                        <div class="adj-totals">
                            <div>
                                <span class="adj-totals-label">{{ __('inventory.adjustments.items.total_in') }}</span>
                                <span class="adj-totals-value inv-delta-pos tnum">{{ rtrim(rtrim(number_format($totalIn, 4, '.', ''), '0'), '.') ?: '0' }}</span>
                            </div>
                            <div>
                                <span class="adj-totals-label">{{ __('inventory.adjustments.items.total_out') }}</span>
                                <span class="adj-totals-value inv-delta-neg tnum">-{{ rtrim(rtrim(number_format($totalOut, 4, '.', ''), '0'), '.') ?: '0' }}</span>
                            </div>
                            <div class="adj-totals-net">
                                <span class="adj-totals-label">{{ __('inventory.adjustments.items.total_net') }}</span>
                                <span class="adj-totals-value tnum {{ $totalNet > 0 ? 'inv-delta-pos' : ($totalNet < 0 ? 'inv-delta-neg' : '') }}">{{ ($totalNet >= 0 ? '+' : '').(rtrim(rtrim(number_format($totalNet, 4, '.', ''), '0'), '.') ?: '0') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
