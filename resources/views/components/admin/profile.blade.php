<div class="notif-wrap" x-data="dropdown" @keydown.escape.window="close">
    {{-- Trigger button --}}
    <button type="button"
            class="profile-btn"
            :class="{ 'is-open': open }"
            @click="toggle"
            :aria-expanded="open"
            aria-haspopup="menu">
        <span class="avatar">
            @if (auth()->user()?->avatar_url)
                <img src="{{ auth()->user()->avatar_url }}" alt="">
            @else
                {{ auth()->user()?->initials ?? 'GU' }}
            @endif
        </span>
        <span class="profile-meta">
            <span class="profile-name">{{ auth()->user()?->name ?? __('admin.shell.guest') }}</span>
            <span class="profile-role">{{ __('admin.shell.role_admin') }}</span>
        </span>
        <span class="profile-chev fg-tertiary"><x-icon name="chevron" class="w-3.5 h-3.5" /></span>
    </button>

    {{-- Dropdown panel --}}
    <div class="profile-panel"
         x-show="open"
         x-cloak
         x-transition.origin.top.right
         @click.outside="close"
         role="menu">

        {{-- Header — larger avatar + name + email --}}
        <div class="profile-panel-head">
            <span class="avatar avatar-lg">
                @if (auth()->user()?->avatar_url)
                    <img src="{{ auth()->user()->avatar_url }}" alt="">
                @else
                    {{ auth()->user()?->initials ?? 'GU' }}
                @endif
            </span>
            <div class="profile-panel-id">
                <div class="profile-panel-name">{{ auth()->user()?->name ?? __('admin.shell.guest') }}</div>
                <div class="profile-panel-email">{{ auth()->user()?->display_email ?? '—' }}</div>
            </div>
        </div>

        {{-- Menu items --}}
        <a href="{{ route('admin.profile.edit') }}" class="dropdown-item" role="menuitem">
            <x-icon name="user" class="w-4 h-4 fg-tertiary" />
            <span class="dropdown-item-label">{{ __('admin.shell.menu.profile') }}</span>
        </a>

        <a href="/admin/settings" class="dropdown-item" role="menuitem">
            <x-icon name="settings" class="w-4 h-4 fg-tertiary" />
            <span class="dropdown-item-label">{{ __('admin.shell.menu.settings') }}</span>
        </a>

        <a href="{{ route('cashier.index') }}" class="dropdown-item" role="menuitem">
            <x-icon name="pos" class="w-4 h-4 fg-tertiary" />
            <span class="dropdown-item-label">{{ __('admin.shell.menu.open_pos') }}</span>
            <span class="kbd">⌘⇧R</span>
        </a>

        <div class="profile-panel-sep" aria-hidden="true"></div>

        {{-- Appearance: 3-way segmented control --}}
        <div class="profile-panel-section">
            <div class="eyebrow profile-panel-section-label">{{ __('admin.shell.menu.appearance') }}</div>
            <div class="seg is-grid-3">
                <button type="button"
                        :class="{ 'is-active': $store.ui.theme === 'light' }"
                        @click="$store.ui.set('light')">
                    <x-icon name="sun" class="w-3.5 h-3.5" />
                    <span>{{ __('admin.shell.menu.light') }}</span>
                </button>
                <button type="button"
                        :class="{ 'is-active': $store.ui.theme === 'dark' }"
                        @click="$store.ui.set('dark')">
                    <x-icon name="moon" class="w-3.5 h-3.5" />
                    <span>{{ __('admin.shell.menu.dark') }}</span>
                </button>
                <button type="button"
                        :class="{ 'is-active': $store.ui.theme === 'auto' }"
                        @click="$store.ui.set('auto')">
                    <x-icon name="theme-auto" class="w-3.5 h-3.5" />
                    <span>{{ __('admin.shell.menu.auto') }}</span>
                </button>
            </div>
        </div>

        <div class="profile-panel-sep" aria-hidden="true"></div>

        {{-- Sign out. If the cashier still has an open shift, warn them
             before logging out (the shift stays open server-side and a
             manager would have to force-close it). --}}
        @php
            $__openShift = auth()->check()
                ? \App\Models\Shift::openForCashier((int) (current_store_id() ?: 0), (int) auth()->id())
                : null;
        @endphp
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="dropdown-item is-danger" role="menuitem"
                @if ($__openShift)
                @click.prevent="
                    const form = $el.closest('form');
                    $store.confirm.show({
                        title: @js(__('shifts.logout_warning.title')),
                        message: @js(__('shifts.logout_warning.message', ['number' => '#'.$__openShift->id])),
                        intent: 'warning',
                        confirmLabel: @js(__('shifts.logout_warning.logout_anyway')),
                        cancelLabel: @js(__('shifts.actions.cancel')),
                        onConfirm: () => form.submit(),
                    })
                "
                @endif>
                <x-icon name="logout" class="w-4 h-4" />
                <span class="dropdown-item-label">{{ __('admin.shell.menu.sign_out') }}</span>
            </button>
        </form>
    </div>
</div>
