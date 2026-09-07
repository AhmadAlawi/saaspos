{{--
    One category list row. Used by:
      - the main list (index.blade.php) when the page first renders
      - CategoryController::update() to return fresh row HTML over JSON
        so the client can swap the row in place without a page reload

    Required variables:
      - $c             App\Models\Category (with `tree_depth` set if rendered from tree-ordered list)
      - $parentName    string|null     name of the parent category (for the meta line)
      - $taxGroupNames Illuminate\Support\Collection<int, string>   id → name lookup
--}}
<li data-id="{{ $c->id }}"
    data-dt-row
    data-dt-id="{{ $c->id }}"
    data-dt-name="{{ $c->name }}"
    data-depth="{{ $c->tree_depth ?? 0 }}"
    data-parent="{{ $c->parent_id ?? '' }}">
    <div class="cat-row"
         :class="{
             'is-selected': isEdit && form.id === {{ $c->id }},
             'is-inactive': rowsById[{{ $c->id }}]?.is_active === false,
         }">
        <button type="button" class="cat-handle" aria-label="{{ __('categories.list.drag_hint') }}">
            <x-icon name="grip" class="w-4 h-4" />
        </button>
        <button type="button"
                @click="openEdit({{ $c->id }})"
                class="cat-row-link">
            <span class="cat-tile" aria-hidden="true"
                  @if ($c->color) style="--cat-color: {{ $c->color }};" @endif>
                <x-icon name="tag" class="w-4 h-4" />
            </span>
            <div class="cat-row-body">
                <div class="cat-row-name">
                    {{ $c->name }}
                    <span class="prod-badge ms-2"
                          :class="rowsById[{{ $c->id }}]?.is_active ? 'prod-badge-positive' : 'prod-badge-muted'"
                          x-text="rowsById[{{ $c->id }}]?.is_active ? @js(__('table.active')) : @js(__('table.inactive'))">
                    </span>
                </div>
                <div class="cat-row-meta">
                    <span class="tnum">{{ (int) ($c->products_count ?? 0) }}</span> {{ __('categories.list.products') }}
                    @if ($c->parent_id)
                        <span class="sep">·</span>
                        {{ __('categories.list.under', ['parent' => $parentName ?? '—']) }}
                    @endif
                </div>
            </div>
            @if ($c->tax_group_id && isset($taxGroupNames[$c->tax_group_id]))
                <span class="cat-row-tag">{{ $taxGroupNames[$c->tax_group_id] }}</span>
            @endif
        </button>
        {{-- Status toggle — sits OUTSIDE .cat-row-link so it doesn't open
             the editor. @click.stop is defensive (z-index keeps clicks here
             anyway) and aria-pressed gives screen readers the state. --}}
        <button type="button"
                class="cat-row-toggle"
                :class="{ 'is-on': rowsById[{{ $c->id }}]?.is_active }"
                :aria-pressed="rowsById[{{ $c->id }}]?.is_active ? 'true' : 'false'"
                @click.stop="toggleActive({{ $c->id }})"
                :disabled="togglingIds.includes({{ $c->id }})"
                aria-label="{{ __('categories.row.toggle_active') }}">
            <span class="cat-row-toggle-thumb" aria-hidden="true"></span>
        </button>
        {{-- Per-row action menu. Delete is hidden for the system-default
             fallback row (non-deletable). --}}
        <x-admin.row-actions>
            <x-admin.row-action icon="edit" :label="__('table.action.edit')" @click="openEdit({{ $c->id }})" />
            @if (! $c->is_default)
                <div class="row-action-sep"></div>
                <x-admin.row-action icon="trash" variant="danger" :label="__('categories.actions.delete')"
                    @click="confirmDeleteRow({{ $c->id }}, {{ \Illuminate\Support\Js::from(__('categories.actions.delete_confirm')) }})" />
            @endif
        </x-admin.row-actions>
    </div>
</li>
