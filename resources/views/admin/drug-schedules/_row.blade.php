{{--
    One drug-schedule list row. Required variables:
      $r — App\Models\DrugSchedule
--}}
<li data-id="{{ $r->id }}" data-dt-row data-dt-name="{{ $r->name }}" data-dt-id="{{ $r->id }}">
    <div class="ds-row"
         :class="{
             'is-selected': isEdit && form.id === {{ $r->id }},
             'is-inactive': rowsById[{{ $r->id }}]?.is_active === false,
         }">
        <button type="button"
                @click="openEdit({{ $r->id }})"
                class="ds-row-link">
            <span class="ds-tile ds-row-code" aria-hidden="true">{{ $r->code }}</span>
            <div class="ds-row-body">
                <div class="ds-row-name">
                    {{ $r->name }}
                    <span class="prod-badge ms-2"
                          :class="rowsById[{{ $r->id }}]?.is_active ? 'prod-badge-positive' : 'prod-badge-muted'"
                          x-text="rowsById[{{ $r->id }}]?.is_active ? @js(__('table.active')) : @js(__('table.inactive'))">
                    </span>
                </div>
                <div class="ds-row-meta">
                    @if ($r->description)
                        {{ \Illuminate\Support\Str::limit($r->description, 80) }}
                    @else
                        {{ __('drug_schedules.editor.empty_sub') }}
                    @endif
                </div>
            </div>
            @if ($r->country_code)
                <span class="ds-row-tag">{{ $r->country_code }}</span>
            @endif
        </button>

        <button type="button"
                class="ds-row-toggle"
                :class="{ 'is-on': rowsById[{{ $r->id }}]?.is_active }"
                :aria-pressed="rowsById[{{ $r->id }}]?.is_active ? 'true' : 'false'"
                @click.stop="toggleActive({{ $r->id }})"
                :disabled="togglingIds.includes({{ $r->id }})"
                aria-label="{{ __('drug_schedules.row.toggle_active') }}">
            <span class="ds-row-toggle-thumb" aria-hidden="true"></span>
        </button>
        <x-admin.row-actions>
            <x-admin.row-action icon="edit" :label="__('table.action.edit')" @click="openEdit({{ $r->id }})" />
            <div class="row-action-sep"></div>
            <x-admin.row-action icon="trash" variant="danger" :label="__('drug_schedules.actions.delete')"
                @click="confirmDeleteRow({{ $r->id }}, {{ \Illuminate\Support\Js::from(__('drug_schedules.actions.delete_confirm')) }})" />
        </x-admin.row-actions>
    </div>
</li>
