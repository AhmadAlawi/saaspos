{{--
    Toast viewport. Renders a fixed top-end stack of transient
    notifications driven by `$store.toasts.items`. Server-side flash
    is funneled into the same store by the layout's POS_FLASH script.
--}}
<div class="toast-container"
     x-data
     x-show="$store.toasts.items.length > 0"
     x-cloak>
    <template x-for="t in $store.toasts.items" :key="t.id">
        <div class="toast" :class="`toast-${t.type}`" role="status">
            <span class="toast-icon" aria-hidden="true">
                <span x-show="t.type === 'success'"><x-icon name="check" class="w-[18px] h-[18px]" /></span>
                <span x-show="t.type === 'error'"><x-icon name="alert" class="w-[18px] h-[18px]" /></span>
                <span x-show="t.type === 'warning'"><x-icon name="alert" class="w-[18px] h-[18px]" /></span>
                <span x-show="t.type === 'info'"><x-icon name="info" class="w-[18px] h-[18px]" /></span>
            </span>
            <div class="toast-body">
                <div class="toast-title" x-text="t.title" x-show="t.title"></div>
                {{-- `messages` (array) wins over `message` (string) when set.
                     List form is used for validation errors so the user
                     sees every field that needs attention. --}}
                <template x-if="t.messages && t.messages.length">
                    <ul class="toast-msg-list">
                        <template x-for="m in t.messages" :key="m">
                            <li x-text="m"></li>
                        </template>
                    </ul>
                </template>
                <template x-if="!t.messages">
                    <div class="toast-msg" x-text="t.message"></div>
                </template>
            </div>
            <button type="button"
                    class="toast-x"
                    @click="$store.toasts.dismiss(t.id)"
                    :aria-label="@js(__('admin.toast.dismiss'))">
                <x-icon name="x" class="w-3.5 h-3.5" />
            </button>
        </div>
    </template>
</div>
