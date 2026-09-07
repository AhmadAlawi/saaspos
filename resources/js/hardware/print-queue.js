/**
 * Failed-print queue (docs/features/hardware.md §9).
 *
 * When a print fails (printer offline, paper out, WebUSB transfer error)
 * the payload is parked in IndexedDB instead of being lost. A topbar
 * indicator surfaces the backlog; the operator (or the silent 30s
 * auto-retry) re-sends it once the printer is back. The stored `payload`
 * is the complete {mode, paper, html, escpos_bytes}, so a retry needs no
 * server round-trip.
 *
 * Reuses the shared `pos_offline` Dexie DB (table added in v4).
 */
import { db } from '../offline/dexie-schema.js';

/** Max silent auto-retries before we stop and wait for manual action. */
export const MAX_SILENT_RETRIES = 5;

/** Notify any open queue panel that the backlog changed. */
function announce() {
    try {
        window.dispatchEvent(new CustomEvent('pos:print-queue-changed'));
    } catch (_) { /* SSR / no window */ }
}

/**
 * Park a failed print.
 *
 * @param {object} item { reference_type, reference_id, label, payload, error }
 * @returns {Promise<number>} the new row id
 */
export async function enqueueFailedPrint({ reference_type, reference_id = null, label = '', payload, error = null }) {
    const id = await db.print_queue.add({
        reference_type,
        reference_id,
        label,
        payload,
        attempts: 0,
        status: 'failed',
        last_error: error,
        created_at: new Date().toISOString(),
    });
    announce();
    return id;
}

/** All queued prints, oldest first. */
export async function listFailedPrints() {
    return db.print_queue.orderBy('created_at').toArray();
}

export async function printQueueDepth() {
    return db.print_queue.count();
}

export async function removeFailedPrint(id) {
    await db.print_queue.delete(id);
    announce();
}

/** Record a failed retry attempt (increments the counter, stores the error). */
export async function bumpAttempt(id, error = null) {
    const row = await db.print_queue.get(id);
    if (!row) return;
    await db.print_queue.update(id, {
        attempts: (row.attempts || 0) + 1,
        last_error: error,
    });
    announce();
}
