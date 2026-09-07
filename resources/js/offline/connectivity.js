/**
 * Connectivity state machine — Slice 1.
 *
 * Three signals (per docs/features/offline-sync.md §8.1):
 *   1. `navigator.onLine` and online/offline events — the browser's idea
 *   2. A 30s heartbeat ping to /cashier/heartbeat — the trustworthy signal
 *   3. Explicit `fail()` calls from the http interceptor on network errors
 *
 * The state machine:
 *   [unknown] --ping ok--> [online] --ping fail--> [degraded] --3 fails--> [offline]
 *                              ^                          |
 *                              +------ ping ok -----------+
 *
 * `degraded` is the cushion that keeps the UI from flapping when a
 * blip drops a single request. The badge only flips to red after we've
 * been continuously degraded long enough to be confident.
 *
 * Slice 1 surfaces only state + lastSyncedAt + a subscribe() hook for
 * the indicator. The sync engine in Slice 2 hooks `online` events to
 * drain the queue.
 */

const HEARTBEAT_URL  = '/cashier/heartbeat';
const POLL_INTERVAL  = 30_000;   // 30s
const DEGRADED_LIMIT = 3;        // consecutive failures before we say "offline"

const _listeners = new Set();
const _state = {
    status:        'unknown',                  // 'unknown' | 'online' | 'degraded' | 'offline'
    lastOk:        null,                       // ISO of last successful ping or sync
    consecutiveFails: 0,
    _timer:        null,
};

function emit() {
    for (const fn of _listeners) {
        try { fn({ ...readState() }); } catch (_) { /* never let a listener kill the loop */ }
    }
}

function setStatus(next) {
    if (_state.status === next) return;
    _state.status = next;
    emit();
}

async function ping() {
    try {
        const res = await fetch(HEARTBEAT_URL, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            // Browsers cache GETs aggressively for the SW path. Cache-bust
            // so a stale 200 doesn't keep painting "online" once we're
            // really offline.
            cache: 'no-store',
        });
        if (!res.ok) throw new Error('heartbeat ' + res.status);
        await res.json();
        _state.consecutiveFails = 0;
        _state.lastOk = new Date().toISOString();
        setStatus('online');
        return true;
    } catch (e) {
        _state.consecutiveFails += 1;
        // Browser-level network signal is the most decisive answer we
        // have. When `navigator.onLine === false`, commit straight to
        // `offline` even on the first fail — otherwise a cold boot
        // while offline strands the indicator at `unknown` until the
        // 3rd heartbeat (90s) and the user thinks the page is broken.
        const browserOffline = typeof navigator !== 'undefined' && navigator.onLine === false;
        if (browserOffline || _state.consecutiveFails >= DEGRADED_LIMIT) {
            setStatus('offline');
        } else if (_state.status === 'online' || _state.status === 'unknown') {
            // Cold boot with browser saying online but heartbeat
            // failing → degraded. Keeps polling; flips to offline
            // after DEGRADED_LIMIT failed pings.
            setStatus('degraded');
        }
        return false;
    } finally {
        // Always re-fire listeners so subscribers get fresh `lastOk` /
        // `consecutiveFails` even when the status string didn't move.
        emit();
    }
}

/**
 * Mark the connection successful — used by the catalog refresher
 * after a successful pull so we don't need to wait for the next
 * heartbeat tick to flip back to green.
 */
export function markOk() {
    _state.consecutiveFails = 0;
    _state.lastOk = new Date().toISOString();
    setStatus('online');
}

/**
 * Mark the connection as failing — used by the http interceptor
 * when a request rejects with a network error. Two of these in quick
 * succession flip the state to offline without waiting for the
 * heartbeat poll.
 */
export function markFail() {
    _state.consecutiveFails += 1;
    if (_state.consecutiveFails >= DEGRADED_LIMIT) setStatus('offline');
    else if (_state.status === 'online') setStatus('degraded');
    emit();
}

/** Reactive read of the current state. */
export function readState() {
    return {
        status:           _state.status,
        lastOk:           _state.lastOk,
        consecutiveFails: _state.consecutiveFails,
    };
}

/** Subscribe to state changes — returns an unsubscribe fn. */
export function subscribe(fn) {
    _listeners.add(fn);
    fn({ ...readState() });
    return () => _listeners.delete(fn);
}

/** Bootstrap — call once on app start. Idempotent. */
export function startConnectivity() {
    if (_state._timer) return;

    // Browser hints — fast pre-check before the first heartbeat fires.
    window.addEventListener('online',  () => ping());
    window.addEventListener('offline', () => {
        // Browser knows we're offline → no point waiting for 3 pings.
        _state.consecutiveFails = DEGRADED_LIMIT;
        setStatus('offline');
        emit();
    });

    // Cold-start short-circuit — if the browser is already telling us
    // we're offline, paint the indicator immediately rather than
    // running an obviously-doomed heartbeat first.
    if (typeof navigator !== 'undefined' && navigator.onLine === false) {
        setStatus('offline');
        emit();
    }

    // First heartbeat NOW so the indicator paints accurately on load.
    ping();

    // Periodic poll.
    _state._timer = setInterval(() => ping(), POLL_INTERVAL);
}

/** Force a fresh ping — used by the connectivity popover's "Refresh" button. */
export function forcePing() {
    return ping();
}
