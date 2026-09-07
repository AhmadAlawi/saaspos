<x-admin-layout
    active="purchase-returns"
    :title="__('purchases.returns.new')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('purchases.crumb_parent')],
        ['label' => __('purchases.title'), 'href' => route('admin.purchases.index')],
        ['label' => $purchase->number, 'href' => route('admin.purchases.show', $purchase)],
        ['label' => __('purchases.returns.new')],
    ]">

    <div class="page-wide"
         x-data="{
             items: @js($purchase->items->map(fn ($i) => [
                 'purchase_item_id' => $i->id,
                 'name'             => $i->product?->name ?? '—',
                 'sku'              => $i->product?->sku ?? '',
                 'received_qty'     => (float) ($i->received_quantity ?? $i->quantity),
                 'unit_cost'        => (float) $i->unit_cost,
                 'quantity'         => 0,
                 'restock'          => true,
                 'batch_number'     => $i->batch_number,
                 'manufacture_date' => $i->manufacture_date?->format('d M Y'),
                 'expiry_date'      => $i->expiry_date?->format('d M Y'),
             ])->values()->all()),

             get subtotal() {
                 return this.items.reduce((s, r) => s + r.quantity * r.unit_cost, 0);
             },
         }">

        <form method="POST"
              action="{{ route('admin.purchase-returns.store', $purchase) }}"
              data-ajax-form>
            @csrf

            <div class="page-header mb-6">
                <div class="flex items-start gap-3">
                    <a href="{{ route('admin.purchases.show', $purchase) }}"
                       class="pos-btn pos-btn-sm pos-btn-ghost"
                       aria-label="{{ __('purchases.title') }}">
                        <x-icon name="back" class="w-4 h-4" />
                    </a>
                    <div>
                        <h1 class="page-title">{{ __('purchases.returns.new') }}</h1>
                        <p class="page-sub">{{ $purchase->number }} · {{ $purchase->supplier?->name }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.purchases.show', $purchase) }}"
                       class="pos-btn pos-btn-sm pos-btn-ghost">
                        {{ __('purchases.returns.actions.discard') }}
                    </a>
                    <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                        <x-icon name="check" class="w-4 h-4" />
                        {{ __('purchases.returns.actions.create') }}
                    </button>
                </div>
            </div>

            <div class="space-y-5">
                {{-- Return header --}}
                <div class="card">
                    <div class="card-header">
                        <div>
                            <div class="card-title">{{ __('purchases.returns.sections.header') }}</div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-stack">
                            <label class="field">
                                <span class="field-label is-required">{{ __('purchases.returns.fields.return_date') }}</span>
                                <input type="text"
                                       name="return_date"
                                       value="{{ old('return_date', now()->toDateString()) }}"
                                       class="pos-input js-datepicker"
                                       autocomplete="off">
                            </label>
                            <label class="field">
                                <span class="field-label">{{ __('purchases.returns.fields.notes') }}</span>
                                <textarea name="notes"
                                          rows="2"
                                          class="pos-input resize-none"
                                          placeholder="{{ __('purchases.returns.fields.notes_placeholder') }}">{{ old('notes') }}</textarea>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Items --}}
                <div class="card">
                    <div class="card-header">
                        <div>
                            <div class="card-title">{{ __('purchases.returns.sections.items') }}</div>
                            <div class="card-title-sub">{{ __('purchases.returns.sections.items_sub') }}</div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="dt-scroll">
                        <table class="dt-table dt-table--lines">
                            <thead>
                                <tr>
                                    <th>{{ __('purchases.columns.product') }}</th>
                                    <th class="num">{{ __('purchases.returns.columns.received_qty') }}</th>
                                    <th class="num w-[140px]">{{ __('purchases.returns.fields.quantity') }}</th>
                                    <th class="num">{{ __('purchases.columns.unit_cost') }}</th>
                                    <th class="text-center w-[110px]">{{ __('purchases.returns.fields.restock') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(item, idx) in items" :key="item.purchase_item_id">
                                    <tr>
                                        <td>
                                            <input type="hidden"
                                                   :name="`items[${idx}][purchase_item_id]`"
                                                   :value="item.purchase_item_id">
                                            <span x-text="item.name" class="font-medium"></span>
                                            <span x-text="item.sku" class="mono fg-tertiary text-xs ms-1"></span>
                                            <template x-if="item.batch_number || item.manufacture_date || item.expiry_date">
                                                <div class="fg-tertiary text-xs flex flex-wrap gap-x-2 mt-0.5">
                                                    <span x-show="item.batch_number">{{ __('purchases.line.batch_chip') }}: <span class="mono" x-text="item.batch_number"></span></span>
                                                    <span x-show="item.manufacture_date">{{ __('purchases.line.mfg_short') }}: <span x-text="item.manufacture_date"></span></span>
                                                    <span x-show="item.expiry_date">{{ __('purchases.line.exp_short') }}: <span x-text="item.expiry_date"></span></span>
                                                </div>
                                            </template>
                                        </td>
                                        <td class="num tnum fg-tertiary">
                                            <span x-text="item.received_qty.toFixed(4).replace(/\.?0+$/, '')"></span>
                                        </td>
                                        <td class="num">
                                            <input type="number"
                                                   :name="`items[${idx}][quantity]`"
                                                   x-model.number="item.quantity"
                                                   min="0"
                                                   :max="item.received_qty"
                                                   step="0.0001"
                                                   class="pos-input text-end w-[120px]">
                                        </td>
                                        <td class="num tnum">
                                            <span x-text="$formatMoney(item.unit_cost)"></span>
                                        </td>
                                        <td class="text-center">
                                            <input type="hidden"
                                                   :name="`items[${idx}][restock]`"
                                                   :value="item.restock ? '1' : '0'">
                                            <input type="checkbox"
                                                   x-model="item.restock"
                                                   class="pos-check">
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>

                {{-- Totals preview --}}
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">{{ __('purchases.returns.sections.totals') }}</div>
                    </div>
                    <div class="card-body">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm max-w-[320px]">
                            <div>
                                <dt class="fg-tertiary">{{ __('purchases.returns.totals.subtotal') }}</dt>
                                <dd class="num tnum" x-text="$formatMoney(subtotal)"></dd>
                            </div>
                            <div>
                                <dt class="fg-tertiary text-[11px]">{{ __('purchases.returns.totals.tax_note') }}</dt>
                                <dd class="num tnum fg-tertiary text-xs">{{ __('purchases.returns.totals.tax_server') }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>
