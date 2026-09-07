/**
 * Catalog refresher — paged sync.
 *
 * Pulls the catalog from `/cashier/sync` and writes it into IDB. Large
 * catalogs (1000s of SKUs) are fetched as KEYSET PAGES rather than one
 * giant response, so the server runs several small indexed queries
 * instead of one heavy one, and the HTML/JSON payloads stay modest.
 *
 *   page 1 : GET /cashier/sync?limit=N            → metadata + first products
 *   page k : GET /cashier/sync?after=<id>&limit=N → products only
 *   …until `has_more` is false.
 *
 * Each page is upserted into IDB immediately; only after the LAST page do
 * we reconcile deletions + stamp `last_full_sync`. So an interrupted sync
 * (offline mid-loop) leaves the previously-complete catalog intact — the
 * cashier keeps working offline against last-known-good data.
 *
 * Runs:
 *   - in the BACKGROUND on cashier-page boot, after first paint;
 *   - on-demand from the connectivity popover's "Refresh data";
 *   - on connectivity-return events (sync engine).
 *
 * Concurrency: a single in-flight refresh is dedup'd via `_inFlight`.
 */

import { posGet } from '../lib/http.js';
import { applyCatalogMeta, putCatalogProducts, finalizeCatalogProducts } from './dexie-schema.js';
import { prefetchCatalogImages } from './image-prefetcher.js';
import { markOk, markFail } from './connectivity.js';

const SYNC_URL = '/cashier/sync';

// Network page size for the background loop. Larger than the inline seed
// (60) because this runs in the background — fewer round-trips, each still
// a modest, index-friendly query. A 1000-SKU store syncs in ~5 requests.
const PAGE_SIZE = 200;

// Safety stop so a server bug that always returns `has_more: true` can't
// spin forever. 200 pages × 200 = 40k products, well past v1.0 targets.
const MAX_PAGES = 200;

let _inFlight = null;

/**
 * Trigger a paged catalog refresh. Returns `{counts, syncedAt}` on success
 * or `null` on any failure (the caller usually doesn't care — the page
 * already painted from inline or warm IDB).
 *
 * @returns {Promise<{counts: Record<string, number>, syncedAt: string} | null>}
 */
export function refreshCatalog() {
    if (_inFlight) return _inFlight;

    _inFlight = (async () => {
        try {
            const seenIds = new Set();
            const counts  = { products: 0 };
            let syncedAt  = null;
            let storeId   = null;
            let after     = null;

            for (let page = 0; page < MAX_PAGES; page++) {
                const params = after === null
                    ? { limit: PAGE_SIZE }
                    : { after, limit: PAGE_SIZE };
                const { data: blob } = await posGet(SYNC_URL, params);
                if (!blob || typeof blob !== 'object') return null;

                const pageData = blob.data ?? {};
                const products = Array.isArray(pageData.products) ? pageData.products : [];

                // First page carries the bounded metadata.
                if (after === null) {
                    await applyCatalogMeta(blob);
                    syncedAt = blob.synced_at ?? null;
                    storeId  = blob.store_id ?? null;
                }

                await putCatalogProducts(products);
                products.forEach((p) => seenIds.add(p.id));
                counts.products += products.length;

                // Warm the image cache page-by-page so below-the-fold tiles
                // are available offline without one giant prefetch burst.
                prefetchCatalogImages(products);

                if (!pageData.has_more || pageData.next_after == null) break;
                after = pageData.next_after;
            }

            // Reconcile + stamp last_full_sync only once everything is in.
            await finalizeCatalogProducts(seenIds, syncedAt, storeId);

            // A successful pull is the strongest possible signal the
            // connection is good — flip the indicator without waiting
            // for the next heartbeat.
            markOk();
            return { counts, syncedAt };
        } catch (e) {
            // lib/http.js auto-toasts 5xx — we deliberately stay silent
            // here. A failed sync is an invisible non-event; the previous
            // catalog (if any) is untouched because we never stamped
            // last_full_sync this run.
            markFail();
            return null;
        } finally {
            _inFlight = null;
        }
    })();

    return _inFlight;
}
