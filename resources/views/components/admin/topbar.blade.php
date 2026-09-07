@props(['crumbs' => []])

<header class="topbar">
    <div class="topbar-left">
        <button type="button"
                class="topbar-menu"
                @click="$store.sidebar.openMobile()"
                aria-label="{{ __('admin.shell.open_drawer') }}">
            <x-icon name="menu" class="w-[18px] h-[18px]" />
        </button>

        <nav class="crumbs" aria-label="{{ __('admin.shell.breadcrumb') }}">
            @foreach ($crumbs as $crumb)
                @if (! empty($crumb['href']) && ! $loop->last)
                    <a href="{{ $crumb['href'] }}" class="crumb">{{ $crumb['label'] }}</a>
                @else
                    <span class="crumb @if($loop->last) is-current @endif">{{ $crumb['label'] }}</span>
                @endif
                @unless ($loop->last)
                    <span class="crumb-sep">/</span>
                @endunless
            @endforeach
        </nav>
    </div>

    <button type="button"
            class="topbar-search"
            @click="$dispatch('pos:cmd-open')"
            aria-label="{{ __('admin.shell.search_placeholder') }}">
        <span class="hs-icon"><x-icon name="search" class="w-4 h-4" /></span>
        <span class="hs-placeholder">{{ __('admin.shell.search_placeholder') }}</span>
        <span class="hs-kbd">
            <span class="kbd">⌘</span>
            <span class="kbd">K</span>
        </span>
    </button>

    <div class="topbar-right">
        <x-admin.store-switcher />

        <span class="tb-secondary"><x-admin.language-switcher /></span>

        <x-admin.print-queue />

        @if (auth()->user()?->hasPermission('reports.view_financial'))
            <span class="tb-secondary"><x-admin.day-summary /></span>
        @endif

        <x-admin.notifications />

        {{-- PWA install / update. The Install button is always shown (unless
             already installed); it fires the native prompt when the browser
             supports it, else opens a how-to dialog. The Update button only
             shows when a new service-worker version is waiting. --}}
        <span class="contents"
              x-data="pwaInstall({
                  help: {
                      title:   @js(__('admin.shell.install_help.title')),
                      ios:     @js(__('admin.shell.install_help.ios')),
                      android: @js(__('admin.shell.install_help.android')),
                      desktop: @js(__('admin.shell.install_help.desktop')),
                      firefox: @js(__('admin.shell.install_help.firefox')),
                  }
              })">
            <button type="button"
                    class="pos-launch"
                    x-show="!installed"
                    @click="install()"
                    data-tip="{{ __('admin.shell.install_app') }}"
                    aria-label="{{ __('admin.shell.install_app') }}">
                <x-icon name="download" class="w-[18px] h-[18px]" />
            </button>
            <button type="button"
                    class="pos-launch pos-launch--update"
                    x-show="updateAvailable"
                    x-cloak
                    @click="update()"
                    data-tip="{{ __('admin.shell.update_app') }}"
                    aria-label="{{ __('admin.shell.update_app') }}">
                <x-icon name="refresh" class="w-[18px] h-[18px]" />
            </button>

            {{-- How-to-install dialog — teleported to body so the topbar's
                 stacking/overflow can't clip it. Shown only as a fallback when
                 the browser has no programmatic install prompt (iOS/Firefox). --}}
            <template x-teleport="body">
                <div class="scrim overlay-host"
                     x-show="showHelp"
                     x-cloak
                     @click.self="showHelp = false"
                     @keydown.escape.window="showHelp = false">
                    <div class="modal-card" style="max-width: 460px;" role="dialog" aria-modal="true">
                        <div class="modal-head">
                            <div>
                                <div class="modal-title" x-text="helpTitle"></div>
                                <div class="modal-sub">{{ __('admin.shell.install_help.sub') }}</div>
                            </div>
                            <button type="button" class="modal-x" @click="showHelp = false" aria-label="{{ __('admin.shell.install_help.close') }}">
                                <x-icon name="x" class="w-4 h-4" />
                            </button>
                        </div>
                        <div class="modal-body">
                            <p style="font-size:13px; line-height:1.6; color:var(--text-secondary); margin:0;" x-text="helpBody"></p>
                        </div>
                        <div class="modal-foot">
                            <button type="button" class="pos-btn pos-btn-sm pos-btn-primary" @click="showHelp = false">
                                {{ __('admin.shell.install_help.close') }}
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </span>

        <a href="{{ route('cashier.index') }}" class="pos-launch" data-tip="{{ __('admin.shell.open_pos') }}" aria-label="{{ __('admin.shell.open_pos') }}">
            <x-icon name="pos" class="w-[18px] h-[18px]" />
        </a>

        <span class="topbar-divider" aria-hidden="true"></span>

        <x-admin.profile />
    </div>
</header>

{{-- Mobile breadcrumb bar — on small screens the in-topbar crumbs are
     hidden (they overflow the bar); the full trail moves to its own thin
     row beneath the topbar, scrollable if very deep. Only rendered when
     there's an actual trail (a single crumb adds no information). --}}
@if (count($crumbs) > 1)
    <nav class="topbar-crumbs-bar" aria-label="{{ __('admin.shell.breadcrumb') }}">
        @foreach ($crumbs as $crumb)
            @if (! empty($crumb['href']) && ! $loop->last)
                <a href="{{ $crumb['href'] }}" class="crumb">{{ $crumb['label'] }}</a>
            @else
                <span class="crumb @if($loop->last) is-current @endif">{{ $crumb['label'] }}</span>
            @endif
            @unless ($loop->last)
                <span class="crumb-sep">/</span>
            @endunless
        @endforeach
    </nav>
@endif
