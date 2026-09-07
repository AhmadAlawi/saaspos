<x-admin-layout
    active="purchase-returns"
    :title="$return->number"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('purchases.crumb_parent')],
        ['label' => __('purchases.returns.title'), 'href' => route('admin.purchase-returns.index')],
        ['label' => $return->number],
    ]">

    @php
        $statusBadge = $return->status === 'posted' ? 'prod-badge-positive' : 'prod-badge-muted';
    @endphp

    <div class="page-wide">
        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ route('admin.purchase-returns.index') }}"
                   class="pos-btn pos-btn-sm pos-btn-ghost"
                   aria-label="{{ __('purchases.returns.title') }}">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title">
                        {{ $return->number }}
                        <span class="prod-badge {{ $statusBadge }} ms-2">
                            {{ __('purchases.returns.status.'.$return->status) }}
                        </span>
                    </h1>
                    <p class="page-sub">
                        {{ $return->supplier?->name }}
                        ·
                        {{ format_date($return->return_date) }}
                        ·
                        <a href="{{ route('admin.purchases.show', $return->purchase_id) }}"
                           class="link mono">
                            {{ $return->purchase?->number }}
                        </a>
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">
            {{-- LEFT: Items --}}
            <div class="space-y-5">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">{{ __('purchases.returns.sections.items') }}</div>
                    </div>
                    <div class="card-body p-0">
                        <table class="dt-table">
                            <thead>
                                <tr>
                                    <th>{{ __('purchases.columns.product') }}</th>
                                    <th class="num">{{ __('purchases.returns.fields.quantity') }}</th>
                                    <th class="num">{{ __('purchases.columns.unit_cost') }}</th>
                                    <th class="text-center">{{ __('purchases.returns.fields.restock') }}</th>
                                    <th class="num">{{ __('purchases.columns.line_total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($return->items as $item)
                                    <tr>
                                        <td>
                                            {{ $item->purchaseItem?->product?->name ?: '—' }}
                                            <span class="mono fg-tertiary text-xs ms-1">
                                                {{ $item->purchaseItem?->product?->sku }}
                                            </span>
                                            <x-admin.purchase-batch-line
                                                :number="$item->purchaseItem?->batch_number"
                                                :mfg="$item->purchaseItem?->manufacture_date"
                                                :expiry="$item->purchaseItem?->expiry_date" />
                                        </td>
                                        <td class="num tnum">
                                            {{ rtrim(rtrim((string) $item->quantity, '0'), '.') }}
                                        </td>
                                        <td class="num tnum">{{ format_money($item->unit_cost_snapshot) }}</td>
                                        <td class="text-center">
                                            @if ($item->restock)
                                                <span class="prod-badge prod-badge-positive">{{ __('purchases.returns.restock_yes') }}</span>
                                            @else
                                                <span class="prod-badge prod-badge-muted">{{ __('purchases.returns.restock_no') }}</span>
                                            @endif
                                        </td>
                                        <td class="num tnum">{{ format_money($item->line_total) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                @if ($return->notes)
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">{{ __('purchases.sections.notes') }}</div>
                        </div>
                        <div class="card-body">
                            <p class="text-sm whitespace-pre-wrap">{{ $return->notes }}</p>
                        </div>
                    </div>
                @endif
            </div>

            {{-- RIGHT: Totals --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">{{ __('purchases.returns.sections.totals') }}</div>
                </div>
                <div class="card-body">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        <div>
                            <dt class="fg-tertiary">{{ __('purchases.returns.totals.subtotal') }}</dt>
                            <dd class="num tnum">{{ format_money($return->subtotal) }}</dd>
                        </div>
                        <div>
                            <dt class="fg-tertiary">{{ __('purchases.returns.totals.tax') }}</dt>
                            <dd class="num tnum">{{ format_money($return->tax_amount) }}</dd>
                        </div>
                        <div class="col-span-2 border-t border-subtle pt-3">
                            <dt class="font-semibold">{{ __('purchases.returns.totals.grand') }}</dt>
                            <dd class="num tnum text-base font-semibold">{{ format_money($return->grand_total) }}</dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="fg-tertiary">{{ __('purchases.returns.totals.refund') }}</dt>
                            <dd class="num tnum">{{ format_money($return->refund_amount) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
