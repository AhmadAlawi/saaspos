@props([
    'label' => null,   // accessible label for the trigger
])

{{--
    Per-row action menu — the "⋮" kebab shown in the last column of every
    admin list table. Children are <x-admin.row-action> items.

    Pairs with the `rowActionsMenu` Alpine factory (resources/js/admin/row-actions.js)
    and `.row-actions*` styles (resources/css/components/row-actions.css).

    The whole control stops click propagation so it never triggers the
    row's click-to-edit handler. The menu is `position: fixed` (positioned
    by JS) so it escapes the `.dt-scroll` overflow clip.

    Usage:
        <x-admin.row-actions>
            <x-admin.row-action :href="route('admin.foo.edit', $foo)" icon="pencil" :label="__('common.edit')" />
            <x-admin.row-action button icon="trash" variant="danger" :label="__('common.delete')"
                @click="$store.confirm.show({ … })" />
        </x-admin.row-actions>
--}}
<div class="row-actions" x-data="rowActionsMenu" @keydown.escape.window="close()">
    <button type="button"
            class="row-actions-btn"
            x-ref="trigger"
            @click.stop="toggle()"
            :class="{ 'is-open': open }"
            :aria-expanded="open ? 'true' : 'false'"
            aria-haspopup="menu"
            aria-label="{{ $label ?? __('table.actions') }}">
        <x-icon name="dots-vertical" class="w-[18px] h-[18px]" />
    </button>

    <div class="row-actions-menu"
         x-ref="menu"
         x-show="open"
         x-cloak
         role="menu"
         @click.stop="close()"
         @click.outside="close()"
         x-transition.opacity.duration.100ms>
        {{ $slot }}
    </div>
</div>
