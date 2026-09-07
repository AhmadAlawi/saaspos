{{--
    One terminal list row. Used by the main list (index.blade.php) and by
    TerminalController::store/update/destroy() which return the freshly-
    rendered list HTML for a one-shot client swap.

    Required variables:
      - $t   App\Models\Terminal  (with `store`)
--}}
<li data-id="{{ $t->id }}" data-dt-row data-dt-name="{{ $t->name }} {{ $t->code }}" data-dt-id="{{ $t->id }}">
    <div class="term-row"
         :class="{ 'is-inactive': rowsById[{{ $t->id }}]?.is_active === false }">
        <a href="{{ route('admin.terminals.edit', $t) }}" class="term-row-link">
            <span class="term-tile" aria-hidden="true"><x-icon name="pos" class="w-4 h-4" /></span>
            <div class="term-row-body">
                <div class="term-row-name">
                    {{ $t->name }}
                    <span class="prod-badge ms-2"
                          :class="rowsById[{{ $t->id }}]?.is_active ? 'prod-badge-positive' : 'prod-badge-muted'"
                          x-text="rowsById[{{ $t->id }}]?.is_active ? @js(__('table.active')) : @js(__('table.inactive'))">
                    </span>
                </div>
                <div class="term-row-meta">
                    <span class="mono">{{ $t->code }}</span>
                </div>
            </div>
        </a>

        {{-- "This device" badge stays inline as a state indicator — it shows
             which terminal this workstation is currently bound to. --}}
        <button type="button"
                class="term-row-active"
                x-show="activeTerminalId === {{ $t->id }}"
                x-cloak
                @click.stop="clearSelection()"
                :title="@js(__('terminals.row.stop_using'))">
            <x-icon name="check" class="w-3.5 h-3.5" />
            <span>{{ __('terminals.row.this_device') }}</span>
        </button>

        <button type="button"
                class="term-row-toggle"
                :class="{ 'is-on': rowsById[{{ $t->id }}]?.is_active }"
                :aria-pressed="rowsById[{{ $t->id }}]?.is_active ? 'true' : 'false'"
                @click.stop="toggleActive({{ $t->id }})"
                :disabled="togglingIds.includes({{ $t->id }})"
                aria-label="{{ __('terminals.row.toggle_active') }}">
            <span class="term-row-toggle-thumb" aria-hidden="true"></span>
        </button>

        <x-admin.row-actions>
            <x-admin.row-action icon="edit" :label="__('table.action.edit')" :href="route('admin.terminals.edit', $t)" />
            {{-- Bind THIS workstation to the terminal (sets the pos_terminal_id
                 cookie current_terminal() reads). Hidden once it's the active one. --}}
            <x-admin.row-action icon="check" :label="__('terminals.row.use_here')"
                x-show="activeTerminalId !== {{ $t->id }}"
                ::disabled="rowsById[{{ $t->id }}]?.is_active === false || selectingId === {{ $t->id }}"
                @click="selectTerminal({{ $t->id }})" />
            <x-admin.row-action icon="x" :label="__('terminals.row.stop_using')"
                x-show="activeTerminalId === {{ $t->id }}"
                @click="clearSelection()" />
            {{-- Kiosk terminals only. Binds THIS device to the terminal (so the
                 kiosk inherits its config via current_terminal()), then launches
                 the customer kiosk. Disabled while the terminal is inactive. --}}
            @if ($t->kioskEnabled())
                <x-admin.row-action icon="pos" :label="__('terminals.row.open_kiosk')"
                    ::disabled="rowsById[{{ $t->id }}]?.is_active === false || selectingId === {{ $t->id }}"
                    @click="openKiosk({{ $t->id }})" />
            @endif
            <div class="row-action-sep"></div>
            <x-admin.row-action icon="trash" variant="danger" :label="__('terminals.actions.delete')"
                @click="confirmDeleteRow({{ $t->id }})" />
        </x-admin.row-actions>
    </div>
</li>
