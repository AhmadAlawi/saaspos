/**
 * Confirm-dialog store — drives the global `.modal-card.is-confirm`
 * overlay (rendered once in admin-layout via <x-admin.confirm />).
 *
 * Callers fire-and-forget:
 *
 *   this.$store.confirm.show({
 *       title:        'Move "Bakery" under "Snacks"?',
 *       message:      'The category will be reparented…',
 *       intent:       'warning',          // 'danger' | 'warning' | 'positive'
 *       confirmLabel: 'Move category',
 *       cancelLabel:  'Cancel',
 *       onConfirm:    () => …,            // may return a Promise
 *       onCancel:     () => …,            // optional
 *   });
 *
 * When `onConfirm` returns a Promise the dialog stays open with a
 * spinner in the confirm button until the promise settles — successful
 * promises close the dialog, rejections close it too (callers surface
 * their own error toasts). Cancel is locked out while submitting.
 *
 * Escape and clicking the scrim both invoke `cancel()`.
 */
export function registerConfirmStore(Alpine) {
    Alpine.store('confirm', {
        open:         false,
        submitting:   false,
        title:        '',
        message:      '',
        intent:       'danger',           // 'danger' | 'warning' | 'positive'
        confirmLabel: 'Confirm',
        cancelLabel:  'Cancel',
        _onConfirm:   null,
        _onCancel:    null,

        show(opts = {}) {
            this.title        = opts.title ?? '';
            this.message      = opts.message ?? '';
            this.intent       = opts.intent ?? 'danger';
            this.confirmLabel = opts.confirmLabel ?? 'Confirm';
            this.cancelLabel  = opts.cancelLabel ?? 'Cancel';
            this._onConfirm   = typeof opts.onConfirm === 'function' ? opts.onConfirm : null;
            this._onCancel    = typeof opts.onCancel  === 'function' ? opts.onCancel  : null;
            this.submitting   = false;
            this.open         = true;
        },

        async confirm() {
            if (this.submitting) return;     // ignore double-clicks
            const cb = this._onConfirm;
            if (!cb) {
                this._close();
                return;
            }
            this.submitting = true;
            try {
                await cb();                  // Promise-aware — works for sync callbacks too
                this._close();
            } catch (e) {
                // Caller is expected to surface its own toast on failure.
                this._close();
            }
        },

        cancel() {
            if (this.submitting) return;     // locked out while the action is in flight
            const cb = this._onCancel;
            this._close();
            if (cb) cb();
        },

        _close() {
            this.open       = false;
            this.submitting = false;
            this._onConfirm = null;
            this._onCancel  = null;
        },
    });
}
