@props([
    'nav'              => [],
    'appLogo'          => null,
    'appLogoDark'      => null,
    'appLogoHalf'      => null,
    'appLogoHalfDark'  => null,
    'appName'          => '',
])

@php
    $hasFullLogo = (bool) ($appLogo || $appLogoDark);
    $hasHalfLogo = (bool) ($appLogoHalf || $appLogoHalfDark);
    $displayName = $appName ?: config('app.name');
    $initial     = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($displayName, 0, 1));
@endphp

<aside class="admin-sidebar" x-data="navTooltip">
    {{-- ── Brand row ─────────────────────────────────────────────── --}}
    <div class="brand">
        <a href="/admin" class="brand-link" data-tip="{{ $displayName }}">

            {{-- ── Expanded content — hidden in collapsed mode via x-show.
                 x-show sets inline display:none which beats any CSS rule,
                 including dark:block on child images. --}}
            <span class="brand-expanded" x-show="!$store.sidebar.collapsed" x-cloak>
                @if ($hasFullLogo)
                    {{-- Full logo (light). hidden dark:block pairs swap in dark mode. --}}
                    @if ($appLogo)
                        <img src="{{ $appLogo }}"
                             class="brand-logo{{ $appLogoDark ? ' dark:hidden' : '' }}"
                             alt="">
                    @endif
                    @if ($appLogoDark)
                        <img src="{{ $appLogoDark }}"
                             class="brand-logo hidden dark:block"
                             alt="">
                    @endif
                @else
                    <span class="mark">{{ $initial }}</span>
                @endif
                {{-- App name only shown when no full logo is uploaded --}}
                @if (! $hasFullLogo)
                <span class="brand-text">
                    <span class="name">{{ $displayName }}</span>
                    <span class="sub">{{ __('admin.shell.brand_sub') }}</span>
                </span>
                @endif
            </span>

            {{-- ── Collapsed content — hidden in expanded mode via x-show.
                 Shows the half/icon logo, or falls back to the letter mark. --}}
            <span class="brand-collapsed" x-show="$store.sidebar.collapsed" x-cloak>
                @if ($hasHalfLogo)
                    @if ($appLogoHalf)
                        <img src="{{ $appLogoHalf }}"
                             class="{{ $appLogoHalfDark ? 'dark:hidden' : '' }}"
                             alt="{{ $displayName }}">
                    @endif
                    @if ($appLogoHalfDark)
                        <img src="{{ $appLogoHalfDark }}"
                             class="hidden dark:block"
                             alt="{{ $displayName }}">
                    @endif
                @else
                    <span class="mark">{{ $initial }}</span>
                @endif
            </span>

        </a>

        <button type="button"
                class="sidebar-toggle"
                @click="$store.sidebar.toggle()"
                aria-label="{{ __('admin.shell.toggle_sidebar') }}">
            <span class="ico-collapse"><x-icon name="panel-left-close" class="w-4 h-4" /></span>
            <span class="ico-expand"><x-icon name="panel-left-open" class="w-4 h-4" /></span>
        </button>

        <button type="button"
                class="sidebar-close-mobile"
                @click="$store.sidebar.closeMobile()"
                aria-label="{{ __('admin.shell.close_drawer') }}">
            <x-icon name="x" class="w-4 h-4" />
        </button>
    </div>

    {{-- ── Nav ───────────────────────────────────────────────────── --}}
    <nav class="nav-scroll"
         x-init="$nextTick(() => $el.querySelector('.nav-item.is-active')?.scrollIntoView({ block: 'nearest' }))"
         aria-label="{{ __('admin.shell.primary_nav') }}">
        @foreach ($nav as $section)
            <div class="nav-group">
                <div class="nav-section"><span>{{ $section['label'] }}</span></div>
                <div class="nav-items">
                    @foreach ($section['items'] as $item)
                        {{-- `hidden` rows exist only so the command palette can
                             still find them (the individual report screens now
                             live on the All reports hub page). --}}
                        @continue(! empty($item['hidden']))

                        @if(isset($item['items']))
                            {{-- Collapsible sub-group. Sections stay flat; only
                                 these read-only / configure-once tails nest.
                                 Opens itself when it holds the active page. --}}
                            <div class="nav-subgroup"
                                 x-data="navGroup({ id: {{ Js::from($item['id'] ?? '') }}, active: {{ $item['is_active'] ? 'true' : 'false' }} })">
                                <button type="button"
                                        class="nav-item nav-item--group"
                                        :class="{ 'is-open': open }"
                                        :aria-expanded="open.toString()"
                                        @click="toggle()"
                                        data-tip="{{ $item['label'] }}"
                                        @mouseenter="showTip($event)"
                                        @mouseleave="hideTip()"
                                        @focus="showTip($event)"
                                        @blur="hideTip()">
                                    <span class="nav-ico"><x-icon :name="$item['icon']" class="w-[18px] h-[18px]" /></span>
                                    <span class="nav-label">{{ $item['label'] }}</span>
                                    <span class="nav-grp-chev"><x-icon name="chevron" class="w-3.5 h-3.5" /></span>
                                </button>

                                <div class="nav-subgroup-body" x-ref="body">
                                    <div class="nav-items">
                                        @foreach ($item['items'] as $child)
                                            <a href="{{ $child['href'] }}"
                                               class="nav-item nav-item--child @if($child['is_active'] ?? false) is-active @endif"
                                               data-tip="{{ $child['label'] }}"
                                               @mouseenter="showTip($event)"
                                               @mouseleave="hideTip()"
                                               @focus="showTip($event)"
                                               @blur="hideTip()">
                                                <span class="nav-ico"><x-icon :name="$child['icon'] ?? $item['icon']" class="w-[18px] h-[18px]" /></span>
                                                <span class="nav-label">{{ $child['label'] }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @else
                            <a href="{{ $item['href'] }}"
                               class="nav-item @if($item['is_active']) is-active @endif"
                               data-tip="{{ $item['label'] }}"
                               @mouseenter="showTip($event)"
                               @mouseleave="hideTip()"
                               @focus="showTip($event)"
                               @blur="hideTip()">
                                <span class="nav-ico"><x-icon :name="$item['icon']" class="w-[18px] h-[18px]" /></span>
                                <span class="nav-label">{{ $item['label'] }}</span>
                                @isset($item['count'])
                                    <span class="nav-count tnum">{{ $item['count'] }}</span>
                                @endisset
                                @if(! empty($item['alert']))
                                    <span class="nav-alert" aria-label="{{ __('admin.shell.has_alert') }}"></span>
                                @endif
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>

    {{-- ── Collapsed-mode tooltip portal ────────────────────────── --}}
    <div class="nav-tooltip"
         :class="{ 'is-visible': tipVisible }"
         :style="`--tip-y: ${tipY}px`"
         x-text="tipText"
         aria-hidden="true"></div>

    {{-- ── Footer ────────────────────────────────────────────────── --}}
    <div class="sidebar-footer">
        <span class="dot dot-positive" aria-hidden="true"></span>
        <div class="footer-text">
            <div class="text-[11.5px] font-medium fg-primary">{{ __('admin.shell.status_online') }}</div>
            <div class="text-[10.5px] fg-tertiary mono">v{{ config('pos.version', '1.0.0') }}</div>
        </div>
        <button type="button"
                class="sidebar-footer-toggle"
                @click="$store.sidebar.toggle()"
                aria-label="{{ __('admin.shell.toggle_sidebar') }}">
            <x-icon name="panel-left-close" class="w-4 h-4" />
        </button>
    </div>
</aside>
