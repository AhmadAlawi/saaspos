@php
    $isEdit       = ($mode ?? 'create') === 'edit';
    $formAction   = $isEdit ? route('admin.products.update', $product) : route('admin.products.store');
    $cancelUrl    = route('admin.products.index');
    $deleteUrl    = $isEdit ? route('admin.products.destroy', $product) : null;
    $initialImage = $isEdit ? $product->image_url : null;

    // `old()` always wins on validation-error reload; otherwise read the
    // model on edit, fall back to sensible defaults on create. Carbon /
    // DateTime values are formatted as YYYY-MM-DD so they slot straight
    // into `<input type="date">` without the time component leaking.
    $val = function (string $key, $default = null) use ($isEdit, $product) {
        $value = old($key, $isEdit ? ($product->{$key} ?? $default) : $default);
        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
    };
    $bool = fn (string $key, bool $default = false) =>
        (bool) old($key, $isEdit ? (bool) ($product->{$key} ?? $default) : $default);

    // Default-open tab. If a validation error came back on a field that
    // lives in a non-General tab, jump straight to that tab so the user
    // sees the highlighted error instead of an apparently-empty form.
    $errorTab = 'general';
    if ($errors->any()) {
        if ($errors->hasAny(['cost_price', 'selling_price', 'sale_price', 'mrp', 'tax_group_id', 'is_tax_inclusive'])) {
            $errorTab = 'pricing';
        } elseif ($errors->hasAny(['track_stock', 'sold_by_weight', 'track_batches', 'track_expiry', 'reorder_level', 'reorder_quantity'])) {
            $errorTab = 'inventory';
        } elseif ($errors->hasAny(['hsn_code', 'pharmacy_schedule', 'generic_name', 'manufacturer'])) {
            $errorTab = 'compliance';
        } elseif ($errors->has('variants') || collect($errors->keys())->contains(fn ($k) => str_starts_with($k, 'variants.'))) {
            $errorTab = 'variants';
        } elseif ($errors->has('kit_items') || collect($errors->keys())->contains(fn ($k) => str_starts_with($k, 'kit_items.'))) {
            $errorTab = 'kit';
        }
    }
@endphp

@php
    // ── Variant attribute definitions for the Alpine matrix builder.
    //   1. old('variant_attributes') after a validation error
    //   2. existing meta->variant_attributes on edit
    //   3. empty on a clean create
    if (old('variant_attributes') !== null) {
        $variantAttributesForJs = collect(old('variant_attributes'))->map(fn ($a) => [
            'name'   => (string) ($a['name'] ?? ''),
            'values' => array_values(array_filter(array_map('strval', (array) ($a['values'] ?? [])), fn ($v) => $v !== '')),
        ])->values()->all();
    } elseif ($isEdit) {
        $variantAttributesForJs = collect($product->variantAttributes())->map(fn ($a) => [
            'name'   => (string) ($a['name'] ?? ''),
            'values' => array_values((array) ($a['values'] ?? [])),
        ])->values()->all();
    } else {
        $variantAttributesForJs = [];
    }

    // Existing variant-level store-price overrides, grouped variant_id →
    // store_id → {cost,selling,mrp}. Drives the per-variant grid on edit.
    $variantStorePrices = $isEdit && $product->relationLoaded('prices')
        ? $product->prices->whereNotNull('variant_id')->groupBy('variant_id')
        : collect();

    $variantStorePricesFor = function ($variantId) use ($variantStorePrices) {
        $out = [];
        foreach ($variantStorePrices->get($variantId, collect()) as $row) {
            $out[(string) $row->store_id] = [
                'cost_price'    => $row->cost_price    !== null ? (string) $row->cost_price    : '',
                'selling_price' => $row->selling_price !== null ? (string) $row->selling_price : '',
                'mrp'           => $row->mrp           !== null ? (string) $row->mrp           : '',
            ];
        }
        return $out;
    };

    // Variants snapshot for the Alpine editor. Priority:
    //   1. `old('variants')` after a validation error — preserves user input
    //   2. existing `$product->variants` on edit
    //   3. empty list on a clean create
    if (old('variants') !== null) {
        $variantsForJs = collect(old('variants'))->map(fn ($v) => [
            'id'            => $v['id']            ?? null,
            'combo'         => is_array($v['combo'] ?? null) ? $v['combo'] : (object) [],
            'sku'           => (string) ($v['sku']           ?? ''),
            'barcode'       => (string) ($v['barcode']       ?? ''),
            'cost_price'    => $v['cost_price']    ?? '',
            'selling_price' => $v['selling_price'] ?? '',
            'sale_price'    => $v['sale_price']    ?? '',
            'mrp'           => $v['mrp']           ?? '',
            'is_active'     => filter_var($v['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'store_prices'  => is_array($v['store_prices'] ?? null) ? $v['store_prices'] : (object) [],
        ])->values()->all();
    } elseif ($isEdit && $product->relationLoaded('variants')) {
        $variantsForJs = $product->variants->map(fn ($v) => [
            'id'            => $v->id,
            'combo'         => is_array($attrs = $v->getAttribute('attributes')) ? ($attrs ?: (object) []) : (object) [],
            'sku'           => (string) $v->sku,
            'barcode'       => (string) ($v->barcode ?? ''),
            'cost_price'    => $v->cost_price    !== null ? (string) $v->cost_price    : '',
            'selling_price' => $v->selling_price !== null ? (string) $v->selling_price : '',
            'sale_price'    => $v->sale_price     !== null ? (string) $v->sale_price     : '',
            'mrp'           => $v->mrp           !== null ? (string) $v->mrp           : '',
            'is_active'     => (bool) $v->is_active,
            'store_prices'  => $variantStorePricesFor($v->id) ?: (object) [],
        ])->values()->all();
    } else {
        $variantsForJs = [];
    }

    // ── Kit-items snapshot for the Alpine editor (same priority chain).
    if (old('kit_items') !== null) {
        $kitItemsForJs = collect(old('kit_items'))->map(fn ($k) => [
            'id'                   => $k['id']                   ?? null,
            'component_product_id' => $k['component_product_id'] ?? '',
            'component_variant_id' => $k['component_variant_id'] ?? '',
            'quantity'             => $k['quantity']             ?? '1',
        ])->values()->all();
    } elseif ($isEdit && $product->relationLoaded('kitItems')) {
        $kitItemsForJs = $product->kitItems->map(fn ($k) => [
            'id'                   => $k->id,
            'component_product_id' => (string) $k->component_product_id,
            'component_variant_id' => $k->component_variant_id !== null ? (string) $k->component_variant_id : '',
            'quantity'             => (string) $k->quantity,
        ])->values()->all();
    } else {
        $kitItemsForJs = [];
    }

    // ── Seed data for the REMOTE kit-component pickers. We no longer
    // preload the whole catalog — the picker searches the server. We only
    // need the currently-referenced components' labels (so each row shows
    // its chosen product) + their variants (for the Variant sub-select).
    $kitComponentIds = collect($kitItemsForJs)
        ->pluck('component_product_id')->filter()->map(fn ($v) => (int) $v)->unique()->values();

    $kitRefProducts = $kitComponentIds->isNotEmpty()
        ? \App\Models\Product::query()->whereIn('id', $kitComponentIds)->get(['id', 'name', 'sku', 'cost_price', 'selling_price'])->keyBy('id')
        : collect();

    // Variants (with prices) for the selected components. New picks bring their
    // own variants + prices from the search endpoint.
    $kitVariantRows = $kitComponentIds->isNotEmpty()
        ? \App\Models\ProductVariant::query()
            ->whereIn('product_id', $kitComponentIds)
            ->orderBy('id')
            ->get(['id', 'product_id', 'sku', 'attributes', 'cost_price', 'selling_price'])
        : collect();

    // variant_id => ['cost' => , 'sell' => ] for seeding each kit row's price.
    $kitVariantPrice = $kitVariantRows->mapWithKeys(fn ($v) => [(string) $v->id => [
        'cost' => (string) ($v->cost_price    ?? optional($kitRefProducts->get($v->product_id))->cost_price    ?? '0'),
        'sell' => (string) ($v->selling_price ?? optional($kitRefProducts->get($v->product_id))->selling_price ?? '0'),
    ]]);

    // Attach the display label + the component's effective unit price (variant
    // price wins, else parent) to each kit row so the auto-pricing sum works on
    // first paint without a round-trip.
    $kitItemsForJs = collect($kitItemsForJs)->map(function ($k) use ($kitRefProducts, $kitVariantPrice) {
        $p = $kitRefProducts->get((int) $k['component_product_id']);
        $k['component_label'] = $p ? $p->name.' ('.$p->sku.')' : '';

        $vid = $k['component_variant_id'] ?? '';
        if ($vid !== '' && $kitVariantPrice->has((string) $vid)) {
            $k['component_cost'] = $kitVariantPrice[(string) $vid]['cost'];
            $k['component_sell'] = $kitVariantPrice[(string) $vid]['sell'];
        } else {
            $k['component_cost'] = (string) (optional($p)->cost_price    ?? '0');
            $k['component_sell'] = (string) (optional($p)->selling_price ?? '0');
        }
        return $k;
    })->values()->all();

    // Variants (id/label/prices) for the Variant sub-select + JS price cache.
    $kitVariantsByProduct = $kitVariantRows
        ->groupBy('product_id')
        ->map(fn ($rows) => $rows->map(fn ($v) => [
            'id'            => (string) $v->id,
            'label'         => is_array($attrs = $v->getAttribute('attributes')) && !empty($attrs['label'])
                ? (string) $attrs['label']
                : (string) $v->sku,
            'cost_price'    => (string) ($v->cost_price    ?? optional($kitRefProducts->get($v->product_id))->cost_price    ?? '0'),
            'selling_price' => (string) ($v->selling_price ?? optional($kitRefProducts->get($v->product_id))->selling_price ?? '0'),
        ])->values())
        ->mapWithKeys(fn ($rows, $pid) => [(string) $pid => $rows->all()])
        ->all();

    // Price cache the editor consults for the components sum: keyed by product id
    // (parent price) and "productId:variantId" (variant price). New picks add to
    // it from the search endpoint; this seeds the already-referenced components.
    $kitComponentPrices = [];
    foreach ($kitRefProducts as $pid => $p) {
        $kitComponentPrices[(string) $pid] = ['cost' => (string) $p->cost_price, 'sell' => (string) $p->selling_price];
    }
    foreach ($kitVariantRows as $v) {
        $kitComponentPrices[$v->product_id.':'.$v->id] = [
            'cost' => (string) ($v->cost_price    ?? optional($kitRefProducts->get($v->product_id))->cost_price    ?? '0'),
            'sell' => (string) ($v->selling_price ?? optional($kitRefProducts->get($v->product_id))->selling_price ?? '0'),
        ];
    }

    // Per-store overrides keyed by store_id so each row can pre-fill
    // from the existing row (if any).
    // Product-level overrides only (variant_id null) — variant products
    // use the per-variant grid instead of this card.
    $existingStorePrices = $isEdit && $product->relationLoaded('prices')
        ? $product->prices->whereNull('variant_id')->keyBy('store_id')
        : collect();

    $storePriceVal = function (int $storeId, string $col) use ($existingStorePrices) {
        $oldKey = "store_prices.{$storeId}.{$col}";
        if (old($oldKey) !== null) return old($oldKey);
        $row = $existingStorePrices->get($storeId);
        if (! $row) return '';
        return $row->{$col} !== null ? (string) $row->{$col} : '';
    };
@endphp

<div class="page-wide"
     x-data="productEditor({
         tab:            '{{ $errorTab }}',
         type:           '{{ $val('type', 'simple') }}',
         cost:           '{{ $val('cost_price', '0') ?: 0 }}',
         selling:        '{{ $val('selling_price', '0') ?: 0 }}',
         salePrice:      '{{ $val('sale_price', '') }}',
         isActive:       {{ $bool('is_active', true) ? 'true' : 'false' }},
         isFeatured:     {{ $bool('is_featured') ? 'true' : 'false' }},
         categoryId:     '{{ $val('category_id', '') }}',
         taxGroupId:     '{{ $val('tax_group_id', '') }}',
         categoryTaxMap: {{ Js::from($categoryTaxMap ?? []) }},
         variantAttributes: {{ Js::from($variantAttributesForJs) }},
         variants:       {{ Js::from($variantsForJs) }},
         parentSku:      '{{ $val('sku', '') }}',
         stores:         {{ Js::from(($stores ?? collect())->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'code' => $s->code])->values()) }},
         kitItems:       {{ Js::from($kitItemsForJs) }},
         variantsByProduct: {{ Js::from($kitVariantsByProduct) }},
         componentPrices: {{ Js::from($kitComponentPrices) }},
     })">

    {{-- AJAX via lib/ajax-form.js — see http-client memory rule.
         Multipart enctype carries the FormData (image upload + variants)
         straight through axios; the lib auto-detects FormData and
         sends with the right Content-Type. --}}
    <form method="POST"
          action="{{ $formAction }}"
          enctype="multipart/form-data"
          novalidate
          data-ajax-form
          @ajax-form:start="submitting = true"
          @ajax-form:end="submitting = false">
        @csrf
        @if ($isEdit) @method('PATCH') @endif

        {{-- ── Page header (matches design-system/admin/Product.html) ── --}}
        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ $cancelUrl }}"
                   class="pos-btn pos-btn-sm pos-btn-ghost"
                   aria-label="{{ __('products.actions.back_to_list') }}">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title">
                        {{ $isEdit ? $product->name : __('products.actions.new') }}
                        {{-- Status badge — reactive, follows the toggle in the
                             Status card on the General tab. --}}
                        @if ($isEdit)
                            <template x-if="isActive">
                                <span class="prod-head-badge prod-head-badge-positive">{{ __('products.list.active') }}</span>
                            </template>
                            <template x-if="!isActive">
                                <span class="prod-head-badge prod-head-badge-muted">{{ __('products.list.inactive') }}</span>
                            </template>
                        @endif
                    </h1>
                    @if ($isEdit)
                        <p class="page-sub">
                            <span class="mono">{{ $product->sku }}</span>
                            · {{ __('products.drawer.edit_updated', []) ?: 'updated' }}
                            {{ $product->updated_at?->diffForHumans() }}
                        </p>
                    @else
                        <p class="page-sub">{{ __('products.sub') }}</p>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-2">
                @if ($isEdit)
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-danger"
                            @click="$store.confirm.show({
                                title:        {{ \Illuminate\Support\Js::from(__('products.actions.delete') . ' “' . $product->name . '”?') }},
                                message:      {{ \Illuminate\Support\Js::from(__('products.actions.delete_confirm')) }},
                                intent:       'danger',
                                confirmLabel: {{ \Illuminate\Support\Js::from(__('products.actions.delete')) }},
                                {{-- onConfirm returns a never-settling Promise so the
                                     dialog's confirm button keeps its spinner until the
                                     browser actually navigates away. Without this the
                                     dialog would close instantly on form.submit() and
                                     the user would see an unexplained idle gap until
                                     the server response loads the next page. --}}
                                onConfirm: () => {
                                    document.getElementById('prod-delete-form').submit();
                                    return new Promise(() => {});
                                },
                            })">
                        <x-icon name="trash" class="w-4 h-4" />
                        {{ __('products.actions.delete') }}
                    </button>
                @endif
                <a href="{{ $cancelUrl }}"
                   class="pos-btn pos-btn-sm pos-btn-ghost"
                   :class="{ 'pointer-events-none opacity-50': submitting }">
                    {{ __('products.actions.discard') }}
                </a>
                <button type="submit"
                        class="pos-btn pos-btn-sm pos-btn-primary"
                        :disabled="submitting">
                    <svg x-show="submitting" x-cloak
                         class="h-4 w-4 animate-spin"
                         viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10"
                                stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor"
                              d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                    </svg>
                    <span x-text="
                        submitting
                            ? (@js($isEdit) ? @js(__('products.actions_extra.saving'))
                                            : @js(__('products.actions_extra.creating')))
                            : (@js($isEdit) ? @js(__('products.actions.save'))
                                            : @js(__('products.actions.create')))
                    "></span>
                </button>
            </div>
        </div>

        {{-- ── Tabs strip ─────────────────────────────────────────── --}}
        <div class="tabs mb-5">
            <button type="button"
                    class="tab"
                    :class="{ 'is-active': tab === 'general' }"
                    @click="tab = 'general'">
                {{ __('products.tabs.general') }}
            </button>
            {{-- Kit tab — only relevant when type='kit'. Sits BEFORE Pricing
                 because a kit's price is auto-generated from the components you
                 pick here, so the natural flow is components → price. --}}
            <button type="button"
                    class="tab"
                    x-show="isKitType"
                    x-cloak
                    :class="{ 'is-active': tab === 'kit' }"
                    @click="tab = 'kit'">
                {{ __('products.tabs.kit') }}
                <span class="prod-tab-count"
                      x-show="kitItems.length > 0"
                      x-text="kitItems.length"
                      x-cloak></span>
            </button>
            <button type="button"
                    class="tab"
                    :class="{ 'is-active': tab === 'pricing' }"
                    @click="tab = 'pricing'">
                {{ __('products.tabs.pricing') }}
            </button>
            {{-- Variants tab — only relevant when type='variant'. Shown
                 conditionally so simple/kit products don't see an
                 empty Variants section that does nothing. --}}
            <button type="button"
                    class="tab"
                    x-show="isVariantType"
                    x-cloak
                    :class="{ 'is-active': tab === 'variants' }"
                    @click="tab = 'variants'">
                {{ __('products.tabs.variants') }}
                <span class="prod-tab-count"
                      x-show="variants.length > 0"
                      x-text="variants.length"
                      x-cloak></span>
            </button>
            <button type="button"
                    class="tab"
                    :class="{ 'is-active': tab === 'inventory' }"
                    @click="tab = 'inventory'">
                {{ __('products.tabs.inventory') }}
            </button>
            <button type="button"
                    class="tab"
                    :class="{ 'is-active': tab === 'compliance' }"
                    @click="tab = 'compliance'">
                {{ __('products.tabs.compliance') }}
            </button>
        </div>

        {{-- ─────────────────────────────── GENERAL tab ─────────── --}}
        <div x-show="tab === 'general'" x-cloak>
            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_340px] gap-5 items-start">
                <div class="space-y-5">
                    {{-- Basics card --}}
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <div class="card-title">{{ __('products.sections.basics') }}</div>
                                <div class="card-title-sub">{{ __('products.sections.basics_sub') }}</div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="form-stack">
                                <label class="field">
                                    <span class="field-label is-required">{{ __('products.fields.name') }}</span>
                                    <input type="text" name="name" value="{{ $val('name') }}"
                                           class="pos-input"
                                           placeholder="{{ __('products.fields.name_placeholder') }}">
                                </label>

                                {{-- Product type — drives the Variants / Components
                                     tabs' visibility. `x-model` keeps the editor's
                                     Alpine state in sync with the dropdown so the
                                     tab strip and Variants tab body react to changes.
                                     Hidden when editing an existing product whose
                                     type isn't selectable yet (e.g. kit when Step 2
                                     hasn't shipped). For Step 1 we expose simple +
                                     variant only — kit lands in Step 2. --}}
                                <label class="field">
                                    <span class="field-label">{{ __('products.fields.type') }}</span>
                                    <select x-data="enhancedSelect()"
                                            x-effect="ts && ts.setValue(type, true)"
                                            x-model="type"
                                            name="type"
                                            class="pos-input">
                                        <option value="simple">{{ __('products.types.simple') }}</option>
                                        <option value="variant">{{ __('products.types.variant') }}</option>
                                        <option value="kit">{{ __('products.types.kit') }}</option>
                                    </select>
                                    <p class="field-help">{{ __('products.fields.type_help') }}</p>
                                </label>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label class="field">
                                        <span class="field-label is-required">{{ __('products.fields.sku') }}</span>
                                        {{-- x-model so auto-generated variant SKUs follow the
                                             parent SKU as the user types it on a fresh create. --}}
                                        <input type="text" name="sku" value="{{ $val('sku') }}"
                                               x-model="parentSku"
                                               class="pos-input mono"
                                               maxlength="64"
                                               placeholder="{{ __('products.fields.sku_placeholder') }}">
                                    </label>
                                    <label class="field">
                                        <span class="field-label">{{ __('products.fields.barcode') }}</span>
                                        <input type="text" name="barcode" value="{{ $val('barcode') }}"
                                               class="pos-input mono"
                                               maxlength="64"
                                               placeholder="{{ __('products.fields.barcode_placeholder') }}">
                                    </label>
                                </div>

                                {{-- Extra/alternate barcodes — a case/carton code from a
                                     different supplier, a relabeled batch, etc. One per
                                     line; parsed into the `barcodes` array server-side
                                     (see ProductRequest::prepareForValidation()). --}}
                                <label class="field">
                                    <span class="field-label">{{ __('products.fields.additional_barcodes') }}</span>
                                    <textarea name="barcodes_text" rows="3"
                                              class="pos-input mono"
                                              placeholder="{{ __('products.fields.additional_barcodes_placeholder') }}">{{ old('barcodes_text', $isEdit ? $product->barcodes->pluck('barcode')->implode("\n") : '') }}</textarea>
                                    <span class="field-help">{{ __('products.fields.additional_barcodes_help') }}</span>
                                    @error('barcodes_text')<p class="field-error">{{ $message }}</p>@enderror
                                </label>

                                <label class="field">
                                    <span class="field-label">{{ __('products.fields.short_description') }}</span>
                                    <input type="text" name="short_description" value="{{ $val('short_description') }}"
                                           class="pos-input"
                                           maxlength="191"
                                           placeholder="{{ __('products.fields.short_description_placeholder') }}">
                                </label>

                                <label class="field">
                                    <span class="field-label">{{ __('products.fields.description') }}</span>
                                    <textarea name="description" rows="3"
                                              class="pos-input"
                                              placeholder="{{ __('products.fields.description_placeholder') }}">{{ $val('description') }}</textarea>
                                </label>

                                @php
                                    // Labels for the remote pickers to seed the current
                                    // selection (so edit shows the chosen name without a fetch).
                                    $selCatId = $val('category_id', '');
                                    $selBrId  = $val('brand_id', '');
                                    $selectedCategoryLabel = $selCatId ? (optional(\App\Models\Category::find($selCatId))->name ?? '') : '';
                                    $selectedBrandLabel    = $selBrId  ? (optional(\App\Models\Brand::find($selBrId))->name ?? '')    : '';
                                @endphp
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label class="field">
                                        <span class="field-label is-required">{{ __('products.fields.category') }}</span>
                                        {{-- Remote searchable picker. Picking a category still
                                             fires the productEditor watcher that pre-fills the Tax
                                             rule; `onResults` caches each result's tax mapping so
                                             that works for server-loaded categories too. --}}
                                        <select x-data="remoteSelect({
                                                    url: @js(route('admin.categories.search')),
                                                    value: categoryId,
                                                    label: @js($selectedCategoryLabel),
                                                    placeholder: @js(__('products.fields.category_none')),
                                                    onResults: (rows) => cacheCategoryTax(rows),
                                                })"
                                                x-model="categoryId"
                                                name="category_id"
                                                class="pos-input"></select>
                                        @error('category_id')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>

                                    <label class="field">
                                        <span class="field-label">{{ __('products.fields.brand') }}</span>
                                        <select x-data="remoteSelect({
                                                    url: @js(route('admin.brands.search')),
                                                    value: @js((string) $selBrId),
                                                    label: @js($selectedBrandLabel),
                                                    placeholder: @js(__('products.fields.brand_none')),
                                                })"
                                                name="brand_id"
                                                class="pos-input"></select>
                                    </label>
                                </div>

                                <label class="field">
                                    <span class="field-label is-required">{{ __('products.fields.unit') }}</span>
                                    <select x-data="enhancedSelect()" name="unit_id" class="pos-input">
                                        @foreach ($units as $u)
                                            <option value="{{ $u->id }}" @selected((string) $val('unit_id') === (string) $u->id)>
                                                {{ $u->name }} ({{ $u->code }})
                                            </option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Image card --}}
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <div class="card-title">{{ __('products.sections.image') }}</div>
                                <div class="card-title-sub">{{ __('products.sections.image_sub') }}</div>
                            </div>
                        </div>
                        <div class="card-body">
                            <x-admin.image-upload
                                name="image"
                                variant="inline"
                                :initial-url="$initialImage"
                                :max-size-kb="2048"
                                :tile-label="__('products.image.title')"
                                :tile-hint="__('products.image.hint')" />
                        </div>
                    </div>
                </div>

                {{-- Right column: Status --}}
                <div class="space-y-5 lg:sticky lg:top-4">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <div class="card-title">{{ __('products.sections.status') }}</div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="form-stack">
                                <label class="field-toggle">
                                    <input type="hidden" name="is_active" value="0">
                                    {{-- x-model two-way binds to `isActive` on the editor
                                         scope so the header badge follows the toggle. --}}
                                    <input type="checkbox" name="is_active" value="1"
                                           x-model="isActive"
                                           @checked($bool('is_active', true))>
                                    <span>
                                        <span class="block">{{ __('products.fields.is_active') }}</span>
                                        <span class="block text-[11.5px] fg-tertiary mt-0.5">{{ __('products.fields.is_active_help') }}</span>
                                    </span>
                                </label>

                                <label class="field-toggle">
                                    <input type="hidden" name="is_featured" value="0">
                                    <input type="checkbox" name="is_featured" value="1"
                                           x-model="isFeatured"
                                           @checked($bool('is_featured'))>
                                    <span>
                                        <span class="block">{{ __('products.fields.is_featured') }}</span>
                                        <span class="block text-[11.5px] fg-tertiary mt-0.5">{{ __('products.fields.is_featured_help') }}</span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ─────────────────────────────── PRICING tab ─────────── --}}
        <div x-show="tab === 'pricing'" x-cloak>
            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_340px] gap-5 items-start">
                <div class="space-y-5">
                    {{-- For variant products the parent SKU isn't sold
                         directly, so its price is meaningless — each
                         variant carries its own. Point the user at the
                         Variants tab instead of showing a dead form. --}}
                    <div class="card" x-show="isVariantType" x-cloak>
                        <div class="card-body">
                            <p class="prod-variant-price-note">
                                {{ __('products.sections.pricing_variant_note') }}
                            </p>
                            <button type="button"
                                    class="pos-btn pos-btn-sm pos-btn-ghost mt-2"
                                    @click="tab = 'variants'">
                                {{ __('products.tabs.variants') }} →
                            </button>
                        </div>
                    </div>

                    {{-- Price & cost card with reactive KPI boxes. Hidden
                         for variant products (priced per-child). --}}
                    <div class="card" x-show="!isVariantType">
                        <div class="card-header">
                            <div>
                                <div class="card-title">{{ __('products.sections.pricing') }}</div>
                                <div class="card-title-sub">{{ __('products.sections.pricing_sub') }}</div>
                            </div>
                        </div>
                        <div class="card-body">
                            {{-- Kit auto-pricing note: prices fill from the sum of the
                                 components until you edit them; "Recalculate" snaps back. --}}
                            <div class="prod-kit-price-note" x-show="isKitType" x-cloak>
                                <x-icon name="info" class="w-4 h-4" />
                                <span>{{ __('products.kit.price_auto_note') }}</span>
                                <button type="button" class="prod-kit-recalc" @click="recalcKitPrice()">
                                    {{ __('products.kit.recalculate') }}
                                </button>
                                <span class="prod-kit-price-sum"
                                      x-text="@js(__('products.kit.components_sum')).replace(':cost', componentsTotalCostFormatted).replace(':sell', componentsTotalSellFormatted)"></span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                                <label class="field">
                                    <span class="field-label is-required">{{ __('products.fields.selling_price') }}</span>
                                    <x-admin.money-input name="selling_price" x-model.number="selling" @input="markSellingOverridden()" value="{{ $val('selling_price', '0') }}" />
                                </label>
                                <label class="field">
                                    <span class="field-label is-required">{{ __('products.fields.cost_price') }}</span>
                                    <x-admin.money-input name="cost_price" x-model.number="cost" @input="markCostOverridden()" value="{{ $val('cost_price', '0') }}" />
                                </label>
                                <label class="field">
                                    <span class="field-label">{{ __('products.fields.mrp') }}</span>
                                    <x-admin.money-input name="mrp" value="{{ $val('mrp') }}" />
                                    <p class="field-help">{{ __('products.fields.mrp_help') }}</p>
                                </label>
                                <label class="field">
                                    <span class="field-label">{{ __('products.fields.sale_price') }}</span>
                                    <x-admin.money-input name="sale_price" x-model.number="salePrice" value="{{ $val('sale_price', '') }}" />
                                    <p class="field-help">{{ __('products.fields.sale_price_help') }}</p>
                                </label>
                            </div>

                            {{-- Target markup % — drives the "auto-update selling price
                                 on purchase receive" workflow. Leave blank to manage
                                 selling price by hand. --}}
                            <div class="form-stack mt-3">
                                <label class="field">
                                    <span class="field-label">{{ __('products.fields.markup_percent') }}</span>
                                    <div class="relative">
                                        <input type="number"
                                               name="markup_percent"
                                               value="{{ $val('markup_percent') }}"
                                               class="pos-input num tnum pe-10"
                                               step="0.0001" min="0" max="9999"
                                               placeholder="—">
                                        <span class="pointer-events-none absolute end-3 inset-y-0 flex items-center text-muted">%</span>
                                    </div>
                                    <p class="field-help">{{ __('products.fields.markup_percent_help') }}</p>
                                </label>
                            </div>

                            {{-- KPI boxes — reactive computed values from productEditor. --}}
                            <div class="prod-kpi-row">
                                <div class="prod-kpi-box">
                                    <div class="prod-kpi-label">{{ __('products.fields.margin') }}</div>
                                    <div class="prod-kpi-value"
                                         :class="marginClass"
                                         x-text="margin + '%'"></div>
                                </div>
                                <div class="prod-kpi-box">
                                    <div class="prod-kpi-label">{{ __('products.fields.markup') }}</div>
                                    <div class="prod-kpi-value"
                                         x-text="markup === null ? '∞' : (markup + '%')"></div>
                                </div>
                                <div class="prod-kpi-box">
                                    <div class="prod-kpi-label">{{ __('products.fields.profit_per_unit') }}</div>
                                    {{-- Currency symbol is static (set from the company's base
                                         currency at render time); the numeric `profit` is
                                         reactive — Alpine updates only the number span. --}}
                                    <div class="prod-kpi-value">
                                        <span>{{ currency_symbol() }}</span><span x-text="profit"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Product-level per-store overrides. Hidden when no
                         active stores exist, and for variant products
                         (which override per-child in the Variants tab). --}}
                    @if (($stores ?? collect())->isNotEmpty())
                        <div class="card" x-show="!isVariantType" x-cloak>
                            <div class="card-header">
                                <div>
                                    <div class="card-title">{{ __('products.sections.store_prices') }}</div>
                                    <div class="card-title-sub">{{ __('products.sections.store_prices_sub') }}</div>
                                </div>
                                {{-- Expand / collapse every store at once. --}}
                                <button type="button"
                                        class="pos-btn pos-btn-sm pos-btn-ghost"
                                        x-show="stores.length > 1"
                                        x-cloak
                                        @click="anyStorePriceCollapsed ? expandAllStorePrices() : collapseAllStorePrices()">
                                    <span class="prod-collapse-all-icon" :class="{ 'is-up': !anyStorePriceCollapsed }">
                                        <x-icon name="chevron" class="w-4 h-4" />
                                    </span>
                                    <span x-text="anyStorePriceCollapsed ? @js(__('products.variants.expand_all')) : @js(__('products.variants.collapse_all'))"></span>
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="prod-store-prices">
                                    @foreach ($stores as $store)
                                        {{-- Collapsible per store — mirrors the variant
                                             per-store overrides pattern. --}}
                                        <div class="prod-store-block" :class="{ 'is-collapsed': !storePricesOpen[{{ $store->id }}] }">
                                            <button type="button"
                                                    class="prod-store-head"
                                                    :aria-expanded="storePricesOpen[{{ $store->id }}] ? 'true' : 'false'"
                                                    @click="storePricesOpen[{{ $store->id }}] = !storePricesOpen[{{ $store->id }}]">
                                                <span class="prod-store-chevron"><x-icon name="chevron" class="w-4 h-4" /></span>
                                                <span class="prod-store-head-name">{{ $store->name }}</span>
                                                <span class="prod-store-head-code mono">{{ $store->code }}</span>
                                            </button>
                                            <div class="prod-store-body" x-show="storePricesOpen[{{ $store->id }}]" x-cloak>
                                                <div class="prod-store-price-grid">
                                                    <label class="field">
                                                        <span class="field-label">{{ __('products.store_prices.selling') }}</span>
                                                        <x-admin.money-input
                                                            name="store_prices[{{ $store->id }}][selling_price]"
                                                            value="{{ $storePriceVal($store->id, 'selling_price') }}"
                                                            placeholder="{{ __('products.store_prices.inherit_hint') }}" />
                                                    </label>
                                                    <label class="field">
                                                        <span class="field-label">{{ __('products.store_prices.cost') }}</span>
                                                        <x-admin.money-input
                                                            name="store_prices[{{ $store->id }}][cost_price]"
                                                            value="{{ $storePriceVal($store->id, 'cost_price') }}"
                                                            placeholder="{{ __('products.store_prices.inherit_hint') }}" />
                                                    </label>
                                                    <label class="field">
                                                        <span class="field-label">{{ __('products.store_prices.mrp') }}</span>
                                                        <x-admin.money-input
                                                            name="store_prices[{{ $store->id }}][mrp]"
                                                            value="{{ $storePriceVal($store->id, 'mrp') }}"
                                                            placeholder="{{ __('products.store_prices.inherit_hint') }}" />
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Right column: Tax + Reporting placeholder --}}
                <div class="space-y-5 lg:sticky lg:top-4">
                    {{-- Tax card --}}
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <div class="card-title">{{ __('products.sections.tax') }}</div>
                                <div class="card-title-sub">{{ __('products.sections.tax_sub') }}</div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="form-stack">
                                <label class="field">
                                    <span class="field-label">{{ __('products.fields.tax_group') }}</span>
                                    {{-- `x-effect` keeps TomSelect's chip in sync with
                                         `taxGroupId`. That value is auto-set whenever the
                                         user picks a different Category that has a default
                                         tax rule (see productEditor.init watcher). --}}
                                    <select x-data="enhancedSelect()"
                                            x-effect="ts && ts.setValue(taxGroupId, true)"
                                            x-model="taxGroupId"
                                            name="tax_group_id"
                                            class="pos-input">
                                        <option value="">{{ __('products.fields.tax_group_none') }}</option>
                                        @foreach ($taxGroups as $tg)
                                            <option value="{{ $tg->id }}" @selected((string) $val('tax_group_id') === (string) $tg->id)>
                                                {{ $tg->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="field-help">{{ __('products.fields.tax_group_help') }}</p>
                                </label>

                                <label class="field-toggle">
                                    <input type="hidden" name="is_tax_inclusive" value="0">
                                    <input type="checkbox" name="is_tax_inclusive" value="1"
                                           @checked($bool('is_tax_inclusive'))>
                                    <span>{{ __('products.fields.is_tax_inclusive') }}</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Reporting placeholder — sales-driven, populated when the
                         sales module lands. Shown empty for now so the slot is
                         visible in the design and customers know what's coming. --}}
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <div class="card-title">{{ __('products.sections.reporting') }}</div>
                                <div class="card-title-sub">{{ __('products.sections.reporting_sub') }}</div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="prod-report-grid">
                                <div>
                                    <div class="prod-kpi-label">{{ __('products.reporting.sold_today') }}</div>
                                    <div class="prod-report-value">{{ __('products.reporting.placeholder') }}</div>
                                </div>
                                <div>
                                    <div class="prod-kpi-label">{{ __('products.reporting.revenue_30d') }}</div>
                                    <div class="prod-report-value">{{ __('products.reporting.placeholder') }}</div>
                                </div>
                            </div>
                            <p class="prod-report-note">{{ __('products.reporting.awaiting_sales') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ─────────────────────────────── VARIANTS tab ────────── --}}
        {{-- Visible only when type='variant'. The user defines attributes
             (Color, Size) + values; the matrix below is the cartesian
             product, regenerated live. Each generated row is a child SKU
             with its own required selling price + optional per-store
             overrides. Deletes apply server-side on save (sales-guard
             refuses any variant already on a sale line). --}}
        <div x-show="tab === 'variants' && isVariantType" x-cloak>
            {{-- Attribute builder --}}
            <div class="card mb-5">
                <div class="card-header">
                    <div>
                        <div class="card-title">{{ __('products.variants.attributes_title') }}</div>
                        <div class="card-title-sub">{{ __('products.variants.attributes_sub') }}</div>
                    </div>
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-primary"
                            @click="addAttribute()">
                        <x-icon name="plus" class="w-4 h-4" />
                        {{ __('products.variants.add_attribute') }}
                    </button>
                </div>
                <div class="card-body">
                    <template x-if="variantAttributes.length === 0">
                        <div class="prod-variants-empty">
                            <span class="prod-variants-empty-icon"><x-icon name="box" class="w-5 h-5" /></span>
                            <div class="prod-variants-empty-title">{{ __('products.variants.attr_empty_title') }}</div>
                            <div class="prod-variants-empty-sub">{{ __('products.variants.attr_empty_sub') }}</div>
                        </div>
                    </template>

                    <div class="prod-attr-list" x-show="variantAttributes.length > 0">
                        <template x-for="(attr, ai) in variantAttributes" :key="ai">
                            <div class="prod-attr-row">
                                <label class="field prod-attr-name">
                                    <span class="field-label is-required">{{ __('products.variants.attr_name') }}</span>
                                    <input type="text"
                                           x-model="attr.name"
                                           @focus="attr._prevName = attr.name"
                                           @change="renameAttribute(ai)"
                                           maxlength="50"
                                           class="pos-input"
                                           :name="`variant_attributes[${ai}][name]`"
                                           :placeholder="@js(__('products.variants.attr_name_placeholder'))">
                                </label>

                                <div class="field prod-attr-values-field">
                                    <span class="field-label">{{ __('products.variants.attr_values') }}</span>
                                    <div class="prod-attr-values">
                                        <template x-for="(val, vi) in attr.values" :key="vi">
                                            <span class="prod-attr-chip">
                                                <span x-text="val"></span>
                                                {{-- Each value posts as a hidden field so it
                                                     survives a validation-error reload. --}}
                                                <input type="hidden" :name="`variant_attributes[${ai}][values][]`" :value="val">
                                                <button type="button"
                                                        class="prod-attr-chip-x"
                                                        @click="removeAttributeValue(ai, vi)"
                                                        aria-label="Remove">×</button>
                                            </span>
                                        </template>
                                        <input type="text"
                                               x-model="attr._newValue"
                                               @keydown.enter.prevent="addAttributeValue(ai)"
                                               @blur="addAttributeValue(ai)"
                                               class="pos-input prod-attr-value-input"
                                               :placeholder="@js(__('products.variants.attr_value_placeholder'))">
                                    </div>
                                </div>

                                <button type="button"
                                        class="prod-attr-remove"
                                        aria-label="{{ __('products.variants.remove') }}"
                                        @click="$store.confirm.show({
                                            title:        {{ \Illuminate\Support\Js::from(__('products.variants.attr_delete_title')) }},
                                            message:      {{ \Illuminate\Support\Js::from(__('products.variants.attr_delete_confirm')) }},
                                            intent:       'danger',
                                            confirmLabel: {{ \Illuminate\Support\Js::from(__('products.variants.remove')) }},
                                            onConfirm:    () => removeAttribute(ai),
                                        })">
                                    <x-icon name="trash" class="w-4 h-4" />
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Generated matrix --}}
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">
                            {{ __('products.sections.variants') }}
                            <span class="prod-tab-count" x-show="variants.length > 0" x-text="variants.length"></span>
                        </div>
                        <div class="card-title-sub">{{ __('products.sections.variants_sub') }}</div>
                    </div>
                    {{-- Expand / collapse every variant card at once. --}}
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            x-show="variants.length > 1"
                            x-cloak
                            @click="anyVariantCollapsed ? expandAllVariants() : collapseAllVariants()">
                        <span class="prod-collapse-all-icon" :class="{ 'is-up': !anyVariantCollapsed }">
                            <x-icon name="chevron" class="w-4 h-4" />
                        </span>
                        <span x-text="anyVariantCollapsed ? @js(__('products.variants.expand_all')) : @js(__('products.variants.collapse_all'))"></span>
                    </button>
                </div>
                <div class="card-body">
                    <template x-if="variants.length === 0">
                        <div class="prod-variants-empty">
                            <span class="prod-variants-empty-icon"><x-icon name="box" class="w-5 h-5" /></span>
                            <div class="prod-variants-empty-title">{{ __('products.variants.empty_title') }}</div>
                            <div class="prod-variants-empty-sub">{{ __('products.variants.empty_sub') }}</div>
                        </div>
                    </template>

                    <template x-if="variants.length > 0">
                        <div class="prod-variants-list">
                            <template x-for="(v, i) in variants" :key="comboKey(v.combo)">
                                <div class="prod-variant-row" :class="{ 'is-collapsed': !v._open }">
                                    <input type="hidden" :name="`variants[${i}][id]`" :value="v.id ?? ''">
                                    {{-- Combination map → hidden fields so it round-trips. --}}
                                    <template x-for="[attrName, val] in Object.entries(v.combo)" :key="attrName">
                                        <input type="hidden" :name="`variants[${i}][combo][${attrName}]`" :value="val">
                                    </template>

                                    {{-- Always-visible header: chevron + combo label + a
                                         compact SKU/price summary + the Active toggle.
                                         Clicking the header (but not the toggle) expands. --}}
                                    <div class="prod-variant-head" @click="v._open = !v._open">
                                        <button type="button"
                                                class="prod-variant-chevron"
                                                :aria-expanded="v._open ? 'true' : 'false'"
                                                @click.stop="v._open = !v._open">
                                            <x-icon name="chevron" class="w-4 h-4" />
                                        </button>
                                        <div class="prod-variant-head-main">
                                            <span class="prod-variant-head-title" x-text="comboLabel(v.combo)"></span>
                                            <span class="prod-variant-head-sub">
                                                <span class="mono" x-text="v.sku || @js(__('products.variants.fields.sku_placeholder'))"></span>
                                                <template x-if="v.selling_price">
                                                    <span>· {{ currency_symbol() }}<span x-text="v.selling_price"></span></span>
                                                </template>
                                            </span>
                                        </div>
                                        <label class="field-toggle prod-variant-head-active" @click.stop>
                                            <input type="hidden" :name="`variants[${i}][is_active]`" value="0">
                                            <input type="checkbox" :name="`variants[${i}][is_active]`" value="1"
                                                   x-model="v.is_active">
                                            <span>{{ __('products.variants.fields.is_active') }}</span>
                                        </label>
                                        {{-- Delete this combination (e.g. a size/color you
                                             don't stock). Confirmed; the combo is remembered
                                             so it won't regenerate. --}}
                                        <button type="button"
                                                class="prod-variant-delete"
                                                aria-label="{{ __('products.variants.remove') }}"
                                                @click.stop="$store.confirm.show({
                                                    title:        @js(__('products.variants.delete_title')),
                                                    message:      comboLabel(v.combo) + ' — ' + @js(__('products.variants.delete_confirm')),
                                                    intent:       'danger',
                                                    confirmLabel: @js(__('products.variants.remove')),
                                                    onConfirm:    () => deleteVariant(i),
                                                })">
                                            <x-icon name="trash" class="w-4 h-4" />
                                        </button>
                                    </div>

                                    {{-- Collapsible body. x-show keeps inputs in the DOM
                                         (still submitted) while hidden. --}}
                                    <div class="prod-variant-body" x-show="v._open" x-cloak>
                                        <div class="prod-variant-grid">
                                            <label class="field">
                                                <span class="field-label is-required">{{ __('products.variants.fields.sku') }}</span>
                                                <input type="text"
                                                       :name="`variants[${i}][sku]`"
                                                       x-model="v.sku"
                                                       maxlength="64"
                                                       class="pos-input mono"
                                                       :placeholder="@js(__('products.variants.fields.sku_placeholder'))">
                                            </label>

                                            <label class="field">
                                                <span class="field-label">{{ __('products.variants.fields.barcode') }}</span>
                                                <input type="text"
                                                       :name="`variants[${i}][barcode]`"
                                                       x-model="v.barcode"
                                                       maxlength="64"
                                                       class="pos-input mono"
                                                       :placeholder="@js(__('products.variants.fields.barcode_placeholder'))">
                                            </label>

                                            <label class="field">
                                                <span class="field-label is-required">{{ __('products.variants.fields.selling_price') }}</span>
                                                <x-admin.money-input x-bind:name="`variants[${i}][selling_price]`" x-model="v.selling_price" />
                                            </label>

                                            <label class="field">
                                                <span class="field-label">{{ __('products.variants.fields.cost_price') }}</span>
                                                <x-admin.money-input x-bind:name="`variants[${i}][cost_price]`" x-model="v.cost_price" />
                                            </label>

                                            <label class="field">
                                                <span class="field-label">{{ __('products.variants.fields.mrp') }}</span>
                                                <x-admin.money-input x-bind:name="`variants[${i}][mrp]`" x-model="v.mrp" />
                                            </label>

                                            <label class="field">
                                                <span class="field-label">{{ __('products.variants.fields.sale_price') }}</span>
                                                <x-admin.money-input x-bind:name="`variants[${i}][sale_price]`" x-model="v.sale_price" />
                                            </label>
                                        </div>

                                    {{-- Per-variant per-store price overrides — collapsible,
                                         since multi-store catalogs make these rows tall. --}}
                                    <template x-if="stores.length > 0">
                                        <div class="prod-variant-stores" :class="{ 'is-collapsed': !v._storesOpen }">
                                            <button type="button"
                                                    class="prod-variant-stores-toggle"
                                                    :aria-expanded="v._storesOpen ? 'true' : 'false'"
                                                    @click="v._storesOpen = !v._storesOpen">
                                                <span class="prod-variant-stores-chevron"><x-icon name="chevron" class="w-3.5 h-3.5" /></span>
                                                <span class="prod-variant-stores-title">{{ __('products.variants.store_overrides') }}</span>
                                                <span class="prod-variant-stores-count" x-text="stores.length"></span>
                                            </button>

                                            <div x-show="v._storesOpen" x-cloak>
                                            {{-- Column headers so the three money inputs are
                                                 identifiable; blank cells inherit the variant price. --}}
                                            <div class="prod-variant-store-row prod-variant-store-head">
                                                <div>{{ __('products.store_prices.store') }}</div>
                                                <div>{{ __('products.store_prices.selling') }}</div>
                                                <div>{{ __('products.store_prices.cost') }}</div>
                                                <div>{{ __('products.store_prices.mrp') }}</div>
                                            </div>
                                            <template x-for="s in stores" :key="s.id">
                                                <div class="prod-variant-store-row">
                                                    <div class="prod-variant-store-name" x-text="s.name"></div>
                                                    <x-admin.money-input
                                                        x-bind:name="`variants[${i}][store_prices][${s.id}][selling_price]`"
                                                        x-model="v.store_prices[s.id].selling_price"
                                                        placeholder="{{ __('products.store_prices.inherit_hint') }}" />
                                                    <x-admin.money-input
                                                        x-bind:name="`variants[${i}][store_prices][${s.id}][cost_price]`"
                                                        x-model="v.store_prices[s.id].cost_price"
                                                        placeholder="{{ __('products.store_prices.inherit_hint') }}" />
                                                    <x-admin.money-input
                                                        x-bind:name="`variants[${i}][store_prices][${s.id}][mrp]`"
                                                        x-model="v.store_prices[s.id].mrp"
                                                        placeholder="{{ __('products.store_prices.inherit_hint') }}" />
                                                </div>
                                            </template>
                                            </div>{{-- /.x-show store rows --}}
                                        </div>
                                    </template>
                                    </div>{{-- /.prod-variant-body --}}
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- ─────────────────────────────── KIT tab ──────────────── --}}
        {{-- Visible only when type='kit'. Each row is a component product
             (and optionally a specific variant) plus the quantity that
             ships in the bundle. --}}
        <div x-show="tab === 'kit' && isKitType" x-cloak>
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">{{ __('products.sections.kit') }}</div>
                        <div class="card-title-sub">{{ __('products.sections.kit_sub') }}</div>
                    </div>
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-primary"
                            @click="addKitItem()">
                        <x-icon name="plus" class="w-4 h-4" />
                        {{ __('products.kit.add_component') }}
                    </button>
                </div>
                <div class="card-body">
                    <template x-if="kitItems.length === 0">
                        <div class="prod-variants-empty">
                            <span class="prod-variants-empty-icon"><x-icon name="box" class="w-5 h-5" /></span>
                            <div class="prod-variants-empty-title">{{ __('products.kit.empty_title') }}</div>
                            <div class="prod-variants-empty-sub">{{ __('products.kit.empty_sub') }}</div>
                        </div>
                    </template>

                    <template x-if="kitItems.length > 0">
                        <div class="prod-variants-list">
                            <template x-for="(k, i) in kitItems" :key="k._uid">
                                <div class="prod-kit-row">
                                    <input type="hidden" :name="`kit_items[${i}][id]`" :value="k.id ?? ''">

                                    <div class="prod-kit-grid">
                                        <label class="field prod-kit-component">
                                            <span class="field-label is-required">{{ __('products.kit.fields.component') }}</span>
                                            {{-- Remote searchable picker — preloads ~25 products
                                                 on focus, searches the server as you type
                                                 (debounced). Scales to thousands of products
                                                 without bloating the page. Stable x-for key
                                                 (k._uid) keeps each row's instance intact. --}}
                                            <select x-data="remoteSelect({
                                                        url: @js(route('admin.products.search')),
                                                        value: k.component_product_id,
                                                        label: k.component_label,
                                                        exclude: {{ $isEdit ? $product->id : 'null' }},
                                                        placeholder: @js(__('products.kit.fields.component_placeholder')),
                                                        onResults: (rows) => cacheComponentVariants(rows),
                                                    })"
                                                    x-model="k.component_product_id"
                                                    :name="`kit_items[${i}][component_product_id]`"
                                                    class="pos-input"></select>
                                        </label>

                                        <label class="field prod-kit-variant">
                                            <span class="field-label">{{ __('products.kit.fields.variant') }}</span>
                                            {{-- Options + the disabled state both follow the chosen
                                                 component product, so re-sync TomSelect from the
                                                 native <select> whenever that changes. --}}
                                            <select :name="`kit_items[${i}][component_variant_id]`"
                                                    x-data="enhancedSelect()"
                                                    x-effect="variantsFor(k.component_product_id).length, syncOptions(k.component_variant_id)"
                                                    x-model="k.component_variant_id"
                                                    class="pos-input"
                                                    :disabled="variantsFor(k.component_product_id).length === 0">
                                                <option value="">{{ __('products.kit.fields.variant_any') }}</option>
                                                <template x-for="vOpt in variantsFor(k.component_product_id)" :key="vOpt.id">
                                                    <option :value="vOpt.id" x-text="vOpt.label"></option>
                                                </template>
                                            </select>
                                        </label>

                                        <label class="field prod-kit-quantity">
                                            <span class="field-label is-required">{{ __('products.kit.fields.quantity') }}</span>
                                            <input type="number" step="0.0001" min="0"
                                                   :name="`kit_items[${i}][quantity]`"
                                                   x-model="k.quantity"
                                                   class="pos-input tnum">
                                        </label>

                                        <div class="prod-kit-actions">
                                            <button type="button"
                                                    class="prod-variant-delete"
                                                    aria-label="{{ __('products.kit.remove') }}"
                                                    @click="$store.confirm.show({
                                                        title:        {{ \Illuminate\Support\Js::from(__('products.kit.delete_title')) }},
                                                        message:      {{ \Illuminate\Support\Js::from(__('products.kit.delete_confirm')) }},
                                                        intent:       'danger',
                                                        confirmLabel: {{ \Illuminate\Support\Js::from(__('products.kit.remove')) }},
                                                        onConfirm:    () => removeKitItem(i),
                                                    })">
                                                <x-icon name="trash" class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        {{-- Live components total — the basis for the kit's
                             auto-generated price (editable on the Pricing tab). --}}
                        <div class="prod-kit-total">
                            <span class="prod-kit-total-label">{{ __('products.kit.total_label') }}</span>
                            <span class="prod-kit-total-values">
                                <span>{{ __('products.kit.total_cost') }} <strong x-text="componentsTotalCostFormatted"></strong></span>
                                <span class="prod-kit-total-sep">·</span>
                                <span>{{ __('products.kit.total_sell') }} <strong x-text="componentsTotalSellFormatted"></strong></span>
                            </span>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- ─────────────────────────────── INVENTORY tab ───────── --}}
        <div x-show="tab === 'inventory'" x-cloak>
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">{{ __('products.sections.inventory') }}</div>
                        <div class="card-title-sub">{{ __('products.sections.inventory_sub') }}</div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-stack">
                        <label class="field-toggle">
                            <input type="hidden" name="track_stock" value="0">
                            <input type="checkbox" name="track_stock" value="1"
                                   @checked($bool('track_stock', true))>
                            <span>{{ __('products.fields.track_stock') }}</span>
                        </label>

                        <div x-data="{ weighed: {{ $bool('sold_by_weight') ? 'true' : 'false' }} }">
                            <label class="field-toggle">
                                <input type="hidden" name="sold_by_weight" value="0">
                                <input type="checkbox" name="sold_by_weight" value="1"
                                       @checked($bool('sold_by_weight'))
                                       @change="weighed = $event.target.checked">
                                <span>{{ __('products.fields.sold_by_weight') }}</span>
                            </label>

                            {{-- Scale PLU — the item code printed inside scale
                                 barcodes. Only relevant for weighed items, so it
                                 reveals when "Sold by weight" is on. Decoded at
                                 the cashier per Settings → Scale. --}}
                            <label class="field mt-3" x-show="weighed" x-cloak>
                                <span class="field-label">{{ __('products.fields.scale_plu') }}</span>
                                <input type="number"
                                       name="scale_plu"
                                       class="pos-input"
                                       value="{{ $val('scale_plu') }}"
                                       min="0" step="1" inputmode="numeric"
                                       autocomplete="off"
                                       placeholder="{{ __('products.fields.scale_plu_placeholder') }}">
                                <span class="field-help">{{ __('products.fields.scale_plu_help') }}</span>
                            </label>
                        </div>

                        <label class="field-toggle">
                            <input type="hidden" name="track_batches" value="0">
                            <input type="checkbox" name="track_batches" value="1"
                                   @checked($bool('track_batches'))>
                            <span>{{ __('products.fields.track_batches') }}</span>
                        </label>

                        <label class="field-toggle">
                            <input type="hidden" name="track_expiry" value="0">
                            <input type="checkbox" name="track_expiry" value="1"
                                   @checked($bool('track_expiry'))>
                            <span>{{ __('products.fields.track_expiry') }}</span>
                        </label>

                        {{-- Default product-level expiry date. Per-batch expiry
                             will override this once the inventory module tracks
                             batches; until then this is the date that appears
                             on the label / receipt. --}}
                        <label class="field">
                            <span class="field-label">{{ __('products.fields.expiry_date') }}</span>
                            {{-- type="text" + js-datepicker class — Flatpickr scans
                                 the class selector on DOMContentLoaded and upgrades
                                 the field to a themed calendar. Native `type="date"`
                                 would briefly show the OS picker before Flatpickr
                                 mounted, even when wrapped successfully. --}}
                            <input type="text"
                                   class="pos-input js-datepicker"
                                   name="expiry_date"
                                   value="{{ $val('expiry_date') }}"
                                   placeholder="YYYY-MM-DD"
                                   autocomplete="off"
                                   data-min-date="today">
                            <p class="field-help">{{ __('products.fields.expiry_date_help') }}</p>
                        </label>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="field">
                                <span class="field-label">{{ __('products.fields.reorder_level') }}</span>
                                <input type="number" step="0.0001" min="0"
                                       name="reorder_level"
                                       value="{{ $val('reorder_level') }}"
                                       class="pos-input tnum">
                                <p class="field-help">{{ __('products.fields.reorder_level_help') }}</p>
                            </label>
                            <label class="field">
                                <span class="field-label">{{ __('products.fields.reorder_quantity') }}</span>
                                <input type="number" step="0.0001" min="0"
                                       name="reorder_quantity"
                                       value="{{ $val('reorder_quantity') }}"
                                       class="pos-input tnum">
                                <p class="field-help">{{ __('products.fields.reorder_quantity_help') }}</p>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ─────────────────────────────── COMPLIANCE tab ──────── --}}
        <div x-show="tab === 'compliance'" x-cloak>
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">{{ __('products.sections.compliance') }}</div>
                        <div class="card-title-sub">{{ __('products.sections.compliance_sub') }}</div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-stack">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="field">
                                <span class="field-label">{{ __('products.fields.hsn_code') }}</span>
                                <input type="text" name="hsn_code"
                                       value="{{ $val('hsn_code') }}"
                                       class="pos-input mono"
                                       maxlength="32">
                            </label>
                            <label class="field">
                                <span class="field-label">{{ __('products.fields.pharmacy_schedule') }}</span>
                                <select x-data="enhancedSelect()" name="pharmacy_schedule" class="pos-input">
                                    <option value="">{{ __('products.fields.pharmacy_schedule_none') }}</option>
                                    @foreach ($pharmacySchedules as $sched)
                                        <option value="{{ $sched->code }}" @selected((string) $val('pharmacy_schedule') === $sched->code)>
                                            {{ $sched->code }} — {{ $sched->name }}@if ($sched->country_code) ({{ $sched->country_code }}) @endif
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <label class="field">
                            <span class="field-label">{{ __('products.fields.generic_name') }}</span>
                            <input type="text" name="generic_name"
                                   value="{{ $val('generic_name') }}"
                                   class="pos-input"
                                   maxlength="191">
                        </label>

                        <label class="field">
                            <span class="field-label">{{ __('products.fields.manufacturer') }}</span>
                            <input type="text" name="manufacturer"
                                   value="{{ $val('manufacturer') }}"
                                   class="pos-input"
                                   maxlength="191">
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </form>

    {{-- Delete confirmation form — submitted by the confirm dialog's
         onConfirm. Lives outside the main form so the delete POST
         isn't tangled with the save POST. --}}
    @if ($isEdit)
        <form id="prod-delete-form"
              method="POST"
              action="{{ $deleteUrl }}"
              class="hidden">
            @csrf
            @method('DELETE')
        </form>
    @endif
</div>
