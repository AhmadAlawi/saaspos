@foreach ($rows as $row)
    <tr class="prod-row"
        data-dt-row
        data-id="{{ $row->id }}"
        data-dt-name="{{ $row->name }}"
        data-dt-id="{{ $row->id }}"
        :class="{ 'is-selected': form.id === {{ $row->id }} }"
        @click="openEdit({{ $row->id }})">
        <td>{{ $row->name }}</td>
        <td>
            <span class="prod-badge"
                  :class="rowsById[{{ $row->id }}]?.is_active ? 'prod-badge-positive' : 'prod-badge-muted'"
                  x-text="rowsById[{{ $row->id }}]?.is_active ? @js(__('table.active')) : @js(__('table.inactive'))">
            </span>
        </td>
        <td>
            <div class="prod-row-actions">
                <button type="button"
                        class="prod-status-btn"
                        :class="{ 'is-on': rowsById[{{ $row->id }}]?.is_active }"
                        :disabled="togglingIds.includes({{ $row->id }})"
                        @click.stop="toggleActive({{ $row->id }})"
                        :aria-label="@js(__('expense_categories.actions.toggle_active'))"
                        :title="@js(__('expense_categories.actions.toggle_active'))">
                    <span class="prod-status-thumb" aria-hidden="true"></span>
                </button>

                <x-admin.row-actions>
                    <x-admin.row-action icon="edit" :label="__('table.action.edit')" @click="openEdit({{ $row->id }})" />
                    <div class="row-action-sep"></div>
                    <x-admin.row-action icon="trash" variant="danger" :label="__('expense_categories.actions.delete')"
                        @click="confirmDeleteRow({{ $row->id }})" />
                </x-admin.row-actions>
            </div>
        </td>
    </tr>
@endforeach
