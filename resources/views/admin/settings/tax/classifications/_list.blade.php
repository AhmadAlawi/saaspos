{{-- One row per tax classification. Rendered server-side on page load AND
     returned as `list_html` from AJAX endpoints. Uses the unit-row CSS
     classes since they produce the same layout needed here. --}}
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
                @if ($row->description)
                    <div class="unit-row-meta">{{ $row->description }}</div>
                @else
                    <div class="unit-row-meta mono">{{ $row->slug }}</div>
                @endif
            </div>
        </button>

        <button type="button"
                class="unit-row-toggle"
                :class="{ 'is-on': rowsById[{{ $row->id }}]?.is_active }"
                :aria-pressed="rowsById[{{ $row->id }}]?.is_active ? 'true' : 'false'"
                @click.stop="toggleActive({{ $row->id }})"
                :disabled="togglingIds.includes({{ $row->id }})"
                aria-label="{{ __('tax.classifications.fields.is_active') }}">
            <span class="unit-row-toggle-thumb" aria-hidden="true"></span>
        </button>

        <x-admin.row-actions>
            <x-admin.row-action icon="edit" :label="__('table.action.edit')" @click="openEdit({{ $row->id }})" />
            <div class="row-action-sep"></div>
            <x-admin.row-action icon="trash" variant="danger" :label="__('tax.classifications.actions.delete')"
                @click="confirmDeleteRow({{ $row->id }}, {{ \Illuminate\Support\Js::from(__('tax.classifications.actions.delete_confirm')) }})" />
        </x-admin.row-actions>
    </div>
</li>
@endforeach
