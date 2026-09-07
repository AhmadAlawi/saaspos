{{-- One row per unit category. Rendered on initial page load AND returned
     as `list_html` from the AJAX save/delete endpoints. Mirrors the structure
     of admin/units/_row.blade.php so the same CSS applies. --}}
@foreach ($rows as $row)
<li data-id="{{ $row->id }}"
    data-dt-row
    data-dt-name="{{ $row->name }}"
    data-dt-id="{{ $row->id }}">
    <div class="unit-row"
         :class="{
             'is-selected': isEdit && form.id === {{ $row->id }},
             'is-inactive': rowsById[{{ $row->id }}]?.is_active === false,
         }">
        <button type="button"
                @click="openEdit({{ $row->id }})"
                class="unit-row-link">
            <span class="unit-tile" aria-hidden="true">{{ strtoupper(substr($row->slug, 0, 2)) }}</span>
            <div class="unit-row-body">
                <div class="unit-row-name">
                    {{ $row->name }}
                    <span class="prod-badge ms-2"
                          :class="rowsById[{{ $row->id }}]?.is_active ? 'prod-badge-positive' : 'prod-badge-muted'"
                          x-text="rowsById[{{ $row->id }}]?.is_active ? @js(__('table.active')) : @js(__('table.inactive'))">
                    </span>
                </div>
                <div class="unit-row-meta mono">{{ $row->slug }}</div>
            </div>
        </button>

        <button type="button"
                class="unit-row-toggle"
                :class="{ 'is-on': rowsById[{{ $row->id }}]?.is_active }"
                :aria-pressed="rowsById[{{ $row->id }}]?.is_active ? 'true' : 'false'"
                @click.stop="toggleActive({{ $row->id }})"
                :disabled="togglingIds.includes({{ $row->id }})"
                aria-label="{{ __('unit_categories.fields.is_active') }}">
            <span class="unit-row-toggle-thumb" aria-hidden="true"></span>
        </button>

        <x-admin.row-actions>
            <x-admin.row-action icon="edit" :label="__('table.action.edit')" @click="openEdit({{ $row->id }})" />
            <div class="row-action-sep"></div>
            <x-admin.row-action icon="trash" variant="danger" :label="__('unit_categories.actions.delete')"
                @click="confirmDeleteRow({{ $row->id }}, {{ \Illuminate\Support\Js::from(__('unit_categories.actions.delete_confirm')) }})" />
        </x-admin.row-actions>
    </div>
</li>
@endforeach
