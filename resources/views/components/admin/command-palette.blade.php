<div x-data="commandPalette"
     @pos:cmd-open.window="show()"
     @keydown.escape.window="hide()"
     @keydown.window.prevent.cmd.k="show()"
     @keydown.window.prevent.ctrl.k="show()">

    <div class="cmd-palette"
         x-show="open"
         x-cloak
         x-transition.opacity
         role="dialog"
         aria-label="Command palette"
         aria-modal="true">

        <div class="cmd-scrim" @click="hide()"></div>

        <div class="cmd-panel"
             x-transition:enter="cmd-enter"
             x-transition:enter-start="cmd-enter-start"
             x-transition:enter-end="cmd-enter-end"
             @keydown.arrow-down.prevent="move(1)"
             @keydown.arrow-up.prevent="move(-1)"
             @keydown.enter.prevent="activate()">

            <div class="cmd-search">
                <span class="cmd-search-icon"><x-icon name="search" class="w-5 h-5" /></span>
                <input x-ref="input"
                       x-model="query"
                       @input="activeIndex = 0"
                       class="cmd-input"
                       type="text"
                       placeholder="{{ __('admin.cmd.placeholder') }}"
                       autocomplete="off">
                <button type="button" class="cmd-close" @click="hide()" aria-label="{{ __('admin.cmd.close') }}">
                    <x-icon name="x" class="w-4 h-4 fg-tertiary" />
                </button>
            </div>

            <div class="cmd-scopes">
                <template x-for="s in scopes" :key="s.id">
                    <button type="button"
                            class="cmd-scope"
                            :class="{ 'is-active': scope === s.id }"
                            @click="setScope(s.id)">
                        <span x-text="s.label"></span>
                        <span class="cmd-scope-count" x-text="countFor(s.id)"></span>
                    </button>
                </template>
            </div>

            <div class="cmd-results">
                <template x-if="filtered.length === 0">
                    <div class="cmd-empty">
                        <span class="cmd-empty-icon"><x-icon name="search" class="w-6 h-6" /></span>
                        <div class="cmd-empty-title">{{ __('admin.cmd.empty_title') }}</div>
                        <div class="cmd-empty-sub">{{ __('admin.cmd.empty_sub') }}</div>
                    </div>
                </template>

                <template x-for="(rows, group) in grouped" :key="group">
                    <div class="cmd-group">
                        <div class="cmd-group-label" x-text="group"></div>
                        <template x-for="(row, idx) in rows" :key="row.href + idx">
                            <a :href="row.href"
                               class="cmd-result"
                               :class="{ 'is-active': filtered.indexOf(row) === activeIndex }"
                               @mouseenter="activeIndex = filtered.indexOf(row)">
                                <span class="cmd-result-icon">
                                    <svg viewBox="0 0 24 24" fill="none"
                                         stroke="currentColor" stroke-width="1.6"
                                         stroke-linecap="round" stroke-linejoin="round"
                                         class="w-[18px] h-[18px]" aria-hidden="true"
                                         x-html="row.svg || ''"></svg>
                                </span>
                                <div class="cmd-result-body">
                                    <div class="cmd-result-title" x-text="row.title"></div>
                                    <div class="cmd-result-sub" x-text="row.sub" x-show="row.sub"></div>
                                </div>
                                <span class="cmd-result-meta" x-text="row.scope"></span>
                                <span class="cmd-result-go"><x-icon name="arrow-right" class="w-4 h-4" /></span>
                            </a>
                        </template>
                    </div>
                </template>
            </div>

            <div class="cmd-footer">
                <span class="cmd-hint">
                    <span class="kbd">↵</span>
                    <span>{{ __('admin.cmd.hint_go') }}</span>
                </span>
                <span class="cmd-hint">
                    <span class="kbd">↑</span><span class="kbd">↓</span>
                    <span>{{ __('admin.cmd.hint_nav') }}</span>
                </span>
                <span class="cmd-hint">
                    <span class="kbd">Esc</span>
                    <span>{{ __('admin.cmd.hint_close') }}</span>
                </span>
                <span class="cmd-footer-brand">{{ config('app.name') }}</span>
            </div>
        </div>
    </div>
</div>
