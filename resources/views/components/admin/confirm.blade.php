{{--
    Global confirm dialog. State lives in `$store.confirm`; the markup
    follows the DesignSystem.html `.modal-card.is-confirm` pattern.
    Esc / scrim click / Cancel all call `$store.confirm.cancel()` —
    locked out while `submitting` is true.
--}}
<div class="scrim overlay-host"
     x-data
     x-show="$store.confirm.open"
     x-cloak
     @click.self="$store.confirm.cancel()"
     @keydown.escape.window="if ($store.confirm.open) $store.confirm.cancel()">
    <div class="modal-card is-confirm"
         :class="{
             'is-warning':  $store.confirm.intent === 'warning',
             'is-positive': $store.confirm.intent === 'positive',
         }"
         role="alertdialog"
         aria-modal="true"
         aria-labelledby="pos-confirm-title"
         aria-describedby="pos-confirm-msg">
        <div class="modal-body">
            <span class="confirm-icon" aria-hidden="true">
                <x-icon name="alert" class="w-[20px] h-[20px]" />
            </span>
            <div>
                <div class="confirm-title" id="pos-confirm-title" x-text="$store.confirm.title"></div>
                <div class="confirm-msg"   id="pos-confirm-msg"   x-text="$store.confirm.message"></div>
            </div>
        </div>
        <div class="modal-foot">
            <button type="button"
                    class="pos-btn pos-btn-sm pos-btn-ghost"
                    :disabled="$store.confirm.submitting"
                    @click="$store.confirm.cancel()"
                    x-text="$store.confirm.cancelLabel"></button>
            <button type="button"
                    class="pos-btn pos-btn-sm"
                    :class="$store.confirm.intent === 'danger' ? 'pos-btn-danger' : 'pos-btn-primary'"
                    :disabled="$store.confirm.submitting"
                    @click="$store.confirm.confirm()">
                <svg x-show="$store.confirm.submitting"
                     x-cloak
                     class="h-4 w-4 animate-spin"
                     viewBox="0 0 24 24"
                     fill="none"
                     aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10"
                            stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor"
                          d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                </svg>
                <span x-text="$store.confirm.confirmLabel"></span>
            </button>
        </div>
    </div>
</div>
