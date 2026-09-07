<div class="store-switcher" x-data="dropdown" @keydown.escape.window="close">
    <button type="button"
            class="store-switcher-btn"
            :class="{ 'is-open': open }"
            @click="toggle"
            :aria-expanded="open"
            aria-haspopup="menu">
        <span class="store-switcher-icon"><x-icon name="store" class="w-4 h-4" /></span>
        <span class="store-switcher-meta">
            <span class="store-switcher-label">{{ __('stores.switcher.label') }}</span>
            <span class="store-switcher-name">{{ $active?->name ?? __('stores.switcher.none') }}</span>
        </span>
        <span class="store-switcher-chev fg-tertiary"><x-icon name="chevron" class="w-3.5 h-3.5" /></span>
    </button>

    <div class="store-switcher-panel"
         x-show="open"
         x-cloak
         x-transition.origin.top
         @click.outside="close"
         role="menu">

        <div class="store-switcher-panel-head eyebrow">{{ __('stores.switcher.heading') }}</div>

        <div class="store-switcher-list">
            @foreach ($stores as $store)
                <form method="POST" action="{{ route('admin.stores.switch', $store) }}">
                    @csrf
                    <button type="submit"
                            class="dropdown-item store-switcher-item @if($active && $store->id === $active->id) is-active @endif"
                            role="menuitemradio"
                            @if($active && $store->id === $active->id) aria-checked="true" disabled @else aria-checked="false" @endif>
                        <span class="store-switcher-item-id">
                            <span class="store-switcher-item-name">{{ $store->name }}</span>
                            <span class="store-switcher-item-code">{{ $store->code }}@if($store->city) · {{ $store->city }}@endif</span>
                        </span>
                        @if($active && $store->id === $active->id)
                            <x-icon name="check" class="w-4 h-4 fg-accent" />
                        @endif
                    </button>
                </form>
            @endforeach
        </div>

        @if ($canManage)
            <div class="store-switcher-panel-sep" aria-hidden="true"></div>
            <a href="{{ route('admin.stores.index') }}" class="dropdown-item" role="menuitem">
                <x-icon name="settings" class="w-4 h-4 fg-tertiary" />
                <span class="dropdown-item-label">{{ __('stores.switcher.manage') }}</span>
            </a>
        @endif
    </div>
</div>
