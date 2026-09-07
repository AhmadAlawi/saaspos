/**
 * Fullscreen toggle (used on the cashier toolbar). Enters/exits the
 * browser Fullscreen API and keeps `isFull` in sync with the actual
 * state — including when the user leaves fullscreen via Esc or F11, which
 * fire `fullscreenchange` rather than going through our button.
 */
export function fullscreenToggle() {
    return {
        isFull: false,
        _onChange: null,

        init() {
            this.isFull = !!document.fullscreenElement;
            this._onChange = () => { this.isFull = !!document.fullscreenElement; };
            document.addEventListener('fullscreenchange', this._onChange);
        },

        destroy() {
            if (this._onChange) {
                document.removeEventListener('fullscreenchange', this._onChange);
            }
        },

        async toggle() {
            try {
                if (!document.fullscreenElement) {
                    const el = document.documentElement;
                    await (el.requestFullscreen?.()
                        ?? el.webkitRequestFullscreen?.()
                        ?? Promise.reject(new Error('Fullscreen not supported')));
                } else {
                    await (document.exitFullscreen?.() ?? document.webkitExitFullscreen?.());
                }
            } catch (_) {
                // User denied, or the browser blocked it (e.g. not a user
                // gesture / unsupported) — nothing to do.
            }
        },
    };
}
