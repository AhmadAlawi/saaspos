<x-admin-layout
    active="price-rules"
    :title="$rule->exists ? __('price_rules.edit.title') : __('price_rules.create.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('price_rules.title'), 'href' => route('admin.price-rules.index')],
        ['label' => $rule->exists ? __('price_rules.edit.title') : __('price_rules.create.title')],
    ]">

    <div class="page-wide" style="max-width:720px;"
         x-data="{
            scope: {{ Js::from($rule->scope ?: 'category') }},
            productQuery: {{ Js::from($productLabel ?? '') }},
            productId: {{ Js::from($rule->product_id) }},
            productResults: [],
            searching: false,
            async searchProducts() {
                if (this.productQuery.trim().length < 2) { this.productResults = []; return; }
                this.searching = true;
                try {
                    const res = await fetch({{ Js::from(route('admin.price-rules.search-products')) }} + '?q=' + encodeURIComponent(this.productQuery), { headers: { 'Accept': 'application/json' } });
                    this.productResults = await res.json();
                } finally { this.searching = false; }
            },
            pickProduct(p) {
                this.productId = p.id;
                this.productQuery = p.name;
                this.productResults = [];
            },
         }">
        <div class="page-header mb-6">
            <h1 class="page-title">{{ $rule->exists ? __('price_rules.edit.title') : __('price_rules.create.title') }}</h1>
        </div>

        <form method="POST" action="{{ $rule->exists ? route('admin.price-rules.update', $rule) : route('admin.price-rules.store') }}" class="card">
            @csrf
            @if ($rule->exists) @method('PATCH') @endif

            <div class="card-body form-stack">
                <label class="field">
                    <span class="field-label is-required">{{ __('price_rules.fields.name') }}</span>
                    <input type="text" name="name" class="pos-input" value="{{ old('name', $rule->name) }}" required maxlength="150"
                           placeholder="{{ __('price_rules.fields.name_placeholder') }}">
                    @error('name') <p class="field-error">{{ $message }}</p> @enderror
                </label>

                <label class="field">
                    <span class="field-label is-required">{{ __('price_rules.fields.scope') }}</span>
                    <select name="scope" class="pos-input" x-model="scope">
                        <option value="{{ \App\Models\PriceRule::SCOPE_CATEGORY }}">{{ __('price_rules.scope.category') }}</option>
                        <option value="{{ \App\Models\PriceRule::SCOPE_PRODUCT }}">{{ __('price_rules.scope.product') }}</option>
                        <option value="{{ \App\Models\PriceRule::SCOPE_ALL }}">{{ __('price_rules.scope.all') }}</option>
                    </select>
                </label>

                <label class="field" x-show="scope === {{ Js::from(\App\Models\PriceRule::SCOPE_CATEGORY) }}" x-cloak>
                    <span class="field-label is-required">{{ __('price_rules.fields.category') }}</span>
                    <select name="category_id" class="pos-input">
                        <option value="">{{ __('price_rules.fields.category_placeholder') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('category_id', $rule->category_id) == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id') <p class="field-error">{{ $message }}</p> @enderror
                </label>

                <div class="field" x-show="scope === {{ Js::from(\App\Models\PriceRule::SCOPE_PRODUCT) }}" x-cloak style="position:relative;">
                    <span class="field-label is-required">{{ __('price_rules.fields.product') }}</span>
                    <input type="text" class="pos-input" x-model="productQuery" @input.debounce.300ms="searchProducts()"
                           placeholder="{{ __('price_rules.fields.product_placeholder') }}" autocomplete="off">
                    <input type="hidden" name="product_id" :value="productId">
                    <div x-show="productResults.length" x-cloak
                         style="position:absolute; top:100%; left:0; right:0; z-index:5; background:var(--bg-surface); border:1px solid var(--border-subtle); border-radius:8px; margin-top:4px; overflow:hidden;">
                        <template x-for="p in productResults" :key="p.id">
                            <button type="button" class="dropdown-item" @click="pickProduct(p)" x-text="p.name + (p.sku ? ' · ' + p.sku : '')"></button>
                        </template>
                    </div>
                    @error('product_id') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <label class="field">
                    <span class="field-label">{{ __('price_rules.fields.store') }}</span>
                    <select name="store_id" class="pos-input">
                        <option value="">{{ __('price_rules.all_stores') }}</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" @selected(old('store_id', $rule->store_id) == $store->id)>{{ $store->name }}</option>
                        @endforeach
                    </select>
                </label>

                <div style="display:flex; gap:12px;">
                    <label class="field" style="flex:1;">
                        <span class="field-label is-required">{{ __('price_rules.fields.discount_type') }}</span>
                        <select name="discount_type" class="pos-input">
                            <option value="{{ \App\Models\PriceRule::TYPE_PERCENT }}" @selected(old('discount_type', $rule->discount_type) === 'pct')>{{ __('price_rules.discount_type.pct') }}</option>
                            <option value="{{ \App\Models\PriceRule::TYPE_AMOUNT }}" @selected(old('discount_type', $rule->discount_type) === 'amt')>{{ __('price_rules.discount_type.amt') }}</option>
                        </select>
                    </label>
                    <label class="field" style="flex:1;">
                        <span class="field-label is-required">{{ __('price_rules.fields.discount_value') }}</span>
                        <input type="number" name="discount_value" class="pos-input" step="0.01" min="0.01"
                               value="{{ old('discount_value', $rule->discount_value) }}" required>
                        @error('discount_value') <p class="field-error">{{ $message }}</p> @enderror
                    </label>
                </div>

                <div style="display:flex; gap:12px;">
                    <label class="field" style="flex:1;">
                        <span class="field-label is-required">{{ __('price_rules.fields.starts_at') }}</span>
                        <input type="datetime-local" name="starts_at" class="pos-input"
                               value="{{ old('starts_at', optional($rule->starts_at)->format('Y-m-d\TH:i')) }}" required>
                    </label>
                    <label class="field" style="flex:1;">
                        <span class="field-label is-required">{{ __('price_rules.fields.ends_at') }}</span>
                        <input type="datetime-local" name="ends_at" class="pos-input"
                               value="{{ old('ends_at', optional($rule->ends_at)->format('Y-m-d\TH:i')) }}" required>
                        @error('ends_at') <p class="field-error">{{ $message }}</p> @enderror
                    </label>
                </div>

                <label class="field-toggle">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $rule->is_active ?? true))>
                    <span>{{ __('price_rules.fields.is_active') }}</span>
                </label>
            </div>

            <div class="card-footer flex items-center justify-end gap-2">
                <a href="{{ route('admin.price-rules.index') }}" class="pos-btn pos-btn-ghost">{{ __('price_rules.actions.cancel') }}</a>
                <button type="submit" class="pos-btn pos-btn-primary">{{ __('price_rules.actions.save') }}</button>
            </div>
        </form>
    </div>
</x-admin-layout>
