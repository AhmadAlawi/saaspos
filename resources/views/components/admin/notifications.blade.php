@php $unread = auth()->user()?->unreadNotifications()->count() ?? 0; @endphp

<div class="notif-wrap"
     x-data="notificationsPanel(@js([
        'unread'    => $unread,
        'emptySub'  => __('admin.shell.notifications_empty_sub'),
        'unreadTpl' => __('notifications.unread_count', ['count' => ':count']),
        'urls' => [
            'index'   => route('admin.notifications.index'),
            'readAll' => route('admin.notifications.read-all'),
            'read'    => route('admin.notifications.read', ['id' => '__id__']),
        ],
     ]))"
     @keydown.escape.window="close">
    <button type="button"
            class="icon-btn"
            :class="{ 'is-open': open }"
            @click="toggle"
            :aria-expanded="open"
            aria-label="{{ __('admin.shell.notifications') }}">
        <x-icon name="bell" class="w-[18px] h-[18px]" />
        <span class="icon-btn-dot" aria-hidden="true" x-show="unread > 0"></span>
    </button>

    <div class="notif-panel"
         x-show="open"
         x-cloak
         x-transition.origin.top.right
         @click.outside="close"
         role="dialog"
         aria-label="{{ __('admin.shell.notifications') }}">

        <div class="notif-head">
            <div>
                <div class="notif-title">{{ __('admin.shell.notifications') }}</div>
                <div class="notif-sub" x-text="subText"></div>
            </div>
            <button type="button" class="pos-btn pos-btn-xs pos-btn-ghost" @click="markAll()" x-show="unread > 0">
                <x-icon name="check-all" class="w-3.5 h-3.5" />
                <span>{{ __('admin.shell.mark_all_read') }}</span>
            </button>
        </div>

        <div class="notif-list">
            {{-- Loading --}}
            <div class="cmd-empty" x-show="busy && !loaded">
                <div class="cmd-empty-sub">{{ __('notifications.loading') }}</div>
            </div>

            {{-- Empty --}}
            <div class="cmd-empty" x-show="loaded && items.length === 0" x-cloak>
                <span class="cmd-empty-icon"><x-icon name="bell" class="w-5 h-5" /></span>
                <div class="cmd-empty-title">{{ __('admin.shell.no_notifications') }}</div>
                <div class="cmd-empty-sub">{{ __('admin.shell.no_notifications_sub') }}</div>
            </div>

            {{-- Items --}}
            <template x-for="item in items" :key="item.id">
                <a href="#" class="notif-item" :class="{ 'is-read': item.read }" @click.prevent="openItem(item)">
                    <span class="notif-icon">
                        <span x-show="item.icon === 'download'"><x-icon name="download" class="w-4 h-4" /></span>
                        <span x-show="item.icon === 'database'"><x-icon name="database" class="w-4 h-4" /></span>
                        <span x-show="item.icon === 'box'"><x-icon name="box" class="w-4 h-4" /></span>
                        <span x-show="item.icon === 'refund'"><x-icon name="refund" class="w-4 h-4" /></span>
                        <span x-show="['download','database','box','refund'].indexOf(item.icon) === -1"><x-icon name="bell" class="w-4 h-4" /></span>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="notif-line"><strong x-text="item.title"></strong> <span x-text="item.message"></span></div>
                        <div class="notif-time" x-text="item.time"></div>
                    </div>
                    <span class="notif-dot" aria-hidden="true"></span>
                </a>
            </template>
        </div>
    </div>
</div>
