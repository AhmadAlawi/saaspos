/**
 * Live status for the customer-facing QR pay page (`/pay/pos/{uuid}`).
 *
 * The page is otherwise static — it renders whatever state the session
 * was in when the customer loaded it. This poller keeps it honest: if
 * the cashier cancels the charge, the session times out, or it gets
 * paid on another device, the page reloads so the server re-renders the
 * "Session expired" / "Already paid" state. No duplicate UI logic lives
 * here — the server view is the single source of truth.
 *
 * The poll root (`[data-pay-status-url]`) is only emitted by the Blade
 * view while the session is still live, so an already-terminal page
 * never polls (and can't get stuck in a reload loop).
 *
 * Standalone public bundle — no admin http.js/axios here, so a plain
 * fetch is the right tool.
 */
const root = document.querySelector('[data-pay-status-url]');

if (root) {
    const url = root.getAttribute('data-pay-status-url');
    let stopped = false;

    const poll = async () => {
        if (stopped) return;
        try {
            const res = await fetch(url, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });
            if (!res.ok) return;
            const data = await res.json();
            if (data && data.terminal) {
                stopped = true;
                clearInterval(timer);
                window.location.reload();
            }
        } catch (_) {
            /* transient (e.g. offline) — the next tick retries */
        }
    };

    const timer = setInterval(poll, 4000);
    // Catch a status change the moment the tab regains focus, without
    // waiting out the 4s interval.
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) poll();
    });
}
