<x-admin-layout
    active="sales"
    :title="__('sales.refund.title') . ' — ' . $sale->number"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('sales.title'), 'href' => route('admin.sales.index')],
        ['label' => $sale->number, 'href' => route('admin.sales.show', $sale)],
        ['label' => __('sales.refund.title')],
    ]">

    @php
        // Per-line "remaining returnable" qty. Anything already
        // returned in a prior refund can't be refunded again.
        $rows = $sale->items->map(function ($item) {
            $remaining = bcsub((string) $item->quantity, (string) $item->quantity_returned, 4);
            return [
                'id'          => $item->id,
                'name'        => $item->product_name_snapshot ?: $item->product?->name,
                'sku'         => $item->sku_snapshot ?: $item->product?->sku,
                'variant'     => $item->variant?->label,
                'unit'        => $item->unit ?: 'pc',
                'unit_price'  => (string) $item->unit_price,
                'quantity'    => (string) $item->quantity,
                'returned'    => (string) $item->quantity_returned,
                'remaining'   => $remaining,
                'tax_amount'  => (string) $item->tax_amount,
                'discount_amount' => (string) ($item->discount_amount ?? '0'),
            ];
        })->values();
    @endphp

    <div class="page-wide"
         x-data="saleRefundPage({
             rows:        @js($rows),
             defaultMethodId: {{ $defaultMethodId ?? 'null' }},
         })">

        <form method="POST"
              action="{{ route('admin.sales.refund.store', $sale) }}"
              data-ajax-form>
            @csrf

            <div class="page-header mb-6">
                <div class="flex items-start gap-3">
                    <a href="{{ route('admin.sales.show', $sale) }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('sales.title') }}">
                        <x-icon name="back" class="w-4 h-4" />
                    </a>
                    <div>
                        <h1 class="page-title">{{ __('sales.refund.title') }}</h1>
                        <p class="page-sub">{{ __('sales.refund.sub') }}</p>
                    </div>
                </div>
                <button type="submit"
                        class="pos-btn pos-btn-sm pos-btn-primary"
                        :disabled="!hasAnyQty">
                    <x-icon name="check" class="w-4 h-4" />
                    {{ __('sales.refund.submit') }}
                </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start">
                {{-- LEFT: line picker --}}
                <div class="card card-pad-0 min-w-0">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('sales.refund.lines') }}</div>
                    </div></div>
                    <div class="dt-scroll">
                    <table class="dt-table dt-table--lines">
                        <thead>
                            <tr>
                                <th>{{ __('sales.items.product') }}</th>
                                <th class="num">{{ __('sales.refund.line_qty') }}</th>
                                <th class="num">{{ __('sales.items.unit_price') }}</th>
                                <th class="num">{{ __('sales.items.line_total') }}</th>
                                <th>{{ __('sales.refund.line_restock') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(r, idx) in rows" :key="r.id">
                                <tr>
                                    <td>
                                        <div class="font-medium" x-text="r.name"></div>
                                        <div class="fg-tertiary text-xs mono">
                                            <span x-text="r.sku"></span>
                                            <template x-if="r.variant">
                                                <span> · <span x-text="r.variant"></span></span>
                                            </template>
                                        </div>
                                    </td>
                                    <td class="num">
                                        <input type="hidden" :name="'items[' + idx + '][sale_item_id]'" :value="r.id">
                                        <input type="number"
                                               class="pos-input refund-qty tnum"
                                               :name="'items[' + idx + '][quantity]'"
                                               x-model.number="r.refundQty"
                                               :max="r.remaining"
                                               :min="0"
                                               step="any">
                                        <div class="fg-tertiary text-xs mt-1"
                                             x-text="@js(__('sales.refund.line_remaining', ['n' => '__N__'])).replace('__N__', fmtQty(r.remaining))"></div>
                                    </td>
                                    <td class="num tnum">
                                        <span x-text="money(r.unit_price)"></span>
                                    </td>
                                    <td class="num tnum" x-text="money(lineTotal(r))"></td>
                                    <td>
                                        {{-- Per-line restock override. Empty = inherit header flag. --}}
                                        <label class="field-toggle">
                                            <input type="hidden" :name="'items[' + idx + '][restock]'" :value="r.restock === null ? '' : (r.restock ? '1' : '0')">
                                            <input type="checkbox" x-model="r.restock" :indeterminate="r.restock === null">
                                            <span class="text-xs"
                                                  x-text="r.restock === null ? @js(__('sales.refund.restock_inherit')) : (r.restock ? @js(__('sales.refund.restock_yes')) : @js(__('sales.refund.restock_no')))"></span>
                                        </label>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    </div>
                </div>

                {{-- RIGHT: refund options + totals --}}
                <div class="space-y-5">
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('sales.refund.title') }}</div>
                        </div></div>
                        <div class="card-body">
                            <div class="form-stack">
                                <label class="field">
                                    <span class="field-label is-required">{{ __('sales.refund.reason') }}</span>
                                    <select name="reason_code_id" class="pos-input" x-data="enhancedSelect()">
                                        <option value="">{{ __('sales.refund.reason_placeholder') }}</option>
                                        @foreach ($reasons as $r)
                                            <option value="{{ $r->id }}">{{ $r->name }}</option>
                                        @endforeach
                                    </select>
                                </label>

                                <label class="field">
                                    <span class="field-label">{{ __('sales.refund.method') }}</span>
                                    <select name="refund_method_id" class="pos-input" x-data="enhancedSelect()">
                                        <option value="">{{ __('sales.refund.method_placeholder') }}</option>
                                        @foreach ($methods as $m)
                                            <option value="{{ $m->id }}" @selected($m->id === $defaultMethodId)>{{ $m->name }}</option>
                                        @endforeach
                                    </select>
                                </label>

                                <label class="field-toggle">
                                    <input type="hidden" name="restock" value="0">
                                    <input type="checkbox" name="restock" value="1" x-model="headerRestock">
                                    <span>{{ __('sales.refund.restock') }}</span>
                                </label>
                                <span class="field-help">{{ __('sales.refund.restock_help') }}</span>

                                <label class="field">
                                    <span class="field-label">{{ __('sales.refund.notes') }}</span>
                                    <textarea name="notes" class="pos-input" rows="2" maxlength="5000"></textarea>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('sales.sections.totals') }}</div>
                        </div></div>
                        <div class="card-body">
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                                <div><dt class="fg-tertiary">{{ __('sales.refund.totals_subtotal') }}</dt><dd class="num tnum" x-text="money(totals.subtotal)"></dd></div>
                                <div><dt class="fg-tertiary">{{ __('sales.refund.totals_tax') }}</dt><dd class="num tnum" x-text="money(totals.tax)"></dd></div>
                                <div class="col-span-2 border-t border-subtle pt-3"><dt class="font-semibold">{{ __('sales.refund.totals_grand') }}</dt><dd class="num tnum text-base font-semibold" x-text="money(totals.grand)"></dd></div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>
