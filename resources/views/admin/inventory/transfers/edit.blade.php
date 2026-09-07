<x-admin-layout
    active="stock-transfers"
    :title="$transfer->exists ? $transfer->number : __('inventory.transfers.new')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('inventory.transfers.crumb_parent')],
        ['label' => __('inventory.transfers.title'), 'href' => route('admin.inventory.transfers.index')],
        ['label' => $transfer->exists ? $transfer->number : __('inventory.transfers.new')],
    ]">

    @php
        $isEdit = $transfer->exists;
        $action = $isEdit
            ? route('admin.inventory.transfers.update', $transfer)
            : route('admin.inventory.transfers.store');

        $existingItems = $isEdit ? $transfer->items->map(fn ($it) => [
            'product_id'         => $it->product_id,
            'variant_id'         => $it->variant_id,
            'product_label'      => trim(($it->product?->name ?: '').($it->variant ? ' — '.$it->variant->label : '')),
            'sku'                => $it->variant?->sku ?: $it->product?->sku,
            'requested_quantity' => (string) (float) $it->requested_quantity,
            'unit_cost'          => $it->unit_cost !== null ? (string) (float) $it->unit_cost : '',
        ])->values() : collect();
    @endphp

    <div class="page-wide"
         x-data="stockTransferEditor({
             searchUrl:      @js(route('admin.inventory.transfers.search-products')),
             stockLevelsUrl: @js($stockLevelsUrl),
             scanUrl:        @js(route('admin.inventory.transfers.scan')),
             fromStoreId:    @js($fromStoreId),
             items:          {{ Js::from($existingItems) }},
         })"
         @change="if ($event.target.name === 'from_store_id') onFromStoreChange($event.target.value)">

        <form method="POST" action="{{ $action }}" novalidate data-ajax-form
              @submit="validateOnSubmit($event)">
            @csrf
            @if ($isEdit) @method('PATCH') @endif

            <div class="page-header mb-6">
                <div class="flex items-start gap-3">
                    <a href="{{ route('admin.inventory.transfers.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('inventory.transfers.title') }}">
                        <x-icon name="back" class="w-4 h-4" />
                    </a>
                    <div>
                        <h1 class="page-title">
                            {{ $isEdit ? $transfer->number : __('inventory.transfers.new') }}
                        </h1>
                        <p class="page-sub">{{ __('inventory.transfers.sub') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ $isEdit ? route('admin.inventory.transfers.show', $transfer) : route('admin.inventory.transfers.index') }}"
                       class="pos-btn pos-btn-sm pos-btn-ghost">
                        {{ __('inventory.transfers.actions.discard') }}
                    </a>
                    <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                        <x-icon name="check" class="w-4 h-4" />
                        {{ $isEdit ? __('inventory.transfers.actions.update_draft') : __('inventory.transfers.actions.save_draft') }}
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">
                {{-- ── Left: header + line items ─────────────────────────── --}}
                <div class="space-y-5">
                    {{-- Header fields --}}
                    <div class="card">
                        <div class="card-body">
                            <div class="form-stack">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label class="field">
                                        <span class="field-label is-required">{{ __('inventory.transfers.fields.from_store') }}</span>
                                        <select name="from_store_id" class="pos-input" x-data="enhancedSelect()">
                                            @foreach ($stores as $store)
                                                <option value="{{ $store->id }}" @selected(old('from_store_id', $transfer->from_store_id) == $store->id)>
                                                    {{ $store->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('from_store_id')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                    <label class="field">
                                        <span class="field-label is-required">{{ __('inventory.transfers.fields.to_store') }}</span>
                                        <select name="to_store_id" class="pos-input" x-data="enhancedSelect()">
                                            <option value="">{{ __('inventory.transfers.filter.to_store_all') }}</option>
                                            @foreach ($stores as $store)
                                                <option value="{{ $store->id }}" @selected(old('to_store_id', $transfer->to_store_id) == $store->id)>
                                                    {{ $store->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('to_store_id')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label class="field">
                                        <span class="field-label is-required">{{ __('inventory.transfers.fields.transfer_date') }}</span>
                                        <input type="text" name="transfer_date"
                                               value="{{ old('transfer_date', optional($transfer->transfer_date)->toDateString()) }}"
                                               class="pos-input js-datepicker"
                                               placeholder="YYYY-MM-DD">
                                        @error('transfer_date')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                    <label class="field">
                                        <span class="field-label">{{ __('inventory.transfers.fields.expected_arrival_date') }}</span>
                                        <input type="text" name="expected_arrival_date"
                                               value="{{ old('expected_arrival_date', optional($transfer->expected_arrival_date)->toDateString()) }}"
                                               class="pos-input js-datepicker"
                                               placeholder="YYYY-MM-DD">
                                        <p class="field-help">{{ __('inventory.transfers.fields.expected_arrival_help') }}</p>
                                        @error('expected_arrival_date')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                </div>

                                <label class="field">
                                    <span class="field-label">{{ __('inventory.transfers.fields.notes') }}</span>
                                    <textarea name="notes" rows="3" class="pos-input" maxlength="1000">{{ old('notes', $transfer->notes) }}</textarea>
                                    <p class="field-help">{{ __('inventory.transfers.fields.notes_help') }}</p>
                                    @error('notes')<p class="field-error">{{ $message }}</p>@enderror
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Line items --}}
                    <div class="card card-pad-0">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('inventory.transfers.items.title') }}</div>
                            <div class="card-title-sub">{{ __('inventory.transfers.items.sub') }}</div>
                        </div></div>

                        {{-- Barcode scan — shared component (USB/Bluetooth wedge + F8 + camera). --}}
                        <x-admin.barcode-scan-field />

                        {{-- Product picker --}}
                        <div class="adj-picker" @click.outside="closeSearch()">
                            <span class="inv-search-icon"><x-icon name="search" class="w-4 h-4" /></span>
                            <input type="text"
                                   x-model="query"
                                   @input="onSearchInput()"
                                   @focus="focusSearch()"
                                   class="pos-input adj-picker-input"
                                   data-error-for="items"
                                   placeholder="{{ __('inventory.transfers.items.picker') }}">

                            <div class="adj-picker-dropdown" x-show="searchOpen" x-cloak>
                                <template x-if="searching">
                                    <div class="adj-picker-empty">{{ __('inventory.transfers.items.picker_empty') }}…</div>
                                </template>
                                <template x-if="!searching && results.length === 0">
                                    <div class="adj-picker-empty">{{ __('inventory.transfers.items.picker_empty') }}</div>
                                </template>
                                <template x-for="row in results" :key="row.value">
                                    <button type="button" class="adj-picker-row" @click="addProduct(row)">
                                        <span class="adj-picker-row-label" x-text="row.label"></span>
                                        <span class="adj-picker-row-sku mono" x-text="row.sku"></span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        {{-- Empty state --}}
                        <template x-if="items.length === 0">
                            <div class="dt-empty">
                                <span class="dt-empty-icon"><x-icon name="box" class="w-5 h-5" /></span>
                                <div class="dt-empty-title">{{ __('inventory.transfers.items.empty') }}</div>
                            </div>
                        </template>

                        {{-- Lines --}}
                        <template x-if="items.length > 0">
                            <table class="dt-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('inventory.transfers.items.col_product') }}</th>
                                        <th class="num">{{ __('inventory.transfers.items.col_qty') }}</th>
                                        <th class="num">{{ __('inventory.transfers.items.col_cost') }}</th>
                                        <th class="dt-actions-col"><span class="sr-only">{{ __('inventory.transfers.items.col_remove') }}</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(line, i) in items" :key="line._uid">
                                        <tr :data-line-uid="line._uid">
                                            <td>
                                                <div class="store-row-name-line">
                                                    <span class="store-row-name" x-text="line.product_label"></span>
                                                </div>
                                                <span class="mono fg-tertiary" x-text="line.sku"></span>
                                                <input type="hidden" :name="`items[${i}][product_id]`" :value="line.product_id">
                                                <input type="hidden" :name="`items[${i}][variant_id]`" :value="line.variant_id ?? ''">
                                            </td>
                                            <td class="num trf-line-qty" :class="{ 'has-error': isOverStock(line) }">
                                                <input type="number" step="0.0001" min="0.0001" max="99999999999.9999"
                                                       :name="`items[${i}][requested_quantity]`"
                                                       x-model="line.requested_quantity"
                                                       class="pos-input pos-input-sm tnum"
                                                       :class="{ 'has-error': isOverStock(line) }"
                                                       placeholder="0">
                                                <p class="field-help text-end"
                                                   x-show="line.available_qty !== null || line.qty_loading"
                                                   :class="{ 'fg-danger': isOverStock(line) }"
                                                   x-text="line.qty_loading
                                                       ? @js(__('inventory.transfers.items.checking_stock'))
                                                       : isOverStock(line)
                                                           ? @js(__('inventory.transfers.items.only_available')) + ' ' + $formatQty(line.available_qty)
                                                           : @js(__('inventory.transfers.items.on_hand')) + ': ' + $formatQty(line.available_qty)">
                                                </p>
                                            </td>
                                            <td class="num">
                                                <input type="number" step="0.0001" min="0"
                                                       :name="`items[${i}][unit_cost]`"
                                                       x-model="line.unit_cost"
                                                       class="pos-input pos-input-sm tnum">
                                            </td>
                                            <td>
                                                <div class="prod-row-actions">
                                                    <button type="button" class="prod-del-btn"
                                                            @click="removeLine(line._uid)"
                                                            aria-label="{{ __('inventory.transfers.items.col_remove') }}">
                                                        <x-icon name="trash" class="w-4 h-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </template>
                    </div>
                </div>

                {{-- ── Right: summary ─────────────────────────────────────── --}}
                <div class="space-y-5 lg:sticky lg:top-4">
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('inventory.transfers.items.title') }}</div>
                        </div></div>
                        <div class="card-body">
                            <div class="adj-totals">
                                <div>
                                    <span class="adj-totals-label">{{ __('inventory.transfers.items.total_items') }}</span>
                                    <span class="adj-totals-value tnum" x-text="totalItems"></span>
                                </div>
                                <div class="adj-totals-net">
                                    <span class="adj-totals-label">{{ __('inventory.transfers.items.total_qty') }}</span>
                                    <span class="adj-totals-value tnum"
                                          x-text="totalQty.toLocaleString(undefined, { maximumFractionDigits: 4 })"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>
