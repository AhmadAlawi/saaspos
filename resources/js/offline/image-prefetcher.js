/**
 * Product image pre-fetcher — Slice 1 polish.
 *
 * The cashier's product tiles + cart-line thumbs both use
 * `<img loading="lazy">`, so images only download when they scroll
 * into view. That's fine online, but it breaks offline in one specific
 * case: the cashier scans a barcode for a product whose tile was
 * never scrolled to, the cart line references its image_url, and the
 * browser cache has nothing for that URL → broken image icon.
 *
 * The fix: as soon as we get a fresh catalog blob, fire a hidden
 * `new Image()` for every product image so the browser's HTTP cache
 * picks them up while we're still online. Subsequent renders (tile
 * or cart) hit the cache instead of the network, including when the
 * cashier is now offline.
 *
 * This is a partial fix — browser cache eviction is unpredictable.
 * The bullet-proof version lands in Slice 3 when Workbox caches
 * `/storage/*` via `StaleWhileRevalidate`.
 *
 * Concurrency: capped at 12 simultaneous prefetches so we don't
 * saturate the connection on a 500-product catalog (a typical
 * cashier load fetches catalog + maybe a customer search + the
 * first heartbeat; piling 500 image requests on top of that would
 * delay the interactive paint).
 */

const MAX_CONCURRENT = 12;
const _seen = new Set();
const _queue = [];
let _running = 0;

/**
 * Walk the catalog and queue a hidden Image() for every product /
 * variant image URL. Idempotent across calls — URLs we've already
 * fetched are skipped on subsequent calls.
 */
export function prefetchCatalogImages(products) {
    if (!Array.isArray(products)) return;
    for (const p of products) {
        if (p?.image_url) enqueue(p.image_url);
        if (Array.isArray(p?.variants)) {
            for (const v of p.variants) {
                if (v?.image_url) enqueue(v.image_url);
            }
        }
    }
    pump();
}

function enqueue(url) {
    if (_seen.has(url)) return;
    _seen.add(url);
    _queue.push(url);
}

function pump() {
    while (_running < MAX_CONCURRENT && _queue.length > 0) {
        const url = _queue.shift();
        _running++;
        const img = new Image();
        const done = () => { _running--; pump(); };
        img.onload  = done;
        img.onerror = done;   // failures shouldn't stall the queue
        img.src = url;
    }
}
