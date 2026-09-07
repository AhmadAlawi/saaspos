/**
 * Dexie / IndexedDB schema for offline-first cashier — Slice 1.
 *
 * This slice covers READS only: catalog data the cashier needs to make
 * a sale without the server. Sync queue (writes) lands in Slice 2,
 * service worker in Slice 3, conflict resolution in Slice 4.
 *
 * Shape is intentionally DENORMALIZED — `catalog_products` rows carry
 * embedded `variants` + `kit_items` + `on_hand` etc., matching the
 * payload the cashier-page Alpine factory already consumes today. The
 * server endpoint pre-joins everything; the client reads a row, uses
 * it. No client-side joins. Wins: simpler client, faster reads, lower
 * indexing cost.
 *
 * Schema is structured so future slices extend `version(N+1)` rather
 * than mutating these tables — Dexie auto-migrates per-version.
 */

import Dexie from 'dexie';

export const db = new Dexie('pos_offline');

db.version(1).stores({
    // Catalog tables — keyed by server PK so upserts collapse onto the
    // same row across refreshes. Secondary indexes match the cashier's
    // query patterns (barcode/sku scan, category filter).
    catalog_products:        '&id, sku, barcode, category_id, name',
    catalog_categories:      '&id, parent_id, name, sort_order',
    catalog_payment_methods: '&id, code, type, sort_order',

    // App metadata — key/value bag. Keys we use:
    //   'last_full_sync' → ISO timestamp of the last successful pull
    //   'store_id'       → the active store the catalog is keyed against
    app_meta:                '&key',
});

// v2 — add `sync_queue` for Slice 2 (offline sale writes).
// Each row is one offline-completed sale waiting to POST to the
// server. Status flow: pending → syncing → synced | failed | conflict.
//   - `local_uuid` is the same client UUID we already stamp on the
//     sale payload, so server-side idempotency (CompleteSale already
//     dedupes by `sales.local_uuid`) prevents double-posting on
//     retries.
//   - Compound index `[status+created_at]` lets the sync engine pull
//     "pending or failed, oldest first" in one indexed query.
db.version(2).stores({
    sync_queue: '++id, kind, &local_uuid, status, created_at, [status+created_at]',
});

// v3 — add offline-readable customer + batch caches (Slice 2.5).
// Customer picker + batch picker both die silently offline because
// they hit the server. Pre-caching gives the cashier read access.
//   - customer_recents: top 500 by updated_at — covers regulars.
//     Phone/name/code indexes match the picker's substring search.
//   - catalog_batches: every live (qty>0) batch for the active store.
//     `[store_id+product_id+variant_id]` matches the picker's lookup.
db.version(3).stores({
    customer_recents: '&id, code, name, phone, business_name',
    catalog_batches:  '&id, [store_id+product_id+variant_id], expiry_date',
});

// v4 — add `print_queue` for the failed-print queue (hardware H7).
// Each row is a print that failed (printer offline / WebUSB error) and
// is kept locally for retry rather than lost. `payload` holds the full
// {mode, paper, html, escpos_bytes} so a retry needs no server round-trip.
// Status flow: failed → (retry) → removed on success. `attempts` caps
// the silent auto-retry loop.
db.version(4).stores({
    print_queue: '++id, status, created_at',
});

/**
 * Snapshot the entire catalog blob from /api/cashier/sync into IDB.
 *
 * Atomic across every table: a half-fetched response can't leave the
 * DB in a torn state. `last_full_sync` is only stamped if every
 * `bulkPut` succeeded — so when `hasCatalog()` returns true, the
 * cashier-page can safely read.
 *
 * @param {object} blob — the response body from /api/cashier/sync
 * @returns {Promise<{counts: Record<string, number>, syncedAt: string}>}
 */
export async function applyCatalogBlob(blob) {
    const data   = blob?.data ?? {};
    const counts = {};

    await db.transaction('rw', [
        db.catalog_products,
        db.catalog_categories,
        db.catalog_payment_methods,
        db.customer_recents,
        db.catalog_batches,
        db.app_meta,
    ], async () => {
        // Bulk-clear-then-put is the simplest correct semantics for a
        // full pull: server-deleted rows vanish locally too. Delta sync
        // (a future slice) will use bulkPut without the wipe.
        if (Array.isArray(data.products)) {
            await db.catalog_products.clear();
            await db.catalog_products.bulkPut(data.products);
            counts.products = data.products.length;
        }
        if (Array.isArray(data.categories)) {
            await db.catalog_categories.clear();
            await db.catalog_categories.bulkPut(data.categories);
            counts.categories = data.categories.length;
        }
        if (Array.isArray(data.payment_methods)) {
            await db.catalog_payment_methods.clear();
            await db.catalog_payment_methods.bulkPut(data.payment_methods);
            counts.payment_methods = data.payment_methods.length;
        }
        if (Array.isArray(data.customers)) {
            await db.customer_recents.clear();
            await db.customer_recents.bulkPut(data.customers);
            counts.customers = data.customers.length;
        }
        if (Array.isArray(data.batches)) {
            await db.catalog_batches.clear();
            await db.catalog_batches.bulkPut(data.batches);
            counts.batches = data.batches.length;
        }

        await db.app_meta.put({ key: 'last_full_sync', value: blob.synced_at });
        if (blob.store_id) {
            await db.app_meta.put({ key: 'store_id', value: blob.store_id });
        }
    });

    return { counts, syncedAt: blob.synced_at };
}

/**
 * Paged catalog sync (large-catalog support).
 *
 * The full-blob `applyCatalogBlob` above is the single-shot path. For
 * 1000s of SKUs the client instead loops keyset pages of `/cashier/sync`:
 *
 *   1. `applyCatalogMeta(blob)`     — once, on the first page: the bounded
 *      sets (categories, payment methods, customers, batches). Does NOT
 *      touch products and does NOT stamp `last_full_sync`.
 *   2. `putCatalogProducts(rows)`   — per page: upsert (no clear), so the
 *      previously-cached catalog stays intact while the sync is mid-flight.
 *   3. `finalizeCatalogProducts(seenIds, syncedAt, storeId)` — once, after
 *      the last page: delete any product NOT seen this run (server-side
 *      deletions / deactivations) and only THEN stamp `last_full_sync`.
 *
 * Reconcile-at-end is deliberate: if the connection drops on page 3 of 5,
 * the old complete catalog is untouched, so offline checkout keeps working
 * against last-known-good data instead of a half-synced set.
 */
export async function applyCatalogMeta(blob) {
    const data   = blob?.data ?? {};
    const counts = {};

    await db.transaction('rw', [
        db.catalog_categories,
        db.catalog_payment_methods,
        db.customer_recents,
        db.catalog_batches,
        db.app_meta,
    ], async () => {
        if (Array.isArray(data.categories)) {
            await db.catalog_categories.clear();
            // Normalise id to string so the IDB-boot category chips +
            // count badges match the inline payload exactly (the blade
            // injects string ids).
            await db.catalog_categories.bulkPut(
                data.categories.map((c) => ({ ...c, id: String(c.id) })),
            );
            counts.categories = data.categories.length;
        }
        if (Array.isArray(data.payment_methods)) {
            await db.catalog_payment_methods.clear();
            await db.catalog_payment_methods.bulkPut(data.payment_methods);
            counts.payment_methods = data.payment_methods.length;
        }
        if (Array.isArray(data.customers)) {
            await db.customer_recents.clear();
            await db.customer_recents.bulkPut(data.customers);
            counts.customers = data.customers.length;
        }
        if (Array.isArray(data.batches)) {
            await db.catalog_batches.clear();
            await db.catalog_batches.bulkPut(data.batches);
            counts.batches = data.batches.length;
        }
        if (blob.store_id) {
            await db.app_meta.put({ key: 'store_id', value: blob.store_id });
        }
    });

    return counts;
}

/** Upsert one page of products. No clear — pages accumulate. */
export async function putCatalogProducts(products) {
    if (!Array.isArray(products) || products.length === 0) return 0;
    await db.catalog_products.bulkPut(products);
    return products.length;
}

/**
 * Close out a paged sync: drop products not seen this run, then stamp
 * `last_full_sync` so `hasCatalog()` flips true only once the catalog is
 * whole.
 *
 * @param {Iterable<number>} seenIds — every product id returned this run
 */
export async function finalizeCatalogProducts(seenIds, syncedAt, storeId = null) {
    const seen = seenIds instanceof Set ? seenIds : new Set(seenIds);

    await db.transaction('rw', [db.catalog_products, db.app_meta], async () => {
        const allKeys = await db.catalog_products.toCollection().primaryKeys();
        const stale   = allKeys.filter((k) => !seen.has(k));
        if (stale.length) await db.catalog_products.bulkDelete(stale);

        if (syncedAt) await db.app_meta.put({ key: 'last_full_sync', value: syncedAt });
        if (storeId)  await db.app_meta.put({ key: 'store_id', value: storeId });
    });
}

/**
 * Read the catalog back out in the same shape the cashier-page factory
 * expects (the inline JSON payload the controller injects today).
 * Cold DB returns empty arrays — the caller falls back to the inline
 * payload so first-load behaviour is unchanged.
 *
 * @returns {Promise<{products: any[], categories: any[], payment_methods: any[], synced_at: string|null, store_id: number|null}>}
 */
export async function readCatalogBlob() {
    const [products, categories, paymentMethods, lastSync, storeId] = await Promise.all([
        db.catalog_products.toArray(),
        db.catalog_categories.toArray(),
        db.catalog_payment_methods.toArray(),
        db.app_meta.get('last_full_sync'),
        db.app_meta.get('store_id'),
    ]);

    return {
        products,
        categories,
        payment_methods: paymentMethods,
        synced_at: lastSync?.value ?? null,
        store_id:  storeId?.value  ?? null,
    };
}

/** Convenience: true iff we've ever stored a catalog. */
export async function hasCatalog() {
    const row = await db.app_meta.get('last_full_sync');
    return !!row?.value;
}

/** Wipe everything — used by tests + a future "clear local data" admin action. */
export async function clearCatalog() {
    await db.delete();
    return db.open();
}

/* ── Sync queue (Slice 2) ─────────────────────────────────────── */

/**
 * Push a completed-offline sale onto the queue. The `local_uuid` is
 * unique per cart (the cashier-page generates a fresh one after each
 * checkout) — also the server-side idempotency key. The unique index
 * on the column means we can't accidentally enqueue the same sale
 * twice (a defensive guard against double-tap on the Complete button).
 */
export async function enqueueSale(payload) {
    return db.sync_queue.add({
        kind:        'sale_complete',
        local_uuid:  payload.local_uuid,
        payload,                                  // entire request body, replayed verbatim on drain
        status:      'pending',
        attempts:    0,
        created_at:  new Date().toISOString(),
        last_error:  null,
        last_attempt_at: null,
    });
}

/**
 * Push an offline self-ordering kiosk order onto the queue (Slice 4).
 * Drained to POST /kiosk/place, which is idempotent by `local_uuid`
 * ({@see \App\Actions\Sales\PlaceKioskOrder}) so a retry can't double-place.
 * The `local_uuid` is stamped once per placement (the "Place order" tap),
 * so the unique index also guards against an accidental double-enqueue.
 */
export async function enqueueKioskOrder(payload) {
    return db.sync_queue.add({
        kind:        'kiosk_order',
        local_uuid:  payload.local_uuid,
        payload,                                  // entire /kiosk/place body, replayed verbatim on drain
        status:      'pending',
        attempts:    0,
        created_at:  new Date().toISOString(),
        last_error:  null,
        last_attempt_at: null,
    });
}

/**
 * Push an offline-created customer onto the queue. The `local_uuid`
 * + `local_id` (a negative integer) get assigned client-side; the
 * server's POST /cashier/customers handler returns the canonical
 * positive id, which the sync engine stashes in `customer_id_remap`
 * so subsequent sale_complete payloads can be rewritten before POST.
 *
 * @param {{local_id: number, name: string, phone?: string, email?: string}} customer
 */
export async function enqueueCustomerCreate(customer) {
    const uuid = (window.crypto && typeof window.crypto.randomUUID === 'function')
        ? window.crypto.randomUUID()
        : 'cust-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10);
    return db.sync_queue.add({
        kind:        'customer_create',
        local_uuid:  uuid,
        local_id:    customer.local_id,           // pinned so id-remap lands on the right key
        payload:     {
            name:  customer.name,
            phone: customer.phone || null,
            email: customer.email || null,
        },
        status:      'pending',
        attempts:    0,
        created_at:  new Date().toISOString(),
    });
}

/** Allocate the next negative local id for an offline customer.
 *  First caller gets -1, next gets -2, etc. — negative so the int
 *  can't ever collide with a server-assigned positive PK. */
export async function nextLocalCustomerId() {
    const row = await db.app_meta.get('next_local_customer_id');
    const id  = row?.value ?? -1;                  // -1 on cold start
    await db.app_meta.put({ key: 'next_local_customer_id', value: id - 1 });
    return id;
}

/** Persist a local→server id mapping after a customer_create syncs. */
export async function recordCustomerRemap(localId, serverId) {
    const row  = await db.app_meta.get('customer_id_remap');
    const map  = (row?.value && typeof row.value === 'object') ? row.value : {};
    map[String(localId)] = Number(serverId);
    await db.app_meta.put({ key: 'customer_id_remap', value: map });
}

/** Look up a localId → serverId from the remap; null if absent. */
export async function readCustomerRemap(localId) {
    const row = await db.app_meta.get('customer_id_remap');
    const map = (row?.value && typeof row.value === 'object') ? row.value : {};
    const v   = map[String(localId)];
    return Number.isFinite(v) ? Number(v) : null;
}

/** Pull the next batch of work — pending + previously-failed, oldest first. */
export async function pendingQueue() {
    return db.sync_queue
        .where('status').anyOf(['pending', 'failed'])
        .sortBy('created_at');
}

export async function markSyncing(id) {
    return db.sync_queue.update(id, {
        status: 'syncing',
        last_attempt_at: new Date().toISOString(),
    });
}

export async function markSynced(id, serverResult) {
    return db.sync_queue.update(id, {
        status:        'synced',
        server_result: serverResult,
        synced_at:     new Date().toISOString(),
    });
}

export async function markFailed(id, message, attempts) {
    return db.sync_queue.update(id, {
        status:     'failed',
        last_error: message,
        attempts:   attempts,
    });
}

export async function markConflict(id, body) {
    return db.sync_queue.update(id, {
        status:        'conflict',
        server_result: body,
    });
}

/** Live count of pending+failed entries — what the queue badge shows. */
export async function queueDepth() {
    return db.sync_queue
        .where('status').anyOf(['pending', 'failed'])
        .count();
}

/* ── Offline customer + batch reads (Slice 2.5) ──────────────── */

/**
 * Search the cached customer list. Mirrors the server endpoint's
 * shape (name / code / phone / business_name substring match) so
 * results render in the same UI without branching.
 */
export async function searchCachedCustomers(query, limit = 25) {
    const q = (query || '').trim().toLowerCase();
    if (q === '') {
        // Empty query → most-recently-updated 25 (server endpoint does
        // the same thing under the hood: `orderBy('name')->limit(25)`).
        return db.customer_recents.orderBy('name').limit(limit).toArray();
    }
    const all = await db.customer_recents.toArray();
    const hit = (c) =>
        (c.name?.toLowerCase().includes(q))
        || (c.code?.toLowerCase().includes(q))
        || (c.phone?.toLowerCase().includes(q))
        || (c.business_name?.toLowerCase().includes(q));
    return all.filter(hit).slice(0, limit);
}

/**
 * Read live batches for a (product, variant?) from IDB. Returns the
 * same shape as `/cashier/batches`. FEFO sort (null expiry last)
 * matches the server endpoint.
 */
export async function readCachedBatches(productId, variantId = null) {
    // Match the exact (product, variant) — NOT a compound-index range.
    // A `.between([0, p, v], [MAX, p, v])` ranges the FIRST key component
    // (store_id) and, because compound keys sort lexicographically, ends up
    // matching every row whose store_id is in [0, MAX] — i.e. ALL batches,
    // regardless of product/variant. The cache only ever holds the active
    // store, so filtering by product_id + variant_id here is both correct
    // and store-scoped.
    const wantVariant = variantId ?? null;
    const rows = await db.catalog_batches
        .filter(r => r.product_id === productId && (r.variant_id ?? null) === wantVariant)
        .toArray();

    return rows
        .filter(r => parseFloat(r.on_hand ?? r.quantity ?? 0) > 0)
        .sort((a, b) => {
            if (!a.expiry_date && !b.expiry_date) return a.id - b.id;
            if (!a.expiry_date) return  1;                // null expiry last
            if (!b.expiry_date) return -1;
            return a.expiry_date.localeCompare(b.expiry_date) || (a.id - b.id);
        });
}

/** Prune synced entries older than 7 days — keeps the queue tidy. */
export async function pruneSyncedQueue(olderThanDays = 7) {
    const cutoff = new Date(Date.now() - olderThanDays * 86400_000).toISOString();
    return db.sync_queue
        .where('status').equals('synced')
        .and(row => (row.synced_at || row.created_at) < cutoff)
        .delete();
}
