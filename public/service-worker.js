/**
 * Service Worker — Offline-first Slice 3.
 *
 * Hand-written (no Workbox dependency to keep the shared-hosting
 * deploy clean). Covers the four caching strategies from
 * docs/features/offline-sync.md §6.2:
 *
 *   - App shell (Vite bundles in /build/*)  → CacheFirst (immutable, hashed)
 *   - Navigation requests                    → NetworkFirst, fall back to cache, fall back to /offline.html
 *   - /storage/* (product images)            → StaleWhileRevalidate
 *   - Fonts                                  → CacheFirst
 *
 * The SW is ONLY useful on the cashier surface — admin pages aren't
 * worth caching for offline, and caching them risks stale views after
 * a deploy. The pwa-installer client-side script gates registration
 * on `location.pathname.startsWith('/cashier')`.
 *
 * Bumping VERSION purges every cache below — that's how new deploys
 * activate. The pwa-installer module shows the user an "Update ready"
 * toast and calls SKIP_WAITING on confirm.
 */

const VERSION   = 'v53';
const APP_SHELL = `pos-shell-${VERSION}`;
const RUNTIME   = `pos-runtime-${VERSION}`;
const IMAGES    = `pos-images-${VERSION}`;
const NAV_CACHE = `pos-nav-${VERSION}`;

// Things the SW eagerly seeds on install — the offline shell HTML is
// the must-have. Vite-built bundles get cached on first fetch via the
// CacheFirst rule, so we don't precache them here (their URLs change
// every build and trying to list them statically is brittle).
//
// `/manifest.json` is deliberately absent: it's now rendered by a Laravel route
// (branded from the company row), and `cache.addAll()` is atomic — one failing
// entry aborts the whole SW install and takes the offline shell with it. It was
// never served from cache anyway; the fetch handler passes it to the network.
const PRECACHE = [
    '/offline.html',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(APP_SHELL).then((cache) => cache.addAll(PRECACHE))
    );
    // Deliberately NOT calling self.skipWaiting() here — the new SW
    // stays in `waiting` until the user clicks "Update" in the
    // connectivity popover. The SKIP_WAITING message handler below
    // is what flips the gate when the user opts in. Without this
    // wait, a deploy reloads the cashier mid-ring-up unannounced
    // (the issue the user hit when VERSION was bumped repeatedly).
});

self.addEventListener('activate', (event) => {
    // Purge old-versioned caches on activation.
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys
                .filter((k) => k.startsWith('pos-') && !k.endsWith(VERSION))
                .map((k) => caches.delete(k))
        )).then(() => self.clients.claim())
    );
});

/**
 * SKIP_WAITING handshake — when the pwa-installer detects a new
 * waiting worker and the user clicks "Update now", it postMessages
 * SKIP_WAITING. The current SW takes over immediately.
 */
self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;                  // POSTs go straight to the network (sync queue handles offline)

    const url = new URL(req.url);

    // Same-origin only — we never cache cross-origin requests (CDN
    // fonts/CSS would need explicit handling we don't need yet).
    if (url.origin !== self.location.origin) return;

    // ── /build/* — Vite bundles (immutable, hashed URLs) ───────
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(cacheFirst(req, RUNTIME));
        return;
    }

    // ── /storage/* — product images, brand logos, etc. ───────
    if (url.pathname.startsWith('/storage/')) {
        event.respondWith(staleWhileRevalidate(req, IMAGES));
        return;
    }

    // ── Navigation requests — HTML pages ─────────────────────
    // ONLY the cashier is offline-capable. Every other page (admin, login,
    // installer, …) must go straight to the network, even though this SW is
    // registered origin-wide — otherwise a network blip, a stale worker, or a
    // cold cache would serve the offline shell on a normal admin page. Not
    // calling event.respondWith() hands the request back to the browser's
    // default network handling.
    if (req.mode === 'navigate') {
        if (url.pathname === '/cashier' || url.pathname.startsWith('/cashier/')) {
            event.respondWith(navigationStrategy(req));
        }
        return;
    }

    // ── Everything else — pass through to the network ───────
    // Heartbeat, /cashier/sync, /cashier/complete etc. MUST hit the
    // server when online so the response is fresh. The connectivity
    // machine + sync queue handle the offline case at the JS layer.
});

/* ── Strategies ───────────────────────────────────────────────── */

/**
 * Cache-first: if it's in the cache, return it. Otherwise fetch +
 * populate the cache. Used for immutable, hashed assets.
 */
async function cacheFirst(req, cacheName) {
    const cache = await caches.open(cacheName);
    const hit   = await cache.match(req);
    if (hit) return hit;
    try {
        const res = await fetch(req);
        if (res.ok) cache.put(req, res.clone());
        return res;
    } catch (_) {
        return new Response('', { status: 504 });      // offline + cache miss
    }
}

/**
 * Stale-while-revalidate: serve from cache immediately, kick off a
 * background fetch to refresh. Best for images that may slightly
 * change (variants, logo updates) but where the old version is fine.
 */
async function staleWhileRevalidate(req, cacheName) {
    const cache = await caches.open(cacheName);
    const hit   = await cache.match(req);
    const networkPromise = fetch(req).then((res) => {
        if (res.ok) cache.put(req, res.clone());
        return res;
    }).catch(() => null);
    return hit || networkPromise || new Response('', { status: 504 });
}

/**
 * Navigation strategy — NetworkFirst with NO artificial timeout.
 *
 * The cashier page is large (~50KB+ HTML with the inline product
 * catalog) and an aggressive timeout (3s) was triggering even on
 * decent connections — every reload showed the offline shell. The
 * native `fetch()` rejects fast when actually offline (TypeError
 * within ~100ms — the browser's DNS / connect attempts fail
 * immediately), so we don't need a timeout to detect offline.
 *
 * On true offline → fetch rejects → fall back to cache → fall back
 * to /offline.html.
 *
 * On slow online → fetch eventually resolves → user sees the real
 * page. They wait however long the network takes, which is the
 * correct behaviour for a slow connection (better than a misleading
 * "you're offline" while the request is still mid-flight).
 */
async function navigationStrategy(req) {
    const cache = await caches.open(NAV_CACHE);
    try {
        const res = await fetch(req);
        // Cache successful 2xx so a later offline reload can replay.
        if (res && res.ok) cache.put(req, res.clone());
        return res;
    } catch (_) {
        const hit = await cache.match(req);
        if (hit) return hit;
        return (await caches.match('/offline.html')) || new Response('Offline', { status: 504 });
    }
}
