{{--
    Table-view rows for the Products list.

    Rendered two ways:
      - Inline by `admin.products.index` for the first page.
      - Standalone by `ProductController@rows` (JSON `html`) for every
        subsequent page / search / filter / sort, then swapped into the
        `<tbody>` by the `dataTableServer` mixin.

    Because this partial is swapped in via innerHTML, every Alpine
    directive here is re-wired by `Alpine.initTree()` after the swap.
    It runs inside the `productsPage()` scope, so `rowState`, `togglingIds`,
    `toggleField`, `marginPct` and `marginClass` all resolve.

    Expects: $products (iterable of Product, with category/brand/unit
    loaded and variants_count / kit_items_count present).
--}}
@foreach ($products as $p)
    <tr data-dt-row
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
        class="prod-row"
        @click="window.location.href = {{ \Illuminate\Support\Js::from(route('admin.products.edit', $p)) }}">
        <td>
            <div class="prod-name-cell">
                <span class="prod-thumb" aria-hidden="true">
                    @if ($p->image_url)
                        <img src="{{ $p->image_url }}" alt="" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex'">
                        <span class="prod-thumb-letter hidden">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($p->name, 0, 1)) }}</span>
                    @else
                        <span class="prod-thumb-letter">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($p->name, 0, 1)) }}</span>
                    @endif
                </span>
                <div>
                    <div class="prod-name">
                        <span>{{ $p->name }}</span>
                        {{-- Inline "Featured" pill — bound to reactive
                             `rowState[id].is_featured`. Mutating that
                             from the toggle button triggers Alpine to
                             re-render this binding in place. --}}
                        <span class="prod-featured-badge"
                              x-show="rowState[{{ $p->id }}]?.is_featured"
                              x-cloak>
                            {{ __('products.list.featured') }}
                        </span>
                    </div>
                    @if ($p->short_description)
                        <div class="prod-sub">{{ \Illuminate\Support\Str::limit($p->short_description, 80) }}</div>
                    @endif
                </div>
            </div>
        </td>
        <td class="mono">{{ $p->sku }}</td>
        <td>
            @if ($p->category)
                <span class="prod-tag">{{ $p->category->name }}</span>
            @else
                <span class="prod-muted">{{ __('products.list.no_category') }}</span>
            @endif
        </td>
        <td>
            @php
                $typeCount = $p->type === 'variant' ? $p->variants_count
                    : ($p->type === 'kit' ? $p->kit_items_count : null);
            @endphp
            <span class="prod-type-text is-{{ $p->type }}">
                {{ __('products.list.type_'.$p->type) }}@if ($typeCount) · {{ $typeCount }}@endif
            </span>
        </td>
        <td class="num tnum">
            @if ($p->sale_price !== null)
                <span class="prod-card-price-was">{{ format_money($p->selling_price) }}</span>
            @endif
            {{ format_money($p->charge_price) }}
        </td>
        <td class="num tnum prod-muted">{{ format_money($p->cost_price) }}</td>
        {{-- Margin — computed client-side from the same data-dt-*
             attrs the sort + filter use. Color thresholds match
             the mockup (≥35% positive, <20% danger). --}}
        <td class="num tnum"
            :class="marginClass($el.closest('tr'))"
            x-text="marginPct($el.closest('tr')) + '%'"></td>
        <td>
            {{-- Status badge bound to reactive rowState — Alpine
                 re-renders when the toggle button flips it. --}}
            <template x-if="rowState[{{ $p->id }}]?.is_active">
                <span class="prod-badge prod-badge-positive">{{ __('products.list.active') }}</span>
            </template>
            <template x-if="!rowState[{{ $p->id }}]?.is_active">
                <span class="prod-badge prod-badge-muted">{{ __('products.list.inactive') }}</span>
            </template>
        </td>
        <td>
            {{-- Quick-action buttons — featured star + status toggle.
                 Both use @click.stop to prevent the row's click-to-edit.
                 AJAX via productsPage.toggleField().

                 `title` attribute gives a native tooltip with state-aware
                 copy (e.g. "Click to feature" when off, "Featured — click
                 to remove" when on). --}}
            <div class="prod-row-actions">
                <button type="button"
                        class="prod-fav-btn"
                        :class="{ 'is-on': rowState[{{ $p->id }}]?.is_featured }"
                        :aria-pressed="rowState[{{ $p->id }}]?.is_featured ? 'true' : 'false'"
                        :title="rowState[{{ $p->id }}]?.is_featured ? @js(__('products.list.tip_featured_on')) : @js(__('products.list.tip_featured_off'))"
                        :disabled="togglingIds.includes({{ $p->id }})"
                        @click.stop="toggleField({{ $p->id }}, 'is_featured')"
                        aria-label="{{ __('products.list.toggle_featured') }}">
                    <x-icon name="star" class="w-4 h-4" />
                </button>

                <button type="button"
                        class="prod-status-btn"
                        :class="{ 'is-on': rowState[{{ $p->id }}]?.is_active }"
                        :aria-pressed="rowState[{{ $p->id }}]?.is_active ? 'true' : 'false'"
                        :title="rowState[{{ $p->id }}]?.is_active ? @js(__('products.list.tip_active_on')) : @js(__('products.list.tip_active_off'))"
                        :disabled="togglingIds.includes({{ $p->id }})"
                        @click.stop="toggleField({{ $p->id }}, 'is_active')"
                        aria-label="{{ __('products.list.toggle_active') }}">
                    <span class="prod-status-thumb" aria-hidden="true"></span>
                </button>

                <x-admin.row-actions>
                    <x-admin.row-action :href="route('admin.products.edit', $p)" icon="edit" :label="__('table.action.edit')" />
                    <div class="row-action-sep"></div>
                    <x-admin.row-action icon="trash" variant="danger" :label="__('products.actions.delete')"
                        @click="$store.confirm.show({
                            title:        {{ \Illuminate\Support\Js::from($p->name) }},
                            message:      {{ \Illuminate\Support\Js::from(__('products.actions.delete_confirm')) }},
                            intent:       'danger',
                            confirmLabel: {{ \Illuminate\Support\Js::from(__('products.actions.delete')) }},
                            cancelLabel:  {{ \Illuminate\Support\Js::from(__('products.actions.cancel')) }},
                            onConfirm:    () => $deleteForm({{ \Illuminate\Support\Js::from(route('admin.products.destroy', $p)) }}),
                        })" />
                </x-admin.row-actions>
            </div>
        </td>
    </tr>
@endforeach
