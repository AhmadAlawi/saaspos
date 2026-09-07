/**
 * Sync engine — Slice 2.
 *
 * Drains the IndexedDB `sync_queue` of offline-completed writes when
 * the cashier comes back online. Idempotency is the server's job
 * (CompleteSale dedupes by `sales.local_uuid`); retries are safe by
 * construction.
 *
 * The engine runs ONE entry at a time per cycle — batched sync per
 * docs §18.2 is a Slice 2 polish. Each cycle:
 *
 *   1. Pull all `pending` + `failed` entries, oldest first.
 *   2. For each: mark `syncing`, POST to the right endpoint.
 *   3. On 2xx → `synced` with the server's response stashed for the
 *      sync log UI (Slice 4).
 *   4. On 422/5xx → `failed` with the error message + bumped
 *      `attempts`. Next online-event triggers a retry.
 *   5. On 409 (future status) → `conflict` for manual resolution.
 *
 * Subscribers (the cashier-page's queue badge, future sync log)
 * register via `subscribeQueue(fn)` and get fresh depth on every
 * mutation.
 */

import { posPost } from '../lib/http.js';
import { subscribe as subscribeConnectivity } from './connectivity.js';
import {
    pendingQueue, markSyncing, markSynced, markFailed,
    queueDepth, pruneSyncedQueue,
    recordCustomerRemap, readCustomerRemap,
} from './dexie-schema.js';

const KIND_ENDPOINTS = {
    customer_create: '/cashier/customers',
    sale_complete:   '/cashier/complete',
    // Slice 4 — offline self-ordering kiosk orders. PlaceKioskOrder is
    // idempotent by local_uuid, so replays are safe. Shares the same
    // sync_queue (same-origin IndexedDB), so whichever page is open —
    // kiosk or cashier — drains it.
    kiosk_order:     '/kiosk/place',
};

// Drain order — customer_create MUST land server-side before any
// sale_complete that references its local negative id, so the
// id-remap is populated by the time the sale POSTs.
const KIND_PRIORITY = {
    customer_create: 0,
    sale_complete:   1,
    kiosk_order:     1,
};

const _listeners = new Set();
let _isSyncing  = false;
let _retryTimer = null;

/* ── Public API ───────────────────────────────────────────────── */

/** Subscribe to queue-depth changes. Fires immediately with current. */
export function subscribeQueue(fn) {
    _listeners.add(fn);
    queueDepth().then(d => fn(d));
    return () => _listeners.delete(fn);
}

async function emit() {
    const depth = await queueDepth();
    for (const fn of _listeners) {
        try { fn(depth); } catch (_) { /* never let a listener kill the loop */ }
    }
}

/** Force a drain pass — used after enqueueSale + on online events. */
export async function drainQueue() {
    if (_isSyncing) return;
    _isSyncing = true;

    try {
        const pending = await pendingQueue();
        if (pending.length === 0) return;

        // Sort by KIND_PRIORITY first, then created_at. Without this,
        // a sale_complete created right after a customer_create at
        // the same wall-clock millisecond could drain first and POST
        // with the un-remapped negative customer_id → 422.
        pending.sort((a, b) => {
            const pa = KIND_PRIORITY[a.kind] ?? 99;
            const pb = KIND_PRIORITY[b.kind] ?? 99;
            if (pa !== pb) return pa - pb;
            return String(a.created_at).localeCompare(String(b.created_at));
        });

        for (const entry of pending) {
            await processOne(entry);
        }
    } finally {
        _isSyncing = false;
        emit();
    }
}

/** Wire the sync engine to the connectivity machine. Call once on app boot. */
export function startSyncEngine() {
    subscribeConnectivity((state) => {
        if (state.status === 'online') drainQueue();
    });

    // Periodic retry tick — if the server flapped or returned 500 mid-
    // sync, the engine will pick failed entries up on its own every
    // 60 seconds. Cheap because the queue is usually empty.
    if (!_retryTimer) {
        _retryTimer = setInterval(() => {
            // Cheap check first: skip the work entirely if queue is empty.
            queueDepth().then(d => { if (d > 0) drainQueue(); });
        }, 60_000);
    }

    // Tidy: synced entries older than a week get pruned on boot so the
    // queue table doesn't grow unboundedly.
    pruneSyncedQueue(7);
}

/* ── Internals ────────────────────────────────────────────────── */

async function processOne(entry) {
    const url = KIND_ENDPOINTS[entry.kind];
    if (!url) {
        await markFailed(entry.id, `unknown kind: ${entry.kind}`, (entry.attempts ?? 0) + 1);
        return;
    }

    await markSyncing(entry.id);
    emit();

    try {
        // Slice 4: for sale_complete entries, rewrite any negative
        // customer_id from the local→server remap before POST.
        let payload = entry.payload;
        if (entry.kind === 'sale_complete' && payload?.customer_id != null && payload.customer_id < 0) {
            const serverId = await readCustomerRemap(payload.customer_id);
            if (serverId == null) {
                // Customer hasn't synced yet — bail and retry on next
                // drain. The drain order should prevent this, but a
                // failed earlier customer_create could leave this entry
                // stranded; mark failed so the periodic tick retries.
                await markFailed(entry.id, 'Waiting for customer to sync.', (entry.attempts ?? 0) + 1);
                return;
            }
            payload = { ...payload, customer_id: serverId };
        }

        // `posPost` already includes CSRF, JSON content-type, and the
        // shared http interceptor's auth flow. The server's CompleteSale
        // action dedupes by `payload.local_uuid` so retries from any
        // prior partial failure produce the same canonical sale.
        const { data } = await posPost(url, payload);

        // Slice 4: on customer_create success, stash localId→serverId
        // so subsequent sale_complete entries get their customer_id
        // rewritten before POST.
        if (entry.kind === 'customer_create' && entry.local_id != null && data?.id) {
            await recordCustomerRemap(entry.local_id, data.id);
        }

        await markSynced(entry.id, data ?? null);
    } catch (e) {
        // 422 → validation/business error; mark failed so the next
        //       online cycle retries (unless it's truly permanent, in
        //       which case the cashier sees it in the queue panel).
        // 5xx → transient; same treatment.
        // Anything else (network drop mid-request) → same.
        const msg = e?.message
            || e?.errors?._action?.[0]
            || (e?.status ? `HTTP ${e.status}` : 'sync failed');
        const nextAttempts = (entry.attempts ?? 0) + 1;
        await markFailed(entry.id, msg, nextAttempts);
    }
}
