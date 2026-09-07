{{--
    Grid-view cards for the Products list.

    Same contract as `_rows.blade.php`: rendered inline for the first page,
    then fetched by `ProductController@rows` and *appended* to `.prod-grid`
    by the `dataTableServer` mixin as the user scrolls (infinite scroll).

    Cards carry the same `data-dt-*` attribute set as the table rows so
    `rowState` can be rebuilt from either view after a swap.

    Expects: $products (iterable of Product, with category/brand/unit
    loaded and variants_count / kit_items_count present).
--}}
@foreach ($products as $p)
    <a href="{{ route('admin.products.edit', $p) }}"
       data-dt-row
       data-dt-product-id="{{ $p->id }}"
       data-dt-name="{{ $p->name }}"
       data-dt-sku="{{ $p->sku }}"
       data-dt-category="{{ optional($p->category)->name }}"
       data-dt-category-id="{{ $p->category_id }}"
       data-dt-brand="{{ optional($p->brand)->name }}"
       data-dt-type="{{ $p->type }}"
       data-dt-price="{{ $p->selling_price }}"
       data-dt-cost="{{ $p->cost_price }}"
       data-dt-margin="{{ $p->selling_price > 0 ? round((($p->selling_price - $p->cost_price) / $p->selling_price) * 100) : 0 }}"
       data-dt-active="{{ $p->is_active ? '1' : '0' }}"
       data-dt-featured="{{ $p->is_featured ? '1' : '0' }}"
       class="prod-card">
        <div class="prod-card-img">
            @if ($p->image_url)
                <img src="{{ $p->image_url }}" alt="" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                <div class="prod-card-letter hidden">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($p->name, 0, 1)) }}</div>
            @else
                <div class="prod-card-letter">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($p->name, 0, 1)) }}</div>
            @endif
        </div>
        <div class="prod-card-body">
            <div class="prod-card-head">
                <div class="prod-card-name">{{ $p->name }}</div>
                {{-- Grid-card badge mirrors the table's state — bound to
                     the reactive rowState so the toggle button updates
                     this card in place even when the user switches view. --}}
                <template x-if="!rowState[{{ $p->id }}]?.is_active">
                    <span class="prod-badge prod-badge-muted">{{ __('products.list.inactive') }}</span>
                </template>
                <template x-if="rowState[{{ $p->id }}]?.is_active && rowState[{{ $p->id }}]?.is_featured">
                    <span class="prod-badge prod-badge-positive">{{ __('products.list.featured') }}</span>
                </template>
            </div>
            <div class="prod-card-meta">
                <span class="prod-card-sku mono">{{ $p->sku }}</span>
                @php
                    $typeCount = $p->type === 'variant' ? $p->variants_count
                        : ($p->type === 'kit' ? $p->kit_items_count : null);
                @endphp
                <span class="prod-type-badge is-{{ $p->type }}">
                    {{ __('products.list.type_'.$p->type) }}@if ($typeCount) · {{ $typeCount }}@endif
                </span>
            </div>
            <div class="prod-card-foot">
                <span class="prod-card-price tnum">
                    @if ($p->sale_price !== null)
                        <span class="prod-card-price-was">{{ format_money($p->selling_price) }}</span>
                    @endif
                    {{ format_money($p->charge_price) }}
                    @if ($p->sale_price !== null)
                        <span class="prod-badge prod-badge-warning">{{ __('products.list.on_sale') }}</span>
                    @endif
                </span>
                <span class="prod-card-cost tnum prod-muted">
                    {{ __('products.list.cost_short') }}
                    {{ format_money($p->cost_price) }}
                </span>
            </div>
        </div>
    </a>
@endforeach
