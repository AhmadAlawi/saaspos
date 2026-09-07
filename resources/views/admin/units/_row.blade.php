{{--
    One unit list row. Used by:
      - the main list (index.blade.php) on first paint
      - UnitController::store/update/destroy() which return the freshly-
        rendered list HTML so the client can swap it in one go.

    Required variables:
      - $u             App\Models\Unit
      - $baseUnitNames Collection<int, string>  id → "Name (code)" lookup
--}}
<li data-id="{{ $u->id }}" data-dt-row data-dt-name="{{ $u->name }}" data-dt-id="{{ $u->id }}">
    <div class="unit-row"
         :class="{
             'is-selected': isEdit && form.id === {{ $u->id }},
             'is-inactive': rowsById[{{ $u->id }}]?.is_active === false,
         }">
        <button type="button"
                @click="openEdit({{ $u->id }})"
                class="unit-row-link">
            <span class="unit-tile" aria-hidden="true">{{ $u->code }}</span>
            <div class="unit-row-body">
                <div class="unit-row-name">
                    {{ $u->name }}
                    <span class="prod-badge ms-2"
                          :class="rowsById[{{ $u->id }}]?.is_active ? 'prod-badge-positive' : 'prod-badge-muted'"
                          x-text="rowsById[{{ $u->id }}]?.is_active ? @js(__('table.active')) : @js(__('table.inactive'))">
                    </span>
                </div>
                <div class="unit-row-meta">
                    <span class="capitalize">{{ __('units.categories.'.$u->category) }}</span>
                    @if ($u->base_unit_id && isset($baseUnitNames[$u->base_unit_id]))
                        <span class="sep">·</span>
                        {{ __('units.list.derived_of', [
                            'code'      => $u->code,
                            'factor'    => rtrim(rtrim((string) $u->conversion_factor, '0'), '.'),
                            'base_code' => $u->baseUnit?->code ?? '',
                        ]) }}
                    @endif
                </div>
            </div>
            @if (! $u->base_unit_id)
                <span class="unit-row-tag unit-row-tag-base">{{ __('units.list.base') }}</span>
            @endif
        </button>

        <button type="button"
                class="unit-row-toggle"
                :class="{ 'is-on': rowsById[{{ $u->id }}]?.is_active }"
                :aria-pressed="rowsById[{{ $u->id }}]?.is_active ? 'true' : 'false'"
                @click.stop="toggleActive({{ $u->id }})"
                :disabled="togglingIds.includes({{ $u->id }})"
                aria-label="{{ __('units.row.toggle_active') }}">
            <span class="unit-row-toggle-thumb" aria-hidden="true"></span>
        </button>
        <x-admin.row-actions>
            <x-admin.row-action icon="edit" :label="__('table.action.edit')" @click="openEdit({{ $u->id }})" />
            <div class="row-action-sep"></div>
            <x-admin.row-action icon="trash" variant="danger" :label="__('units.actions.delete')"
                @click="confirmDeleteRow({{ $u->id }}, {{ \Illuminate\Support\Js::from(__('units.actions.delete_confirm')) }})" />
        </x-admin.row-actions>
    </div>
</li>
