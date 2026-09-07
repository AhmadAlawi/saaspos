<x-admin-layout
    active="product-labels"
    :title="__('labels.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('labels.crumb_parent'), 'href' => '/admin/products'],
        ['label' => __('labels.title')],
    ]">

    <div class="page-wide"
         x-data="labelWizard({ defaultLayout: @js($defaultLayout) })">

        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('labels.title') }}</h1>
                <p class="page-sub">{{ __('labels.sub') }}</p>
            </div>
            <a class="pos-btn pos-btn-ghost" target="_blank"
               :href="'{{ route('admin.products.labels.designer.edit', '__LAYOUT__') }}'.replace('__LAYOUT__', layout)">
                {{ __('labels.designer.customize') }}
            </a>
        </div>

        <form method="POST" action="{{ route('admin.products.labels.sheet') }}" target="_blank">
            @csrf

            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">
                {{-- Left: product picker + chosen rows --}}
                <div class="space-y-5">
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('labels.products.title') }}</div>
                            <div class="card-title-sub">{{ __('labels.products.sub') }}</div>
                        </div></div>
                        <div class="card-body">
                            <label class="field">
                                <span class="field-label">{{ __('labels.products.add') }}</span>
                                {{-- No `name` → never submits; it only feeds rows. --}}
                                <input type="text" class="pos-input" autocomplete="off"
                                       placeholder="{{ __('labels.products.barcode_placeholder') }}"
                                       x-model="barcodeInput"
                                       @keydown.enter.prevent="addFromBarcode($event)">
                            </label>
                            <p class="field-help text-red-600" x-show="barcodeNotFound" x-cloak
                               x-text="@js(__('labels.products.barcode_not_found'))"></p>

                            <div class="label-rows" x-show="hasRows" x-cloak>
                                <template x-for="r in rows" :key="r.id">
                                    <div class="label-row">
                                        <input type="hidden" name="product_id[]" :value="r.id">
                                        <span class="label-row-name" x-text="r.label"></span>
                                        <div class="label-row-qty">
                                            <span class="label-row-qty-label">{{ __('labels.products.qty') }}</span>
                                            <input type="number" name="quantity[]" class="pos-input"
                                                   x-model="r.qty" min="1" max="500" step="1">
                                        </div>
                                        <button type="button" class="prod-del-btn" @click="removeRow(r.id)"
                                                aria-label="{{ __('labels.products.remove') }}">
                                            <x-icon name="trash" class="w-4 h-4" />
                                        </button>
                                    </div>
                                </template>
                            </div>

                            <div class="label-empty" x-show="!hasRows" x-cloak>
                                {{ __('labels.products.empty') }}
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Right: layout + fields + generate --}}
                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('labels.options.title') }}</div>
                    </div></div>
                    <div class="card-body">
                        <div class="form-stack">
                            {{-- Grouped so a shop on a thermal label roll finds
                                 its stock size without wading through the A4
                                 Avery grids (and vice-versa). --}}
                            {{-- preserveKeys=true — the layout key is the form value. --}}
                            @php($grouped = collect($layouts)->groupBy(fn ($c) => $c['type'] ?? 'sheet', true))
                            <label class="field">
                                <span class="field-label">{{ __('labels.options.layout') }}</span>
                                <select name="layout" class="pos-input"
                                        x-data="enhancedSelect({ value: layout })"
                                        x-model="layout">
                                    @foreach (['sheet' => __('labels.options.group_sheet'), 'roll' => __('labels.options.group_roll')] as $type => $groupLabel)
                                        @if ($grouped->has($type))
                                            <optgroup label="{{ $groupLabel }}">
                                                @foreach ($grouped[$type] as $key => $cfg)
                                                    <option value="{{ $key }}">{{ $cfg['label'] }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                    @endforeach
                                </select>
                            </label>
                            <p class="field-help">{{ __('labels.options.layout_help') }}</p>

                            <div class="field">
                                <span class="field-label">{{ __('labels.options.fields') }}</span>
                                <div class="label-fields">
                                    <label class="field-toggle">
                                        <input type="checkbox" name="fields[]" value="name" x-model="fields.name">
                                        <span>{{ __('labels.options.field_name') }}</span>
                                    </label>
                                    <label class="field-toggle">
                                        <input type="checkbox" name="fields[]" value="sku" x-model="fields.sku">
                                        <span>{{ __('labels.options.field_sku') }}</span>
                                    </label>
                                    <label class="field-toggle">
                                        <input type="checkbox" name="fields[]" value="price" x-model="fields.price">
                                        <span>{{ __('labels.options.field_price') }}</span>
                                    </label>
                                    <label class="field-toggle">
                                        <input type="checkbox" name="fields[]" value="barcode" x-model="fields.barcode">
                                        <span>{{ __('labels.options.field_barcode') }}</span>
                                    </label>
                                </div>
                            </div>

                            <p class="label-summary" x-show="hasRows" x-cloak
                               x-text="@js(__('labels.options.summary')).replace(':count', totalLabels)"></p>

                            <button type="submit"
                                    class="pos-btn pos-btn-primary w-full"
                                    :disabled="!canGenerate">
                                <x-icon name="barcode" class="w-4 h-4" />
                                {{ __('labels.options.generate') }}
                            </button>
                            <p class="field-help">{{ __('labels.options.generate_help') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>
