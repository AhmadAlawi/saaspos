{{-- Reason rows. Rendered both on the server (initial page render) and
     returned as `list_html` from the AJAX save/delete endpoints, so the
     client can drop in fresh `<tr>`s without touching the rest of the
     table chrome. `data-dt-row` is the hook the data-table mixin would
     use; safe to keep here for future search/pagination wiring. --}}
@foreach ($rows as $row)
    <tr class="prod-row"
        data-dt-row
        data-id="{{ $row->id }}"
        data-dt-name="{{ $row->name }}"
        data-dt-id="{{ $row->id }}"
        :class="{ 'is-selected': form.id === {{ $row->id }} }"
        @click="openEdit({{ $row->id }})">
        <td class="mono">{{ $row->code }}</td>
        <td>{{ $row->name }}</td>
        <td class="tnum">{{ $row->sort_order }}</td>
        <td>
            <span class="prod-badge"
                  :class="rowsById[{{ $row->id }}]?.is_active ? 'prod-badge-positive' : 'prod-badge-muted'"
                  x-text="rowsById[{{ $row->id }}]?.is_active ? @js(__('table.active')) : @js(__('table.inactive'))">
            </span>
        </td>
        <td>
            <div class="prod-row-actions">
                {{-- Status toggle — AJAX flip without opening the editor.
                     Mirrors customer-groups / brands / units convention. --}}
                <button type="button"
                        class="prod-status-btn"
                        :class="{ 'is-on': rowsById[{{ $row->id }}]?.is_active }"
                        :disabled="togglingIds.includes({{ $row->id }})"
                        @click.stop="toggleActive({{ $row->id }})"
                        :aria-label="rowsById[{{ $row->id }}]?.is_active
                            ? @js(__('inventory.reasons.badges.active'))
                            : @js(__('inventory.reasons.badges.inactive'))"
                        :title="rowsById[{{ $row->id }}]?.is_active
                            ? @js(__('inventory.reasons.badges.active'))
                            : @js(__('inventory.reasons.badges.inactive'))">
                    <span class="prod-status-thumb" aria-hidden="true"></span>
                </button>

                <x-admin.row-actions>
                    <x-admin.row-action icon="edit" :label="__('table.action.edit')" @click="openEdit({{ $row->id }})" />
                    <div class="row-action-sep"></div>
                    <x-admin.row-action icon="trash" variant="danger" :label="__('inventory.reasons.actions.delete')"
                        @click="confirmDeleteRow({{ $row->id }})" />
                </x-admin.row-actions>
            </div>
        </td>
    </tr>
@endforeach
