<x-admin-layout
    active="purchases"
    :title="$purchase->exists ? $purchase->number : __('purchases.new')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('purchases.crumb_parent')],
        ['label' => __('purchases.title'), 'href' => route('admin.purchases.index')],
        ['label' => $purchase->exists ? $purchase->number : __('purchases.new')],
    ]">

    @php
        $isEdit = $purchase->exists;
        $action = $isEdit
            ? route('admin.purchases.update', $purchase)
            : route('admin.purchases.store');

        // Shape the items array for the Alpine factory. Server-side
        // discount/tax come back as decimal:4 strings — preserved as-is.
        $itemsPayload = $isEdit
            ? $purchase->items->map(fn ($i) => [
                'product_id'       => (string) $i->product_id,
                'variant_id'       => $i->variant_id ? (string) $i->variant_id : '',
                'product_label'    => $i->product?->name.' ('.$i->product?->sku.')',
                'sku'              => $i->product?->sku,
                'quantity'         => (string) $i->quantity,
                'unit_cost'        => (string) $i->unit_cost,
                'discount_percent' => (string) $i->discount_percent,
                'tax_group_id'     => $i->tax_group_id ? (string) $i->tax_group_id : '',
                'track_batches'    => (bool) ($i->product?->track_batches ?? false),
                'track_expiry'     => (bool) ($i->product?->track_expiry  ?? false),
                'batch_number'     => $i->batch_number,
                'manufacture_date' => $i->manufacture_date?->toDateString(),
                'expiry_date'      => $i->expiry_date?->toDateString(),
            ])->values()
            : collect();
    @endphp

    <div class="page-wide"
         x-data="purchaseForm({
             searchUrl:            '{{ route('admin.products.search') }}',
             scanUrl:              '{{ route('admin.purchases.scan') }}',
             pricesUrl:            '{{ route('admin.purchases.prices') }}',
             labels:               {{ Js::from([
                                       'markupTitle' => __('purchases.line.markup_title'),
                                       'markupBelow' => __('purchases.line.markup_below'),
                                       'markupLoss'  => __('purchases.line.markup_loss'),
                                       'priceUnset'  => __('purchases.line.price_unset'),
                                   ]) }},
             items:                {{ Js::from($itemsPayload) }},
             suppliers:            {{ Js::from($suppliers->map(fn ($s) => [
                                       'id'                    => (string) $s->id,
                                       'default_currency_code' => $s->default_currency_code,
                                       'payment_terms_days'    => $s->payment_terms_days,
                                   ])->values()) }},
             taxGroups:            {{ Js::from($taxGroups->map(fn ($g) => [
                                       'id'         => (string) $g->id,
                                       'components' => $g->components->map(fn ($c) => [
                                           'rate' => (float) $c->rate,
                                       ])->values(),
                                   ])->values()) }},
             initialSupplierId:    {{ Js::from(old('supplier_id',             $purchase->supplier_id)) }},
             initialStoreId:       {{ Js::from(old('store_id',                $purchase->store_id)) }},
             initialPurchaseDate:  {{ Js::from(old('purchase_date',           optional($purchase->purchase_date)->toDateString())) }},
             initialDueDate:       {{ Js::from(old('due_date',                optional($purchase->due_date)->toDateString())) }},
             initialCurrency:      {{ Js::from(old('currency_code',           $purchase->currency_code)) }},
             initialInvoiceNumber: {{ Js::from(old('supplier_invoice_number', $purchase->supplier_invoice_number)) }},
             initialNotes:         {{ Js::from(old('notes',                   $purchase->notes)) }},
             baseCurrency:         '{{ $baseCurrency }}',
         })">
        {{-- AJAX via lib/ajax-form.js — see http-client memory rule. --}}
        <form method="POST" action="{{ $action }}" novalidate data-ajax-form>
            @csrf
            @if ($isEdit) @method('PATCH') @endif

            <div class="page-header mb-6">
                <div class="flex items-start gap-3">
                    <a href="{{ route('admin.purchases.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('purchases.title') }}">
                        <x-icon name="back" class="w-4 h-4" />
                    </a>
                    <div>
                        <h1 class="page-title">{{ $isEdit ? $purchase->number : __('purchases.new') }}</h1>
                        <p class="page-sub">{{ __('purchases.sub') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ $isEdit ? route('admin.purchases.show', $purchase) : route('admin.purchases.index') }}"
                       class="pos-btn pos-btn-sm pos-btn-ghost">
                        {{ __('purchases.actions.discard') }}
                    </a>
                    <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                        <x-icon name="check" class="w-4 h-4" />
                        {{ $isEdit ? __('purchases.actions.save') : __('purchases.actions.save_draft') }}
                    </button>
                </div>
            </div>

            {{-- Two-column shell: header + items on the left, totals + notes on the right. --}}
            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start">
                <div class="space-y-5 min-w-0">
                    {{-- Header --}}
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('purchases.sections.header') }}</div>
                            <div class="card-title-sub">{{ __('purchases.sections.header_sub') }}</div>
                        </div></div>
                        <div class="card-body">
                            <div class="form-stack">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label class="field">
                                        <span class="field-label is-required">{{ __('purchases.fields.supplier') }}</span>
                                        <select name="supplier_id" class="pos-input"
                                                x-data="enhancedSelect()"
                                                x-model="supplierId"
                                                x-effect="onSupplierChange(supplierId)"
                                                required>
                                            <option value="">—</option>
                                            @foreach ($suppliers as $s)
                                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('supplier_id')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                    <label class="field">
                                        <span class="field-label is-required">{{ __('purchases.fields.store') }}</span>
                                        <select name="store_id" class="pos-input"
                                                x-data="enhancedSelect()" x-model="storeId" required>
                                            <option value="">—</option>
                                            @foreach ($stores as $st)
                                                <option value="{{ $st->id }}">{{ $st->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('store_id')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <label class="field">
                                        <span class="field-label is-required">{{ __('purchases.fields.purchase_date') }}</span>
                                        <input type="text" name="purchase_date" x-model="purchaseDate"
                                               class="pos-input js-datepicker" required>
                                        @error('purchase_date')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                    <label class="field">
                                        <span class="field-label">{{ __('purchases.fields.due_date') }}</span>
                                        <input type="text" name="due_date" x-model="dueDate" x-ref="dueDateInput"
                                               class="pos-input js-datepicker">
                                        <p class="field-help">{{ __('purchases.fields.due_date_help') }}</p>
                                        @error('due_date')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                    <label class="field">
                                        <span class="field-label">{{ __('purchases.fields.supplier_invoice') }}</span>
                                        <input type="text" name="supplier_invoice_number" x-model="invoiceNumber"
                                               class="pos-input mono" maxlength="64">
                                        @error('supplier_invoice_number')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                </div>
                                <label class="field">
                                    <span class="field-label is-required">{{ __('purchases.fields.currency') }}</span>
                                    <select name="currency_code" class="pos-input"
                                            x-data="enhancedSelect()"
                                            x-model="currencyCode"
                                            x-effect="ts && ts.setValue(currencyCode ?? '', true)"
                                            @change="onCurrencyChange()" required>
                                        <option value="">—</option>
                                        @foreach ($currencies as $cur)
                                            <option value="{{ $cur->code }}">{{ $cur->code }} — {{ $cur->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('currency_code')<p class="field-error">{{ $message }}</p>@enderror
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Items --}}
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('purchases.sections.items') }}</div>
                            <div class="card-title-sub">{{ __('purchases.sections.items_sub') }}</div>
                        </div></div>
                        <div class="card-body">
                            {{-- Barcode scan — shared component (USB/Bluetooth
                                 wedge + F2 + camera). Scanning an item already on
                                 the order bumps its quantity instead of adding a
                                 second line. --}}
                            <x-admin.barcode-scan-field
                                :placeholder="__('purchases.scan.placeholder')"
                                :camera-hint="__('purchases.scan.camera_hint')" />

                            {{-- Product picker --}}
                            <div class="pur-picker" @click.outside="closeSearch()">
                                <label class="field">
                                    <span class="field-label">{{ __('purchases.fields.add_product') }}</span>
                                    <input type="search" x-model="query" @input="onSearchInput()"
                                           @focus="focusSearch()" class="pos-input"
                                           placeholder="{{ __('purchases.fields.add_product_placeholder') }}"
                                           data-no-validate data-no-live-search>
                                </label>
                                <div class="pur-picker-results" x-show="searchOpen" x-cloak>
                                    <template x-if="searching">
                                        <div class="pur-picker-row pur-picker-empty">{{ __('purchases.picker.searching') }}</div>
                                    </template>
                                    <template x-if="!searching && results.length === 0">
                                        <div class="pur-picker-row pur-picker-empty">{{ __('purchases.picker.empty') }}</div>
                                    </template>
                                    <template x-for="row in results" :key="row.value">
                                        <div>
                                            <button type="button" class="pur-picker-row"
                                                    @click="addProduct(row)">
                                                <span x-text="row.label"></span>
                                            </button>
                                            <template x-for="v in row.variants" :key="v.id">
                                                <button type="button" class="pur-picker-row pur-picker-variant"
                                                        @click="addProduct(row, v)">
                                                    <span>↳ <span x-text="v.label"></span></span>
                                                </button>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            {{-- Cost and selling price are in different currencies on a
                                 foreign-currency PO, and this form captures no exchange rate,
                                 so a markup figure would be noise rather than an estimate.
                                 Said once here, not repeated on every line. --}}
                            <template x-if="pricesUnavailableFx">
                                <div class="pur-price-fx"
                                     x-text="{{ Js::from(__('purchases.line.price_fx')) }}.replace(':currency', currencyCode).replace(':base', {{ Js::from($baseCurrency) }})"></div>
                            </template>

                            {{-- Lines table --}}
                            <div class="dt-scroll">
                            <table class="dt-table pur-lines dt-table--lines" x-show="items.length > 0">
                                <thead>
                                    <tr>
                                        <th>{{ __('purchases.columns.product') }}</th>
                                        <th class="num">{{ __('purchases.columns.qty') }}</th>
                                        <th class="num">{{ __('purchases.columns.unit_cost') }}</th>
                                        <th class="num">{{ __('purchases.columns.discount') }}</th>
                                        <th>{{ __('purchases.columns.tax_group') }}</th>
                                        <th class="num">{{ __('purchases.columns.line_total') }}</th>
                                        <th class="dt-actions-col"><span class="sr-only">{{ __('purchases.columns.actions') }}</span></th>
                                    </tr>
                                </thead>
                                {{-- Each line is its own `<tbody>` so the main row stays
                                     a normal single-line row (all cells align cleanly),
                                     and the optional batch sub-row sits visually
                                     under it for `track_batches` products. A table
                                     may contain multiple tbodies; Alpine's x-for
                                     needs a single root element, so a tbody-per-line
                                     is the canonical pattern here. --}}
                                <template x-for="(line, i) in items" :key="line._uid">
                                    <tbody>
                                        <tr :data-line-uid="line._uid">
                                            <td>
                                                <div class="pur-line-product">
                                                    <span x-text="line.product_label || @js(__('purchases.line.unnamed_product'))"></span>
                                                    <template x-if="line.track_batches">
                                                        <span class="pur-line-batch-chip">{{ __('purchases.line.batch_chip') }}</span>
                                                    </template>
                                                </div>

                                                {{-- Read-only price context: what we sell this for vs. what
                                                     we're paying. Sibling of .pur-line-product (which is a
                                                     flex row) so it stacks under the name. Nothing here is
                                                     an input — the PO never reprices the shelf; see
                                                     docs/features/suppliers-purchases.md §5.7. --}}
                                                <template x-if="linePrices(line)">
                                                    <div class="pur-line-price">
                                                        <span>{{ __('purchases.line.sells') }}
                                                            <span class="val" x-text="formatBaseMoney(linePrices(line).selling_price)"></span>
                                                        </span>
                                                        <span class="sep">·</span>
                                                        <span>{{ __('purchases.line.mrp') }}
                                                            <span class="val" x-text="formatBaseMoney(linePrices(line).mrp)"></span>
                                                        </span>
                                                        <template x-if="lineTargetMarkup(line) !== null">
                                                            <span class="sep">·</span>
                                                        </template>
                                                        <template x-if="lineTargetMarkup(line) !== null">
                                                            <span x-text="{{ Js::from(__('purchases.line.target_markup')) }}.replace(':percent', formatPercent(lineTargetMarkup(line)))"></span>
                                                        </template>
                                                        <template x-if="lineMarkup(line) !== null">
                                                            <span class="pur-mk" :class="markupTone(line)" :title="markupTitle(line)"
                                                                  x-text="formatPercent(lineMarkup(line)) + '%'"></span>
                                                        </template>
                                                    </div>
                                                </template>

                                                <input type="hidden" :name="`items[${i}][product_id]`" :value="line.product_id">
                                                <input type="hidden" :name="`items[${i}][variant_id]`" :value="line.variant_id">
                                            </td>
                                            <td class="pur-line-qty">
                                                {{-- step=1 so the keyboard up/down arrow buttons increment
                                                     by whole units — clerks key purchase qty in whole
                                                     units >99% of the time. Decimal qty (weighed items,
                                                     bulk units) is still accepted via direct typing;
                                                     the DECIMAL(15,4) column carries it through.

                                                     min=0 (not 0.0001) so the step ANCHOR is 0 — the
                                                     spec validates each step from min, so min=0.0001
                                                     was producing 1.0001 / 2.0001 instead of round
                                                     integers when the user clicked the spinner. --}}
                                                <input type="number" step="1" min="0"
                                                       :name="`items[${i}][quantity]`"
                                                       x-model="line.quantity"
                                                       class="pos-input tnum pos-input-compact"
                                                       data-no-validate required>
                                            </td>
                                            <td>
                                                <input type="number" step="0.0001" min="0"
                                                       :name="`items[${i}][unit_cost]`"
                                                       x-model="line.unit_cost"
                                                       class="pos-input tnum pos-input-compact"
                                                       data-no-validate required>
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" min="0" max="100"
                                                       :name="`items[${i}][discount_percent]`"
                                                       x-model="line.discount_percent"
                                                       class="pos-input tnum pos-input-compact"
                                                       data-no-validate>
                                            </td>
                                            <td>
                                                <select :name="`items[${i}][tax_group_id]`"
                                                        x-data="enhancedSelect({ value: line.tax_group_id })"
                                                        x-model="line.tax_group_id"
                                                        class="pos-input pos-input-compact">
                                                    <option value="">—</option>
                                                    @foreach ($taxGroups as $tg)
                                                        <option value="{{ $tg->id }}">{{ $tg->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="num tnum">
                                                <span x-text="formatMoney(lineTotal(line))"></span>
                                            </td>
                                            <td>
                                                <div class="prod-row-actions">
                                                    <button type="button" class="prod-default-btn"
                                                            @click="duplicateLine(line._uid)"
                                                            title="{{ __('purchases.actions.duplicate_line') }}"
                                                            aria-label="{{ __('purchases.actions.duplicate_line') }}">
                                                        <x-icon name="copy" class="w-4 h-4" />
                                                    </button>
                                                    <button type="button" class="prod-del-btn"
                                                            @click="removeLine(line._uid)"
                                                            title="{{ __('purchases.actions.remove_line') }}"
                                                            aria-label="{{ __('purchases.actions.remove_line') }}">
                                                        <x-icon name="trash" class="w-4 h-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        {{-- Batch sub-row — only on track_batches products.
                                             Spans the full table width via colspan and
                                             carries batch_number + manufacture_date +
                                             expiry_date. Server validation already accepts
                                             these. --}}
                                        <tr x-show="line.track_batches" x-cloak class="pur-line-batch-row">
                                            <td :colspan="7">
                                                <div class="pur-line-batch-fields">
                                                    <label class="field pur-line-batch-field">
                                                        <span class="field-label">{{ __('purchases.line.batch_number') }}</span>
                                                        <input type="text"
                                                               :name="`items[${i}][batch_number]`"
                                                               x-model="line.batch_number"
                                                               maxlength="64"
                                                               class="pos-input mono pos-input-compact"
                                                               placeholder="{{ __('purchases.line.batch_number_placeholder') }}">
                                                    </label>
                                                    <label class="field pur-line-batch-field">
                                                        <span class="field-label">{{ __('purchases.line.manufacture_date') }}</span>
                                                        <input type="text"
                                                               :name="`items[${i}][manufacture_date]`"
                                                               x-model="line.manufacture_date"
                                                               class="pos-input js-datepicker pos-input-compact">
                                                    </label>
                                                    <label class="field pur-line-batch-field">
                                                        <span class="field-label">{{ __('purchases.line.expiry_date') }}</span>
                                                        <input type="text"
                                                               :name="`items[${i}][expiry_date]`"
                                                               x-model="line.expiry_date"
                                                               class="pos-input js-datepicker pos-input-compact">
                                                    </label>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </template>
                            </table>
                            </div>

                            <p class="fg-tertiary text-sm" x-show="items.length === 0" x-cloak>
                                {{ __('purchases.items_empty') }}
                            </p>
                            @error('items')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                {{-- Sidebar: Totals + Notes --}}
                <div class="space-y-5">
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('purchases.sections.totals') }}</div>
                        </div></div>
                        <div class="card-body">
                            {{-- Live preview uses raw `(amount).toFixed(2)` + the
                                 currency code suffix. Server replaces this with the
                                 properly-localized `format_money()` on the show page. --}}
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                                <div><dt class="fg-tertiary">{{ __('purchases.totals.subtotal') }}</dt><dd class="num tnum" x-text="formatMoney(subtotal)"></dd></div>
                                <div><dt class="fg-tertiary">{{ __('purchases.totals.discount') }}</dt><dd class="num tnum" x-text="formatMoney(discountTotal)"></dd></div>
                                <div><dt class="fg-tertiary">{{ __('purchases.totals.tax') }}</dt><dd class="num tnum" x-text="formatMoney(taxTotal)"></dd></div>
                                <div class="col-span-2 border-t border-subtle pt-3"><dt class="font-semibold">{{ __('purchases.totals.grand') }}</dt><dd class="num tnum text-base font-semibold" x-text="formatMoney(grandTotal)"></dd></div>
                            </dl>
                            <p class="field-help mt-2">{{ __('purchases.totals.preview_note') }}</p>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('purchases.sections.notes') }}</div>
                        </div></div>
                        <div class="card-body">
                            <label class="field">
                                <span class="sr-only">{{ __('purchases.fields.notes') }}</span>
                                <textarea name="notes" x-model="notes" rows="5" class="pos-input" maxlength="5000"></textarea>
                                @error('notes')<p class="field-error">{{ $message }}</p>@enderror
                            </label>
                        </div>
                    </div>

                    {{-- Invoice attachment — a single file (the data-ajax-form
                         handler builds FormData, so the file rides along with
                         the AJAX save). On edit, the current file shows with a
                         remove checkbox; uploading a new one replaces it. --}}
                    @php($existingAttachment = $purchase->exists ? $purchase->attachments->first() : null)
                    <div class="card" x-data="{ removeAttachment: false }">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('purchases.sections.attachment') }}</div>
                        </div></div>
                        <div class="card-body space-y-3">
                            @if ($existingAttachment)
                                <div class="flex items-center gap-3 rounded-lg border border-subtle p-3"
                                     :class="removeAttachment ? 'opacity-50 line-through' : ''">
                                    <span class="fg-tertiary shrink-0">
                                        <x-icon name="{{ $existingAttachment->isImage() ? 'image' : 'receipt' }}" class="w-5 h-5" />
                                    </span>
                                    <a href="{{ route('admin.purchases.attachment.download', [$purchase, $existingAttachment]) }}"
                                       class="min-w-0 flex-1 text-sm font-medium truncate hover:underline">
                                        {{ $existingAttachment->original_filename }}
                                    </a>
                                    <label class="flex items-center gap-1.5 text-xs fg-secondary shrink-0 cursor-pointer">
                                        <input type="checkbox" name="remove_attachment" value="1" x-model="removeAttachment" class="pos-check">
                                        {{ __('purchases.attachment.remove') }}
                                    </label>
                                </div>
                            @endif

                            <label class="field">
                                <span class="field-label">{{ $existingAttachment ? __('purchases.attachment.replace_hint') : __('purchases.attachment.label') }}</span>
                                <x-admin.file-upload
                                    name="attachment"
                                    accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.csv"
                                    :max-size-kb="10240"
                                    :hint="__('purchases.attachment.help')" />
                                @error('attachment')<p class="field-error">{{ $message }}</p>@enderror
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>
