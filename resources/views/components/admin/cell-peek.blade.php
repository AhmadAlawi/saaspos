@props([
    'label'   => null,   // trigger text — usually a count
    'title'   => null,   // optional heading rendered above the slot
    'empty'   => null,   // text shown when the slot has nothing to show
    'disabled' => false, // render plain text instead of a trigger (count === 0)
    'srLabel' => null,   // accessible name for the trigger
])

{{--
    Inline "peek" popover for a table cell — lets a list row reveal detail
    (line items, allocations, …) without navigating to the detail page.

    Reuses the `rowActionsMenu` Alpine factory: the panel is `position: fixed`
    and placed by JS, so it escapes both the `.dt-scroll` horizontal clip and
    the `.page` vertical scroll clip. The trigger stops click propagation so
    it never fires the row's click-to-open handler.

    Usage:
        <x-admin.cell-peek :label="$adj->items_count" :disabled="$adj->items_count === 0">
            <x-slot:title>Items</x-slot:title>
            @foreach ($adj->items as $item) … @endforeach
        </x-admin.cell-peek>
--}}
@if ($disabled)
    <span class="tnum fg-tertiary">{{ $label }}</span>
@else
    <div class="cell-peek" x-data="rowActionsMenu" @keydown.escape.window="close()">
        <button type="button"
                class="cell-peek-btn"
                x-ref="trigger"
                @click.stop="toggle()"
                :class="{ 'is-open': open }"
                :aria-expanded="open ? 'true' : 'false'"
                aria-haspopup="dialog"
                aria-label="{{ $srLabel ?? __('table.peek_label') }}">
            <span class="tnum">{{ $label }}</span>
            <x-icon name="chevron" class="w-3.5 h-3.5 cell-peek-caret" />
        </button>

        <div class="cell-peek-panel"
             x-ref="menu"
             x-show="open"
             x-cloak
             role="dialog"
             @click.stop
             @click.outside="close()"
             x-transition.opacity.duration.100ms>
            @if ($title)
                <div class="cell-peek-title">{{ $title }}</div>
            @endif
            <div class="cell-peek-body">
                {{ $slot }}
            </div>
        </div>
    </div>
@endif
