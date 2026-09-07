/**
 * Customer-Facing Display (CFD) — same-machine transport.
 *
 * The cashier window (publisher) and the customer-display window
 * (subscriber) run on the SAME machine, same origin — so we move cart
 * snapshots between them entirely client-side, with no server round-trip
 * and no network. That's what lets the CFD keep working when the internet
 * is down (the checkout is offline-first; a CFD that died on a drop would
 * be worse than none). See docs/features/customer-display.md §5 (Tier 1).
 *
 * Primary transport is BroadcastChannel; where it's missing (older
 * Safari), we fall back to a `localStorage` write + the `storage` event,
 * which fires in *other* same-origin tabs — exactly the cross-window
 * delivery we need.
 *
 * Both sides derive the SAME channel name from the bound terminal
 * (`pos-cfd:{terminalId|default}`), so two POS on one machine never
 * cross-talk.
 */

const LS_PREFIX = 'pos_cfd_msg:';

/**
 * A tiny publisher for the cashier side. Returns `{ publish, close }`.
 * `publish(snapshot)` is safe to call on every cart change — the CFD
 * drops out-of-order frames by `seq`, so over-publishing is harmless.
 */
export function createCfdPublisher(channelName) {
    let bc = null;
    try {
        if (typeof BroadcastChannel !== 'undefined') {
            bc = new BroadcastChannel(channelName);
        }
    } catch (e) {
        bc = null;
    }

    return {
        publish(snapshot) {
            const payload = JSON.stringify(snapshot);
            if (bc) {
                try { bc.postMessage(snapshot); return; } catch (e) { /* fall through */ }
            }
            // Fallback: bump a per-channel key so the `storage` event fires
            // in the CFD window. The value must actually change each write,
            // hence the snapshot's monotonic `seq`.
            try { localStorage.setItem(LS_PREFIX + channelName, payload); } catch (e) {}
        },
        close() {
            if (bc) { try { bc.close(); } catch (e) {} }
        },
    };
}

/**
 * Subscribe on the customer-display side. `onSnapshot(snap)` is called for
 * every frame received on either transport. Returns an unsubscribe fn.
 */
export function subscribeCfd(channelName, onSnapshot) {
    let bc = null;
    try {
        if (typeof BroadcastChannel !== 'undefined') {
            bc = new BroadcastChannel(channelName);
            bc.onmessage = (e) => { if (e?.data) onSnapshot(e.data); };
        }
    } catch (e) {
        bc = null;
    }

    const key = LS_PREFIX + channelName;
    const onStorage = (e) => {
        if (e.key !== key || !e.newValue) return;
        try { onSnapshot(JSON.parse(e.newValue)); } catch (err) {}
    };
    window.addEventListener('storage', onStorage);

    return () => {
        window.removeEventListener('storage', onStorage);
        if (bc) { try { bc.close(); } catch (e) {} }
    };
}
