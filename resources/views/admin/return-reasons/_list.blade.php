{{-- Return-reason rows. Rendered both server-side on first paint AND
     returned as `list_html` from the AJAX save / delete endpoints so
     the client swaps fresh rows without touching the rest of the table
     chrome. Mirrors the adjustment-reasons row template. --}}
@foreach ($rows as $row)
    <tr class="prod-row"
        data-dt-row
        data-id="{{ $row->id }}"
        data-dt-name="{{ $row->name }}"
        data-dt-id="{{ $row->id }}"
        :class="{ 'is-selected': form.id === {{ $row->id }} }"
        @click="openEdit({{ $row->id }})">
        <td class="mono">{{ $row->code }}</td>
        <td>
            {{ $row->name }}
            @if ($row->default_restock)
                <span class="prod-head-badge prod-head-badge-soft ms-1">{{ __('return_reasons.badges.restock_default') }}</span>
            @else
                <span class="prod-head-badge prod-head-badge-muted ms-1">{{ __('return_reasons.badges.no_restock') }}</span>
            @endif
        </td>
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
                     Mirrors adjustment-reasons / brands / units convention. --}}
                <button type="button"
                        class="prod-status-btn"
                        :class="{ 'is-on': rowsById[{{ $row->id }}]?.is_active }"
                        :disabled="togglingIds.includes({{ $row->id }})"
                        @click.stop="toggleActive({{ $row->id }})"
                        :aria-label="rowsById[{{ $row->id }}]?.is_active
                            ? @js(__('return_reasons.badges.active'))
                            : @js(__('return_reasons.badges.inactive'))"
                        :title="rowsById[{{ $row->id }}]?.is_active
                            ? @js(__('return_reasons.badges.active'))
                            : @js(__('return_reasons.badges.inactive'))">
                    <span class="prod-status-thumb" aria-hidden="true"></span>
                </button>

                <x-admin.row-actions>
                    <x-admin.row-action icon="edit" :label="__('table.action.edit')" @click="openEdit({{ $row->id }})" />
                    <div class="row-action-sep"></div>
                    <x-admin.row-action icon="trash" variant="danger" :label="__('return_reasons.actions.delete')"
                        @click="confirmDeleteRow({{ $row->id }})" />
                </x-admin.row-actions>
            </div>
        </td>
    </tr>
@endforeach
