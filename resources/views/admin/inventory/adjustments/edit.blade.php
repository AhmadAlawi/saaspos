<x-admin-layout
    active="stock-adjustments"
    :title="$adjustment->exists ? $adjustment->number : __('inventory.adjustments.new')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('inventory.adjustments.crumb_parent')],
        ['label' => __('inventory.adjustments.title'), 'href' => route('admin.inventory.adjustments.index')],
        ['label' => $adjustment->exists ? $adjustment->number : __('inventory.adjustments.new')],
    ]">

    @php
        $isEdit  = $adjustment->exists;
        $action  = $isEdit
            ? route('admin.inventory.adjustments.update', $adjustment)
            : route('admin.inventory.adjustments.store');

        // Pre-existing draft lines surface to the Alpine editor as JSON.
        $existingItems = $isEdit ? $adjustment->items->map(fn ($it) => [
            'product_id'     => $it->product_id,
            'variant_id'     => $it->variant_id,
            'product_label'  => trim(($it->product?->name ?: '').($it->variant ? ' — '.$it->variant->label : '')),
            'sku'            => $it->variant?->sku ?: $it->product?->sku,
            'quantity_delta' => (string) (float) $it->quantity_delta,
            'unit_cost'      => $it->unit_cost !== null ? (string) (float) $it->unit_cost : '',
            'notes'          => $it->notes ?? '',
            'track_batches'    => (bool) $it->product?->track_batches,
            'batch_id'         => $it->batch_id,
            // For an existing pick the label comes off the batch relation;
            // for an in-progress new batch it lives on the line itself.
            'batch_number'     => $it->batch_id ? ($it->batch?->batch_number ?? '') : ($it->batch_number ?? ''),
            'manufacture_date' => $it->manufacture_date?->toDateString() ?? '',
            'expiry_date'      => $it->expiry_date?->toDateString() ?? '',
            'creating_batch'   => $it->batch_id === null && filled($it->batch_number),
        ])->values() : collect();

        $initStoreId = old('store_id', $adjustment->store_id ?? $stores->first()?->id ?? '');
    @endphp

    <div class="page-wide"
         x-data="stockAdjustmentEditor({
             searchUrl:  @js(route('admin.inventory.adjustments.search-products')),
             batchesUrl: @js(route('admin.inventory.adjustments.batches')),
             stockUrl:   @js(route('admin.inventory.adjustments.product-stock')),
             scanUrl:    @js(route('admin.inventory.adjustments.scan')),
             storeId:    @js($initStoreId),
             items:      {{ Js::from($existingItems) }},
         })"
         @change.capture="$event.target.name === 'store_id' && setStoreId($event.target.value)">

        {{-- AJAX via lib/ajax-form.js. --}}
        <form method="POST" action="{{ $action }}" novalidate data-ajax-form>
            @csrf
            @if ($isEdit) @method('PATCH') @endif

            <div class="page-header mb-6">
                <div class="flex items-start gap-3">
                    <a href="{{ route('admin.inventory.adjustments.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('inventory.adjustments.title') }}">
                        <x-icon name="back" class="w-4 h-4" />
                    </a>
                    <div>
                        <h1 class="page-title">
                            {{ $isEdit ? $adjustment->number : __('inventory.adjustments.new') }}
                        </h1>
                        <p class="page-sub">{{ __('inventory.adjustments.sub') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ $isEdit ? route('admin.inventory.adjustments.show', $adjustment) : route('admin.inventory.adjustments.index') }}"
                       class="pos-btn pos-btn-sm pos-btn-ghost">
                        {{ __('inventory.adjustments.actions.discard') }}
                    </a>
                    <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                        <x-icon name="check" class="w-4 h-4" />
                        {{ $isEdit ? __('inventory.adjustments.actions.update_draft') : __('inventory.adjustments.actions.save_draft') }}
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start">
                {{-- ── Left: header + line items ─────────────────────────── --}}
                <div class="space-y-5">
                    {{-- Header fields --}}
                    <div class="card">
                        <div class="card-body">
                            <div class="form-stack">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label class="field">
                                        <span class="field-label is-required">{{ __('inventory.adjustments.fields.store') }}</span>
                                        <select name="store_id" class="pos-input" x-data="enhancedSelect()">
                                            @foreach ($stores as $store)
                                                <option value="{{ $store->id }}" @selected(old('store_id', $adjustment->store_id) == $store->id)>
                                                    {{ $store->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('store_id')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                    <label class="field">
                                        <span class="field-label is-required">{{ __('inventory.adjustments.fields.adjustment_date') }}</span>
                                        <input type="text" name="adjustment_date"
                                               value="{{ old('adjustment_date', optional($adjustment->adjustment_date)->toDateString()) }}"
                                               class="pos-input js-datepicker"
                                               placeholder="YYYY-MM-DD">
                                        @error('adjustment_date')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                </div>

                                <label class="field">
                                    <span class="field-label">{{ __('inventory.adjustments.fields.reason_code') }}</span>
                                    <select name="reason_code_id" class="pos-input" x-data="enhancedSelect()">
                                        <option value="">{{ __('inventory.adjustments.fields.reason_code_ph') }}</option>
                                        @foreach ($reasons as $r)
                                            <option value="{{ $r->id }}" @selected(old('reason_code_id', $adjustment->reason_code_id) == $r->id)>{{ $r->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('reason_code_id')<p class="field-error">{{ $message }}</p>@enderror
                                </label>

                                <label class="field">
                                    <span class="field-label">{{ __('inventory.adjustments.fields.reason') }}</span>
                                    <input type="text" name="reason" value="{{ old('reason', $adjustment->reason) }}" class="pos-input" maxlength="191">
                                    <p class="field-help">{{ __('inventory.adjustments.fields.reason_help') }}</p>
                                    @error('reason')<p class="field-error">{{ $message }}</p>@enderror
                                </label>

                                <label class="field">
                                    <span class="field-label">{{ __('inventory.adjustments.fields.notes') }}</span>
                                    <textarea name="notes" rows="3" class="pos-input" maxlength="1000">{{ old('notes', $adjustment->notes) }}</textarea>
                                    <p class="field-help">{{ __('inventory.adjustments.fields.notes_help') }}</p>
                                    @error('notes')<p class="field-error">{{ $message }}</p>@enderror
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Line items --}}
                    <div class="card card-pad-0">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('inventory.adjustments.items.title') }}</div>
                            <div class="card-title-sub">{{ __('inventory.adjustments.items.sub') }}</div>
                        </div></div>

                        {{-- Barcode scan — shared component (USB/Bluetooth wedge + F8 + camera). --}}
                        <x-admin.barcode-scan-field :placeholder="__('inventory.adjustments.scan.placeholder')" />

                        {{-- Product picker --}}
                        <div class="adj-picker" @click.outside="closeSearch()">
                            <span class="inv-search-icon"><x-icon name="search" class="w-4 h-4" /></span>
                            <input type="text"
                                   x-model="query"
                                   @input="onSearchInput()"
                                   @focus="focusSearch()"
                                   class="pos-input adj-picker-input"
                                   placeholder="{{ __('inventory.adjustments.items.picker') }}">

                            <div class="adj-picker-dropdown" x-show="searchOpen" x-cloak>
                                <template x-if="searching">
                                    <div class="adj-picker-empty">{{ __('inventory.adjustments.items.picker_empty') }}…</div>
                                </template>
                                <template x-if="!searching && results.length === 0">
                                    <div class="adj-picker-empty">{{ __('inventory.adjustments.items.picker_empty') }}</div>
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
                                <div class="dt-empty-title">{{ __('inventory.adjustments.items.empty') }}</div>
                            </div>
                        </template>

                        {{-- Lines. Wrapped in .dt-scroll so the 8-column table
                             scrolls horizontally on laptop/tablet/phone instead
                             of overflowing the card (system-wide table pattern). --}}
                        <template x-if="items.length > 0">
                            <div class="dt-scroll">
                            <table class="dt-table adj-line-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('inventory.adjustments.items.col_product') }}</th>
                                        <th class="num">{{ __('inventory.adjustments.items.col_available') }}</th>
                                        <th>{{ __('inventory.adjustments.items.col_direction') }}</th>
                                        <th class="num">{{ __('inventory.adjustments.items.col_qty') }}</th>
                                        <th class="num">{{ __('inventory.adjustments.items.col_cost') }}</th>
                                        <th>{{ __('inventory.adjustments.items.col_batch') }}</th>
                                        <th>{{ __('inventory.adjustments.items.col_notes') }}</th>
                                        <th class="dt-actions-col"><span class="sr-only">{{ __('inventory.adjustments.items.col_remove') }}</span></th>
                                    </tr>
                                </thead>
                                {{-- One <tbody> per line so a batch sub-row can sit
                                     directly beneath its parent line (x-for needs a
                                     single root element). --}}
                                <template x-for="(line, i) in items" :key="line._uid">
                                    <tbody class="adj-line-group" :data-line-uid="line._uid">
                                        <tr>
                                            <td>
                                                <div class="store-row-name-line">
                                                    <span class="store-row-name" x-text="line.product_label"></span>
                                                </div>
                                                <span class="mono fg-tertiary" x-text="line.sku"></span>
                                                <input type="hidden" :name="`items[${i}][product_id]`" :value="line.product_id">
                                                <input type="hidden" :name="`items[${i}][variant_id]`" :value="line.variant_id ?? ''">
                                                <input type="hidden" :name="`items[${i}][batch_id]`" :value="line.batch_id ?? ''">
                                                {{-- Signed delta reassembled from direction + magnitude. --}}
                                                <input type="hidden" :name="`items[${i}][quantity_delta]`" :value="signedDeltaFor(line)">
                                            </td>
                                            <td class="num tnum">
                                                <template x-if="line.available !== null && line.available !== undefined">
                                                    <span :class="{ 'inv-delta-neg': isOverDrawn(line) || isOversold(line) }" x-text="formatQty(line.available)"></span>
                                                </template>
                                                <template x-if="line.available === null || line.available === undefined">
                                                    <span class="fg-tertiary">—</span>
                                                </template>
                                                {{-- Oversold warning: this product's on-hand is negative. An
                                                     In line's entered qty nets the oversold amount off first. --}}
                                                <template x-if="isOversold(line)">
                                                    <span class="inv-oversold-note"
                                                          x-text="line.direction === 'in'
                                                              ? @js(__('inventory.adjustments.oversold_in'))
                                                              : @js(__('inventory.adjustments.oversold'))"
                                                          :title="@js(__('inventory.adjustments.oversold')) + ' ' + formatQty(oversoldBy(line))"></span>
                                                </template>
                                            </td>
                                            <td>
                                                {{-- Two-button direction toggle — avoids needing the
                                                     user to type a minus sign in a number input (system
                                                     convention blocks negatives). --}}
                                                <div class="adj-dir" role="group">
                                                    <button type="button"
                                                            class="adj-dir-btn adj-dir-in"
                                                            :class="{ 'is-active': line.direction === 'in' }"
                                                            :aria-pressed="line.direction === 'in'"
                                                            @click="setDirection(line, 'in')">
                                                        <x-icon name="plus" class="w-3.5 h-3.5" />
                                                        {{ __('inventory.adjustments.items.dir_in') }}
                                                    </button>
                                                    <button type="button"
                                                            class="adj-dir-btn adj-dir-out"
                                                            :class="{ 'is-active': line.direction === 'out' }"
                                                            :aria-pressed="line.direction === 'out'"
                                                            @click="setDirection(line, 'out')">
                                                        <x-icon name="minus" class="w-3.5 h-3.5" />
                                                        {{ __('inventory.adjustments.items.dir_out') }}
                                                    </button>
                                                </div>
                                            </td>
                                            <td class="num adj-line-qty">
                                                {{-- Visible field is the unsigned `quantity_abs`; the signed
                                                     `quantity_delta` submits via a hidden input. `data-error-for`
                                                     routes the server's quantity_delta error onto THIS field. --}}
                                                <input type="number" step="0.0001" min="0" max="99999999999.9999"
                                                       x-model="line.quantity_abs"
                                                       :data-error-for="`items[${i}][quantity_delta]`"
                                                       class="pos-input pos-input-sm tnum"
                                                       placeholder="0">
                                            </td>
                                            <td class="num adj-line-cost">
                                                <x-admin.money-input
                                                    x-bind:name="`items[${i}][unit_cost]`"
                                                    x-model="line.unit_cost"
                                                    class="pos-input-sm" />
                                            </td>
                                            <td class="adj-line-batch">
                                                <template x-if="line.track_batches">
                                                    <div class="adj-batch-cell" @click.outside="isBatchPickerOpen(line) && closeBatchPicker()">
                                                        <div class="adj-batch-trigger">
                                                            <button type="button"
                                                                    class="adj-batch-btn"
                                                                    :class="{ 'is-set': line.batch_id || line.creatingBatch }"
                                                                    @click="openBatchPicker(line)">
                                                                <span x-text="line.batch_id
                                                                        ? line.batch_number
                                                                        : (line.creatingBatch
                                                                            ? '{{ __('inventory.adjustments.items.batch_new_prefix') }}' + (line.batch_number || '{{ __('inventory.adjustments.items.batch_new_unnamed') }}')
                                                                            : '{{ __('inventory.adjustments.items.batch_add') }}')"></span>
                                                                <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                                                            </button>
                                                            <template x-if="line.batch_id || line.creatingBatch">
                                                                <button type="button" class="adj-batch-clear" @click.stop="clearBatch(line)"
                                                                        aria-label="{{ __('inventory.adjustments.items.batch_clear') }}">
                                                                    <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                                                                </button>
                                                            </template>
                                                        </div>
                                                        <div class="adj-batch-dropdown" x-show="isBatchPickerOpen(line)" x-cloak>
                                                            {{-- Create-new is offered on inflow lines only — you can't
                                                                 draw stock from a batch that doesn't exist yet. --}}
                                                            <template x-if="line.direction === 'in'">
                                                                <button type="button" class="adj-batch-row adj-batch-new" @click="startNewBatch(line)">
                                                                    <span class="adj-batch-new-plus">+</span>
                                                                    <span>{{ __('inventory.adjustments.items.batch_create_new') }}</span>
                                                                </button>
                                                            </template>
                                                            <template x-if="batchPickerLoading">
                                                                <div class="adj-batch-empty">{{ __('inventory.adjustments.items.batch_loading') }}</div>
                                                            </template>
                                                            <template x-if="!batchPickerLoading && batchPickerResults.length === 0">
                                                                <div class="adj-batch-empty">{{ __('inventory.adjustments.items.batch_none') }}</div>
                                                            </template>
                                                            <template x-for="batch in batchPickerResults" :key="batch.id">
                                                                <button type="button" class="adj-batch-row" @click="pickBatch(line, batch)">
                                                                    <span class="adj-batch-row-num" x-text="batch.batch_number"></span>
                                                                    <span class="adj-batch-row-meta">
                                                                        <template x-if="batch.expiry_date">
                                                                            <span x-text="'{{ __('inventory.adjustments.items.batch_exp') }} ' + batch.expiry_date"></span>
                                                                        </template>
                                                                        <span x-text="batch.on_hand + ' {{ __('inventory.adjustments.items.batch_on_hand') }}'"></span>
                                                                    </span>
                                                                </button>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </template>
                                                <template x-if="!line.track_batches">
                                                    <span class="fg-tertiary text-sm">—</span>
                                                </template>
                                            </td>
                                            <td class="adj-line-notes">
                                                <input type="text" maxlength="191"
                                                       :name="`items[${i}][notes]`"
                                                       x-model="line.notes"
                                                       class="pos-input pos-input-sm">
                                            </td>
                                            <td>
                                                <div class="prod-row-actions">
                                                    <button type="button" class="prod-del-btn"
                                                            @click="removeLine(line._uid)"
                                                            aria-label="{{ __('inventory.adjustments.items.col_remove') }}">
                                                        <x-icon name="trash" class="w-4 h-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        {{-- New-batch capture sub-row. Spans the table; the
                                             inputs only carry a `name` while creatingBatch is
                                             true, so they never submit for existing-batch picks.
                                             The batch row itself is materialised at post time. --}}
                                        <tr x-show="line.creatingBatch" x-cloak class="adj-line-batch-row">
                                            <td colspan="8">
                                                <div class="adj-newbatch">
                                                    <span class="adj-newbatch-tag">{{ __('inventory.adjustments.items.new_batch_title') }}</span>
                                                    <label class="field adj-newbatch-field">
                                                        <span class="field-label">{{ __('inventory.adjustments.items.batch_number_label') }}</span>
                                                        <input type="text" maxlength="64"
                                                               :name="line.creatingBatch ? `items[${i}][batch_number]` : ''"
                                                               x-model="line.batch_number"
                                                               class="pos-input pos-input-sm mono"
                                                               placeholder="{{ __('inventory.adjustments.items.batch_number_placeholder') }}">
                                                    </label>
                                                    <label class="field adj-newbatch-field">
                                                        <span class="field-label">{{ __('inventory.adjustments.items.manufacture_date') }}</span>
                                                        <input type="text"
                                                               :name="line.creatingBatch ? `items[${i}][manufacture_date]` : ''"
                                                               x-model="line.manufacture_date"
                                                               class="pos-input pos-input-sm js-datepicker">
                                                    </label>
                                                    <label class="field adj-newbatch-field">
                                                        <span class="field-label">{{ __('inventory.adjustments.items.expiry_date') }}</span>
                                                        <input type="text"
                                                               :name="line.creatingBatch ? `items[${i}][expiry_date]` : ''"
                                                               x-model="line.expiry_date"
                                                               class="pos-input pos-input-sm js-datepicker">
                                                    </label>
                                                    <button type="button" class="adj-newbatch-cancel" @click="cancelNewBatch(line)">
                                                        {{ __('inventory.adjustments.items.new_batch_cancel') }}
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </template>
                            </table>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- ── Right: sticky totals + post button ────────────────── --}}
                <div class="space-y-5 lg:sticky lg:top-4">
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('inventory.adjustments.items.title') }}</div>
                        </div></div>
                        <div class="card-body">
                            <div class="adj-totals">
                                <div>
                                    <span class="adj-totals-label">{{ __('inventory.adjustments.items.total_in') }}</span>
                                    <span class="adj-totals-value inv-delta-pos tnum"
                                          x-text="totalIn.toLocaleString(undefined, { maximumFractionDigits: 4 })"></span>
                                </div>
                                <div>
                                    <span class="adj-totals-label">{{ __('inventory.adjustments.items.total_out') }}</span>
                                    <span class="adj-totals-value inv-delta-neg tnum"
                                          x-text="totalOut.toLocaleString(undefined, { maximumFractionDigits: 4 })"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>

