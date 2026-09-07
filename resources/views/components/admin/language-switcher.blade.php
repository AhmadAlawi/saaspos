<div class="store-switcher" x-data="dropdown" @keydown.escape.window="close">
    <button type="button"
            class="store-switcher-btn"
            :class="{ 'is-open': open }"
            @click="toggle"
            :aria-expanded="open"
            aria-haspopup="menu">
        <span class="store-switcher-icon"><x-icon name="globe" class="w-4 h-4" /></span>
        <span class="store-switcher-meta">
            <span class="store-switcher-label">{{ __('languages.switcher.label') }}</span>
            <span class="store-switcher-name">{{ $current?->native_name ?? strtoupper(app()->getLocale()) }}</span>
        </span>
        <span class="store-switcher-chev fg-tertiary"><x-icon name="chevron" class="w-3.5 h-3.5" /></span>
    </button>

    <div class="store-switcher-panel"
         x-show="open"
         x-cloak
         x-transition.origin.top
         @click.outside="close"
         role="menu">

        <div class="store-switcher-panel-head eyebrow">{{ __('languages.switcher.heading') }}</div>

        <div class="store-switcher-list">
            @foreach ($languages as $language)
                <form method="POST" action="{{ route('admin.locale.switch', $language->code) }}">
                    @csrf
                    <button type="submit"
                            class="dropdown-item store-switcher-item @if($current && $language->id === $current->id) is-active @endif"
                            role="menuitemradio"
                            @if($current && $language->id === $current->id) aria-checked="true" disabled @else aria-checked="false" @endif>
                        <span class="store-switcher-item-id">
                            <span class="store-switcher-item-name">{{ $language->native_name }}</span>
                            <span class="store-switcher-item-code">{{ $language->name }} · {{ $language->code }}</span>
                        </span>
                        @if($current && $language->id === $current->id)
                            <x-icon name="check" class="w-4 h-4 fg-accent" />
                        @endif
                    </button>
                </form>
            @endforeach
        </div>

        @can('settings.view')
            <div class="store-switcher-panel-sep" aria-hidden="true"></div>
            <a href="{{ route('admin.languages.index') }}" class="dropdown-item" role="menuitem">
                <x-icon name="settings" class="w-4 h-4 fg-tertiary" />
                <span class="dropdown-item-label">{{ __('languages.switcher.manage') }}</span>
            </a>
        @endcan
    </div>
</div>
