{{--
    One brand list row. Used by:
      - the main list (index.blade.php) on first paint
      - BrandController::store/update/destroy() which return the freshly-
        rendered list HTML so the client can swap it in one go.

    Required variables:
      - $b   App\Models\Brand
--}}
<li data-id="{{ $b->id }}" data-dt-row data-dt-name="{{ $b->name }}" data-dt-id="{{ $b->id }}">
    <div class="brand-row"
         :class="{
             'is-selected': isEdit && form.id === {{ $b->id }},
             'is-inactive': rowsById[{{ $b->id }}]?.is_active === false,
         }">
        <button type="button"
                @click="openEdit({{ $b->id }})"
                class="brand-row-link">
            {{-- Logo tile: shows the stored image when present, falls
                 back to the brand's initial letter. The `@error` hook
                 also flips to the letter when the image URL 404s, so a
                 missing / moved file never renders as a broken-image
                 icon. Reactive on the client side so an in-place save
                 updates the thumb. --}}
            <span class="brand-tile" aria-hidden="true">
                <template x-if="brandHasLogo({{ $b->id }})">
                    <img :src="rowsById[{{ $b->id }}].logo_url"
                         alt=""
                         class="brand-tile-img"
                         @@error="_onLogoError({{ $b->id }})">
                </template>
                <template x-if="!brandHasLogo({{ $b->id }})">
                    <span>{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($b->name, 0, 1)) }}</span>
                </template>
            </span>
            <div class="brand-row-body">
                <div class="brand-row-name">
                    {{ $b->name }}
                    <span class="prod-badge ms-2"
                          :class="rowsById[{{ $b->id }}]?.is_active ? 'prod-badge-positive' : 'prod-badge-muted'"
                          x-text="rowsById[{{ $b->id }}]?.is_active ? @js(__('table.active')) : @js(__('table.inactive'))">
                    </span>
                </div>
                <div class="brand-row-meta">
                    @if ($b->description)
                        {{ \Illuminate\Support\Str::limit($b->description, 60) }}
                    @else
                        <span class="tnum">{{ (int) ($b->products_count ?? 0) }}</span> {{ __('brands.list.products') }}
                    @endif
                </div>
            </div>
        </button>

        <button type="button"
                class="brand-row-toggle"
                :class="{ 'is-on': rowsById[{{ $b->id }}]?.is_active }"
                :aria-pressed="rowsById[{{ $b->id }}]?.is_active ? 'true' : 'false'"
                @click.stop="toggleActive({{ $b->id }})"
                :disabled="togglingIds.includes({{ $b->id }})"
                aria-label="{{ __('brands.row.toggle_active') }}">
            <span class="brand-row-toggle-thumb" aria-hidden="true"></span>
        </button>
        {{-- Per-row action menu. Delete hidden for the system-default fallback row. --}}
        <x-admin.row-actions>
            <x-admin.row-action icon="edit" :label="__('table.action.edit')" @click="openEdit({{ $b->id }})" />
            @if (! $b->is_default)
                <div class="row-action-sep"></div>
                <x-admin.row-action icon="trash" variant="danger" :label="__('brands.actions.delete')"
                    @click="confirmDeleteRow({{ $b->id }}, {{ \Illuminate\Support\Js::from(__('brands.actions.delete_confirm')) }})" />
            @endif
        </x-admin.row-actions>
    </div>
</li>
