<x-admin-layout
    active="stock-takes"
    :title="__('stock_takes.edit_title', ['number' => $take->number])"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('stock_takes.crumb_parent')],
        ['label' => __('stock_takes.title'), 'href' => route('admin.inventory.stock-takes.index')],
        ['label' => $take->number],
    ]">

    @php
        $rows = $take->items->map(function ($item) {
            $name = $item->product?->name ?? '—';
            $sku  = $item->variant?->sku ?: $item->product?->sku;
            return [
                'id'         => $item->id,
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'name'       => $name,
                'sku'        => $sku,
                'variant'   => $item->variant?->attributes
                    ? collect(json_decode(is_string($item->variant->attributes) ? $item->variant->attributes : json_encode($item->variant->attributes), true) ?: [])
                        ->values()->implode(' · ')
                    : null,
                'unit'      => $item->product?->unit?->code ?: 'pc',
                'expected'  => (string) $item->expected_quantity,
                'counted'   => $item->counted_quantity === null ? '' : (string) $item->counted_quantity,
                'notes'     => $item->notes ?: '',
            ];
        })->values();
    @endphp

    <div class="page-wide"
         x-data="stockTakeEditor({
             rows: @js($rows),
             scanUrl: @js(route('admin.inventory.stock-takes.scan')),
             labels: {
                 not_in_take:   @js(__('stock_takes.scan.not_in_take')),
                 counted:       @js(__('stock_takes.scan.counted')),
                 locate_prompt: @js(__('stock_takes.scan.locate_prompt')),
             },
         })">

        <form method="POST"
              action="{{ route('admin.inventory.stock-takes.update', $take) }}"
              data-ajax-form>
            @csrf
            @method('PATCH')

            <div class="page-header mb-6">
                <div class="flex items-start gap-3">
                    <a href="{{ route('admin.inventory.stock-takes.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('stock_takes.title') }}">
                        <x-icon name="back" class="w-4 h-4" />
                    </a>
                    <div>
                        <h1 class="page-title">{{ $take->number }}</h1>
                        <p class="page-sub">
                            <span>{{ $take->store?->name }}</span>
                            <span class="fg-tertiary">·</span>
                            <span>{{ format_date($take->take_date) }}</span>
                            <span class="fg-tertiary">·</span>
                            <span>{{ __('stock_takes.status.draft') }}</span>
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost"
                            @click="$store.confirm.show({
                                title:        {{ \Illuminate\Support\Js::from(__('stock_takes.confirm_delete.title', ['number' => $take->number])) }},
                                message:      {{ \Illuminate\Support\Js::from(__('stock_takes.confirm_delete.message')) }},
                                intent:       'danger',
                                confirmLabel: {{ \Illuminate\Support\Js::from(__('stock_takes.confirm_delete.confirm')) }},
                                onConfirm:    () => $deleteForm({{ \Illuminate\Support\Js::from(route('admin.inventory.stock-takes.destroy', $take)) }}),
                            })">
                        <x-icon name="trash" class="w-4 h-4" />
                        {{ __('stock_takes.actions.delete') }}
                    </button>
                    <button type="submit" class="pos-btn pos-btn-sm pos-btn-ghost">
                        <x-icon name="check" class="w-4 h-4" />
                        {{ __('stock_takes.actions.save_draft') }}
                    </button>
                    {{-- Post Count: must save the operator's unsaved
                         counts in the form FIRST, then post. We gather
                         FormData from the surrounding form and pass it
                         to $submitForm as extra fields (which appends
                         each one to the FormData posted to the post
                         URL). _method is stripped so it doesn't trip
                         Laravel's method-override into PATCH. --}}
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-primary"
                            :disabled="!hasAnyCount"
                            @click="$store.confirm.show({
                                title:        {{ \Illuminate\Support\Js::from(__('stock_takes.confirm_post.title', ['number' => $take->number])) }},
                                message:      {{ \Illuminate\Support\Js::from(__('stock_takes.confirm_post.message')) }},
                                intent:       'primary',
                                confirmLabel: {{ \Illuminate\Support\Js::from(__('stock_takes.actions.post')) }},
                                onConfirm:    () => {
                                    const form = $el.closest('form');
                                    const fd   = new FormData(form);
                                    fd.delete('_method');
                                    const fields = {};
                                    for (const [k, v] of fd.entries()) { fields[k] = v; }
                                    return $submitForm({{ \Illuminate\Support\Js::from(route('admin.inventory.stock-takes.post', $take)) }}, fields);
                                },
                            })">
                        <x-icon name="check" class="w-4 h-4" />
                        {{ __('stock_takes.actions.post') }}
                    </button>
                </div>
            </div>

            {{-- KPI strip: total / counted / variance lines / net variance --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
                <div class="card"><div class="card-body">
                    <div class="cust-kpi-label">{{ __('stock_takes.kpis.lines_total') }}</div>
                    <div class="cust-kpi-value num tnum">{{ $rows->count() }}</div>
                </div></div>
                <div class="card"><div class="card-body">
                    <div class="cust-kpi-label">{{ __('stock_takes.kpis.lines_counted') }}</div>
                    <div class="cust-kpi-value num tnum" x-text="countedLines"></div>
                </div></div>
                <div class="card"><div class="card-body">
                    <div class="cust-kpi-label">{{ __('stock_takes.kpis.lines_with_variance') }}</div>
                    <div class="cust-kpi-value num tnum" x-text="varianceLines"></div>
                </div></div>
                <div class="card"><div class="card-body">
                    <div class="cust-kpi-label">{{ __('stock_takes.kpis.net_variance') }}</div>
                    <div class="cust-kpi-value num tnum"
                         :class="netVariance < 0 ? 'text-amber-600 dark:text-amber-400' : (netVariance > 0 ? 'text-emerald-600 dark:text-emerald-400' : '')"
                         x-text="formatVariance(netVariance)"></div>
                </div></div>
            </div>

            {{-- Header header fields (name + notes + take_date hidden field if needed) --}}
            <div class="card mb-5">
                <div class="card-body">
                    <div class="form-stack-tight grid grid-cols-1 lg:grid-cols-2 gap-3">
                        <label class="field">
                            <span class="field-label">{{ __('stock_takes.fields.name') }}</span>
                            <input type="text" name="name" class="pos-input"
                                   maxlength="191"
                                   value="{{ $take->name }}">
                        </label>
                        <label class="field">
                            <span class="field-label">{{ __('stock_takes.fields.take_date') }}</span>
                            <input type="text" name="take_date" class="pos-input js-datepicker"
                                   value="{{ optional($take->take_date)->toDateString() }}">
                        </label>
                    </div>
                    <label class="field mt-3">
                        <span class="field-label">{{ __('stock_takes.fields.notes') }}</span>
                        <textarea name="notes" class="pos-input" rows="2" maxlength="1000">{{ $take->notes }}</textarea>
                    </label>
                </div>
            </div>

            {{-- Barcode scan. A take is a fixed snapshot, so scanning counts an
                 existing row. Two modes: locate-and-type (default) jumps to the
                 row + focuses its count field; +1-per-scan tallies loose items. --}}
            <div class="card card-pad-0 mb-4">
                <x-admin.barcode-scan-field :placeholder="__('stock_takes.scan.placeholder')" />

                <div class="inv-scan-modes">
                    <span class="inv-scan-modes-label">{{ __('stock_takes.scan.mode_label') }}</span>
                    <div class="seg" role="tablist" aria-label="{{ __('stock_takes.scan.mode_label') }}">
                        <button type="button"
                                role="tab"
                                :aria-selected="scanMode === 'locate'"
                                :class="{ 'is-active': scanMode === 'locate' }"
                                @click="scanMode = 'locate'">
                            {{ __('stock_takes.scan.mode_locate') }}
                        </button>
                        <button type="button"
                                role="tab"
                                :aria-selected="scanMode === 'increment'"
                                :class="{ 'is-active': scanMode === 'increment' }"
                                @click="scanMode = 'increment'">
                            {{ __('stock_takes.scan.mode_increment') }}
                        </button>
                    </div>
                </div>

            </div>

            {{-- Items editor. The data-table behaviour (search/sort/paginate) is
                 composed into stockTakeEditor, so no nested x-data here — that's
                 what lets a scan page the list to the matched row + focus it. --}}
            <div class="card card-pad-0">
                <div class="dt-toolbar">
                    <div class="dt-toolbar-title">{{ __('stock_takes.items_title') }} (<span x-text="rowCount">{{ $rows->count() }}</span>)</div>
                    <x-admin.dt-toolbar-actions />
                </div>

                @if ($rows->isEmpty())
                    <div class="dt-empty">
                        <span class="dt-empty-icon"><x-icon name="search" class="w-5 h-5" /></span>
                        <div class="dt-empty-title">{{ __('stock_takes.empty_lines.title') }}</div>
                        <div class="dt-empty-sub">{{ __('stock_takes.empty_lines.sub') }}</div>
                    </div>
                @else
                    <table class="dt-table">
                        <thead>
                            <tr>
                                <th>{{ __('stock_takes.items.product') }}</th>
                                <th class="num">{{ __('stock_takes.items.expected') }}</th>
                                <th class="num">{{ __('stock_takes.items.counted') }}</th>
                                <th class="num">{{ __('stock_takes.items.variance') }}</th>
                                <th>{{ __('stock_takes.items.notes') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(r, idx) in rows" :key="r.id">
                                <tr data-dt-row :data-dt-name="r.sku + ' ' + r.name" :data-line-uid="r.id">
                                    <td>
                                        <div class="font-medium" x-text="r.name"></div>
                                        <div class="fg-tertiary text-xs mono">
                                            <span x-text="r.sku"></span>
                                            <template x-if="r.variant">
                                                <span> · <span x-text="r.variant"></span></span>
                                            </template>
                                        </div>
                                    </td>
                                    <td class="num tnum"
                                        x-text="formatQty(r.expected) + ' ' + r.unit"></td>
                                    <td class="num">
                                        <input type="hidden" :name="'items[' + idx + '][id]'" :value="r.id">
                                        {{-- Enter returns focus to the scan bar so the next scan is
                                             safe (a scanner types into whatever field has focus). --}}
                                        <input type="number"
                                               class="pos-input refund-qty tnum"
                                               :name="'items[' + idx + '][counted_quantity]'"
                                               x-model.lazy="r.counted"
                                               @keydown.enter.prevent="$refs.scanInput?.focus()"
                                               step="any" min="0" max="99999999999.9999"
                                               placeholder="—">
                                    </td>
                                    <td class="num tnum">
                                        <span x-show="r.counted === ''" x-cloak class="fg-tertiary">—</span>
                                        <span x-show="r.counted !== ''" x-cloak
                                              :class="variance(r) < 0 ? 'text-amber-600 dark:text-amber-400 font-semibold' :
                                                      (variance(r) > 0 ? 'text-emerald-600 dark:text-emerald-400 font-semibold' :
                                                       'fg-tertiary')"
                                              x-text="formatVariance(variance(r))"></span>
                                    </td>
                                    <td>
                                        <input type="text"
                                               class="pos-input"
                                               :name="'items[' + idx + '][notes]'"
                                               x-model="r.notes"
                                               maxlength="191"
                                               placeholder="{{ __('stock_takes.items.notes_placeholder') }}">
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <x-admin.dt-pager />
                @endif
            </div>
        </form>
    </div>

</x-admin-layout>
