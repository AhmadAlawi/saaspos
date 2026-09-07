/**
 * Toast store — small transient notifications shown top-end of the
 * viewport. Both server-side flash (success / error / warning / info)
 * and client-side code can push toasts.
 *
 *   Alpine.store('toasts').push({ type, title?, message, duration? });
 *
 * `duration: 0` keeps the toast until the user dismisses it.
 *
 * Server-side flash flows in via a `window.POS_FLASH` array set by
 * the admin layout. We drain it here on register so toasts appear on
 * first paint without an extra round-trip.
 */
export function registerToastStore(Alpine) {
    Alpine.store('toasts', {
        items: [],
        _id: 0,

        push({ type = 'info', title = '', message = '', messages = null, duration = 4000 } = {}) {
            // Accept either:
            //   - `message`     → single line (existing callers)
            //   - `messages`    → array of lines (rendered as a bulleted list)
            // Treat as "nothing to show" only when both are empty.
            const hasList = Array.isArray(messages) && messages.length > 0;
            if (!message && !title && !hasList) return null;

            const id = ++this._id;
            this.items.push({ id, type, title, message, messages: hasList ? messages : null });

            // Stay around longer when there are many lines to read.
            const auto = hasList && messages.length > 1
                ? Math.max(duration, 2000 + messages.length * 1500)
                : duration;
            if (auto > 0) {
                setTimeout(() => this.dismiss(id), auto);
            }
            return id;
        },

        dismiss(id) {
            this.items = this.items.filter((t) => t.id !== id);
        },

        clear() {
            this.items = [];
        },
    });

    // Drain anything the server queued before this script ran.
    if (Array.isArray(window.POS_FLASH)) {
        const store = Alpine.store('toasts');
        window.POS_FLASH.forEach((t) => store.push(t));
        window.POS_FLASH = null;
    }
}
