{{--
    Topbar failed-print queue indicator. Only visible while there are
    parked prints (an empty queue shows nothing). Driven by printQueuePanel
    — see resources/js/admin/print-queue-panel.js.
--}}
<div class="notif-wrap"
     x-data="printQueuePanel()"
     x-show="count > 0"
     x-cloak
     @keydown.escape.window="close">
    <button type="button"
            class="icon-btn"
            :class="{ 'is-open': open }"
            @click="toggle"
            :aria-expanded="open"
            aria-label="{{ __('print_queue.title') }}">
        <x-icon name="printer" class="w-[18px] h-[18px]" />
        <span class="icon-btn-badge" aria-hidden="true" x-text="count" x-show="count > 0"></span>
    </button>

    <div class="notif-panel"
         x-show="open"
         x-cloak
         x-transition.origin.top.right
         @click.outside="close"
         role="dialog"
         aria-label="{{ __('print_queue.title') }}">

        <div class="notif-head">
            <div>
                <div class="notif-title">{{ __('print_queue.title') }}</div>
                <div class="notif-sub" x-text="`${count} ${count === 1 ? '{{ __('print_queue.failed_one') }}' : '{{ __('print_queue.failed_many') }}'}`"></div>
            </div>
        </div>

        <div class="notif-list">
            <template x-for="item in items" :key="item.id">
                <div class="pq-item">
                    <span class="pq-icon" :class="{ 'is-stalled': isStalled(item) }">
                        <x-icon name="printer" class="w-4 h-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="pq-line">
                            <strong x-text="item.label || item.reference_type"></strong>
                            <span class="pq-attempts" x-show="item.attempts > 0"
                                  x-text="`· {{ __('print_queue.attempts') }} ${item.attempts}`"></span>
                        </div>
                        <div class="pq-err" x-show="item.last_error" x-text="item.last_error"></div>
                    </div>
                    <div class="pq-actions">
                        <button type="button" class="pos-btn pos-btn-xs pos-btn-ghost"
                                :disabled="retrying.includes(item.id)"
                                @click="retry(item.id)">
                            {{ __('print_queue.retry') }}
                        </button>
                        <button type="button" class="pos-btn pos-btn-xs pos-btn-ghost"
                                @click="discard(item.id)">
                            {{ __('print_queue.discard') }}
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
