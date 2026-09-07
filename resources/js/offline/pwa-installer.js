/**
 * PWA + Service Worker installer — Slice 3.
 *
 * Three responsibilities:
 *   1. Register the service worker at /service-worker.js.
 *   2. Capture the `beforeinstallprompt` event and expose it so the
 *      cashier UI can show an "Install" button.
 *   3. Detect new SW versions (waiting state) and expose an
 *      update-available signal so the UI can prompt the cashier to
 *      reload.
 *
 * HTTPS-only: browsers refuse to register a SW over plain HTTP
 * (except on localhost). On HTTP we silently no-op.
 */

const _state = {
    registration:    null,
    deferredPrompt:  null,
    updateAvailable: false,
    installable:     false,
};

const _listeners = new Set();

function emit() {
    const snap = { ...readState() };
    for (const fn of _listeners) {
        try { fn(snap); } catch (_) { /* never let a listener kill the loop */ }
    }
}

export function readState() {
    return {
        installable:     _state.installable,
        updateAvailable: _state.updateAvailable,
    };
}

/** Subscribe to install/update state. Returns an unsubscribe fn. */
export function subscribePwa(fn) {
    _listeners.add(fn);
    fn({ ...readState() });
    return () => _listeners.delete(fn);
}

/**
 * Boot the SW + install prompt machinery. Idempotent.
 *
 * @returns {Promise<void>}
 */
export async function startPwa() {
    if (!('serviceWorker' in navigator)) return;
    if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
        return;
    }

    // Capture the install prompt BEFORE the SW kicks in — Chrome
    // fires it independently as soon as PWA criteria are met.
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        _state.deferredPrompt = e;
        _state.installable    = true;
        emit();
    });
    // After the user installs, hide the button.
    window.addEventListener('appinstalled', () => {
        _state.deferredPrompt = null;
        _state.installable    = false;
        emit();
    });

    try {
        // `updateViaCache: 'none'` is the critical option here. Without
        // it, browsers can cache `/service-worker.js` itself for up to
        // 24h, which means a freshly-deployed VERSION bump goes
        // unnoticed until the cache expires. `none` makes the browser
        // hit the network for the SW file on every navigation — the
        // file is tiny, and only the SW file itself is uncached;
        // bundles + page still come from caches.
        const reg = await navigator.serviceWorker.register('/service-worker.js', {
            scope: '/',
            updateViaCache: 'none',
        });
        _state.registration = reg;

        // A worker is already waiting — that means a new version
        // installed in the background while the cashier wasn't
        // looking. Surface the update prompt.
        if (reg.waiting && navigator.serviceWorker.controller) {
            _state.updateAvailable = true;
            emit();
        }

        // Explicit update check on boot. `register()` already runs one
        // implicitly, but in practice browsers sometimes skip it when
        // the SW file came from the same session. This is a belt-and-
        // braces ping to force a re-check.
        reg.update().catch(() => { /* no-op */ });

        // New installations happen here.
        reg.addEventListener('updatefound', () => {
            const w = reg.installing;
            if (!w) return;
            w.addEventListener('statechange', () => {
                if (w.state === 'installed' && navigator.serviceWorker.controller) {
                    _state.updateAvailable = true;
                    emit();
                }
            });
        });

        // Periodic check — the browser re-runs the SW update check on
        // most navigations, but a long-lived single-page session may
        // not navigate for hours. Force a check every 5 minutes so an
        // updated deploy gets noticed within a reasonable window.
        setInterval(() => reg.update().catch(() => {}), 5 * 60 * 1000);

        // When the new SW takes over, force a reload so all assets
        // come from the freshly-claimed cache. Done ONCE per session
        // to avoid reload loops on flaky updates.
        let reloaded = false;
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (reloaded) return;
            reloaded = true;
            window.location.reload();
        });
    } catch (e) {
        // SW registration failures are non-fatal. The cashier still
        // works online; offline reload just won't survive.
        // eslint-disable-next-line no-console
        console.warn('[pwa] service worker registration failed', e);
    }
}

/**
 * Show the "Add to home screen" prompt — wired to the connectivity
 * popover's Install button. The deferred prompt can only be fired
 * once per `beforeinstallprompt` event.
 */
export async function promptInstall() {
    const p = _state.deferredPrompt;
    if (!p) return { outcome: 'unavailable' };
    p.prompt();
    const choice = await p.userChoice;
    _state.deferredPrompt = null;
    _state.installable    = false;
    emit();
    return choice;
}

/**
 * Tell the waiting SW to activate now. The controllerchange event
 * triggers a reload so the new SW serves the next request.
 */
export function applyUpdate() {
    const reg = _state.registration;
    if (!reg?.waiting) return;
    reg.waiting.postMessage({ type: 'SKIP_WAITING' });
}
