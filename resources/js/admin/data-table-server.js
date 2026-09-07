/**
 * Server-paginated counterpart to `dataTableMixin`.
 *
 * Same public surface — `search`, `page`, `pageSize`, `sortCol`, `matchedCount`,
 * `totalPages`, `pageStart`, `pageEnd`, `isEmpty`, `prev()`, `next()`,
 * `gotoPage()`, `setPageSize()`, `pageWindow` — so `<x-admin.data-table>` and
 * `<x-admin.dt-pager>` bind to it without a single markup change. The difference
 * is where the work happens: the client mixin renders every row up-front and
 * hides the ones it doesn't want; this one asks the server for exactly the page
 * it needs and swaps that HTML in.
 *
 * Use it when a list can grow without bound (products, sales, customers). For a
 * fixed 20-row lookup table the client mixin is faster — don't pay a round trip
 * to filter twenty rows.
 *
 * ## The host contract
 *
 * The composing factory must provide:
 *   `_dtEndpoint`        — string, the rows URL
 *   `_dtContainer()`     — the element whose innerHTML holds the rows
 *
 * and may provide:
 *   `_dtExtraParams()`   — extra query params (filters, view mode, …)
 *   `_dtAfterSwap(el)`   — runs after new rows are in the DOM and Alpine-wired
 *   `_dtOnMeta(meta)`    — runs on every response (summary cards, counters, …)
 *
 * ## Always-fresh (no client cache, no prefetch)
 *
 * Every page change, filter, or search fetches that exact page from the server
 * — the classic server-side-DataTables model. There is deliberately NO response
 * cache and NO next-page prefetch: on live POS data (sales, stock, sync) showing
 * the current server truth matters more than a 0ms revisit, and a prefetched
 * page could already be stale by the time the user reaches it. Two things still
 * keep it snappy without hiding fresh data:
 *   1. Search is debounced, and each keystroke aborts the in-flight request, so
 *      only the settled term is ever fetched.
 *   2. A burst of watcher callbacks that resolve to the SAME param set (e.g.
 *      `resetFilters()` clearing several controls at once) collapses to a single
 *      request via `_dtLastKey` — request de-dup, not caching.
 *
 * `dtInvalidate()` is retained as a no-op for call-site compatibility: with no
 * cache there is nothing to invalidate, and the next navigation always refetches.
 */

import { posGet } from '../lib/http.js';
import { buildPageWindow, decodeCfEmails } from './data-table.js';

/** axios rejections are normalised by the http interceptor; the raw error
 *  still carries the cancellation code. An abort is expected, never an error. */
const isAbort = (e) => e?.raw?.code === 'ERR_CANCELED' || e?.raw?.name === 'CanceledError';

export function dataTableServerMixin({
    endpoint,
    initialPageSize = 25,
    pageSizes       = [10, 25, 50, 100],
    searchDebounce  = 250,
} = {}) {
    return {
        // ── User-controlled state (mirrors dataTableMixin) ─────────
        search:   '',
        page:     1,
        pageSize: initialPageSize,
        pageSizes,
        pageSizeOpen: false,

        sortCol: null,
        sortDir: null,

        // ── Server-derived state ───────────────────────────────────
        matchedCount: 0,
        totalPages:   1,
        pageStart:    0,
        pageEnd:      0,
        isFiltered:   false,
        isPaginated:  false,
        isEmpty:      false,
        isPristine:   true,
        dtReady:      false,
        isTable:      true,

        // ── Internal ───────────────────────────────────────────────
        _dtEndpoint:    endpoint,
        _dtRoot:        null,
        _dtRefreshing:  false,  // toolbar refresh button spinner
        _dtLoading:     false,  // any row fetch in flight
        _dtAbort:       null,   // AbortController for the current primary fetch
        _dtSeq:         0,      // guards against a slow response overwriting a fast one
        _dtSearchTimer: null,
        _dtLastKey:     null,   // param set currently on screen — collapses duplicate loads

        get pageWindow() { return buildPageWindow(this.page, this.totalPages); },

        // ── Setup ──────────────────────────────────────────────────
        /**
         * Seed from the first page, which the server already rendered inline.
         * No fetch on boot — the rows are on screen before Alpine runs.
         *
         * @param {{total:number, page:number, totalPages:number, perPage:number}} meta
         */
        initDataTableServer(meta = {}) {
            // Cache the root BEFORE any await — `this.$el` only resolves while
            // a directive expression is evaluating.
            this._dtRoot = this.$el;

            this.pageSize = meta.perPage ?? this.pageSize;

            // Fall back to counting the rows the server rendered. `total` should
            // always be supplied, but if the host ever boots without it — a
            // stale compiled Blade paired with a fresh bundle, say — counting
            // beats defaulting to 0, which would paint "No matches" on top of
            // rows the user can plainly see.
            this._dtApplyMeta({
                total:       meta.total ?? this._dtRenderedRowCount(),
                page:        meta.page ?? 1,
                total_pages: meta.totalPages ?? 1,
                per_page:    this.pageSize,
            });
            this.dtReady = true;

            // The server already rendered this param set, so a `_dtGo` that
            // resolves to the same key is a no-op rather than a redundant fetch.
            this._dtLastKey = this._dtParamKey(this._dtParams(this.page));

            // Debounce the search fetch here rather than in the input's
            // `x-model.debounce` so there's exactly one delay, not two stacked.
            this.$watch('search', () => {
                clearTimeout(this._dtSearchTimer);
                this._dtSearchTimer = setTimeout(() => this._dtGo(1), searchDebounce);
            });
        },

        /** How many rows the server actually put in the container. */
        _dtRenderedRowCount() {
            return this._dtContainer?.()?.querySelectorAll('[data-dt-row]').length ?? 0;
        },

        // ── Params ─────────────────────────────────────────────────
        _dtParams(page = this.page) {
            const params = {
                page,
                per_page: this.pageSize,
                ...(typeof this._dtExtraParams === 'function' ? this._dtExtraParams() : {}),
            };
            const term = (this.search ?? '').trim();
            if (term) params.q = term;
            if (this.sortCol && this.sortDir) {
                params.sort = this.sortCol;
                params.dir  = this.sortDir;
            }
            return params;
        },

        /** Stable key regardless of property insertion order. Used only to
         *  de-dup back-to-back loads that resolve to the same param set. */
        _dtParamKey(params) {
            return Object.keys(params).sort().map((k) => `${k}=${params[k]}`).join('&');
        },

        /** No-op: always-fresh mode keeps no client cache, so there is nothing
         *  to invalidate. Retained so mutation handlers (delete / toggle) can
         *  call it without knowing the mixin's internals — the next navigation
         *  refetches from the server regardless. */
        dtInvalidate() {},

        // ── Loading ────────────────────────────────────────────────
        /**
         * Go to `page` and replace the rows.
         *
         * `page` has to move before the fetch, because it's part of the request.
         * But if the fetch fails, leaving it moved would strand the pager
         * highlighting a page whose rows never arrived — and `prev()`/`next()`
         * derive their disabled state from it. So roll back, unless something
         * else navigated while we were waiting.
         */
        async _dtGo(page) {
            const from = this.page;
            const to   = Math.max(1, page);

            this.page = to;
            const loaded = await this._dtLoad({ append: false });
            if (!loaded && this.page === to) this.page = from;

            return loaded;
        },

        /**
         * Fetch the current param set and put the rows in the DOM.
         *
         * Always fetches the current param set fresh from the server — no cache.
         *
         * @param {{append?: boolean, bustCache?: boolean}} opts
         *   append    — concatenate rather than replace (grid infinite scroll)
         *   bustCache — bypass the same-key de-dup guard (the toolbar Refresh
         *               button, so re-fetching the page you're already on works)
         * @returns {Promise<boolean>} true when rows landed in the DOM. False
         *   means the load errored or was superseded — callers that advanced
         *   `page` to fetch must undo it.
         */
        async _dtLoad({ append = false, bustCache = false } = {}) {
            const params = this._dtParams();
            const key    = this._dtParamKey(params);

            // These params are already on screen. This collapses the burst of
            // watcher callbacks a multi-field `resetFilters()` fires — they all
            // resolve to one key — into a single load. `bustCache` (Refresh)
            // bypasses it so the current page can be re-fetched on demand.
            if (!append && !bustCache && key === this._dtLastKey) return true;

            const seq = ++this._dtSeq;
            this._dtLastKey = key;

            // A newer request supersedes whatever is in flight.
            this._dtAbort?.abort();
            this._dtAbort = new AbortController();
            this._dtLoading = true;

            let payload;
            try {
                const { data } = await posGet(this._dtEndpoint, params, { signal: this._dtAbort.signal });
                payload = data;
            } catch (e) {
                // An abort means the user typed again and the newer request owns
                // the outcome — including `_dtLoading` and `_dtLastKey`, which it
                // already overwrote. A real failure has already toasted via the
                // http interceptor; clear the key so a retry isn't read as a no-op.
                if (!isAbort(e) && seq === this._dtSeq) {
                    this._dtLoading = false;
                    this._dtLastKey = null;
                }
                return false;
            }

            // A slower earlier request must never overwrite a newer one's rows.
            if (seq !== this._dtSeq) return false;

            this._dtLoading = false;
            this._dtSwap(payload.html, payload, append);
            return true;
        },

        _dtSwap(html, meta, append) {
            const container = this._dtContainer?.();
            if (!container) return;

            if (append) {
                container.insertAdjacentHTML('beforeend', html);
            } else {
                container.innerHTML = html;
            }

            // Cloudflare's email-obfuscation decoder never re-runs on nodes we
            // inject, so emails would stay as "[email protected]".
            decodeCfEmails(container);

            // Wire @click / :class / x-show on the freshly inserted rows.
            if (window.Alpine?.initTree) window.Alpine.initTree(container);

            this._dtApplyMeta(meta);
            this._dtAfterSwap?.(container, meta);
        },

        _dtApplyMeta(meta) {
            const total = meta.total ?? 0;
            const per   = meta.per_page ?? this.pageSize;

            this.matchedCount = total;
            this.page         = meta.page ?? 1;
            this.totalPages   = Math.max(1, meta.total_pages ?? 1);
            this.pageStart    = total === 0 ? 0 : (this.page - 1) * per + 1;
            this.pageEnd      = Math.min(this.page * per, total);
            this.isFiltered   = (this.search ?? '').trim() !== '';
            this.isPaginated  = this.totalPages > 1;
            this.isEmpty      = total === 0;
            this.isPristine   = !this.isFiltered && !this.isPaginated;

            this._dtOnMeta?.(meta);
        },

        // ── Pager actions ──────────────────────────────────────────
        prev()      { if (this.page > 1)               this._dtGo(this.page - 1); },
        next()      { if (this.page < this.totalPages) this._dtGo(this.page + 1); },
        gotoPage(p) { this._dtGo(Math.min(this.totalPages, parseInt(p, 10) || 1)); },
        clearSearch() { this.search = ''; },

        setPageSize(n) {
            this.pageSizeOpen = false;
            if (n === this.pageSize) return;
            this.pageSize = n;
            this._dtGo(1);
        },

        // ── Column sort ────────────────────────────────────────────
        /** asc → desc → off, matching the client mixin. Off restores server order. */
        sortBy(col) {
            if (this.sortCol !== col) {
                this.sortCol = col;
                this.sortDir = 'asc';
            } else if (this.sortDir === 'asc') {
                this.sortDir = 'desc';
            } else if (this.sortDir === 'desc') {
                this.sortCol = null;
                this.sortDir = null;
            } else {
                this.sortDir = 'asc';
            }
            this._dtGo(1);
        },

        sortDirFor(col) { return this.sortCol === col ? this.sortDir : null; },

        setSort(col, dir) {
            this.sortCol = dir ? col : null;
            this.sortDir = dir || null;
            this._dtGo(1);
        },

        // ── Refresh ────────────────────────────────────────────────
        /** Toolbar refresh — re-fetch from the server, bypassing (and clearing)
         *  the cache. A paged table stays on its page; an appending view
         *  restarts at page 1. */
        async dtRefresh() {
            if (this._dtRefreshing) return;
            this._dtRefreshing = true;
            try {
                this.dtInvalidate();

                // An appending view (infinite scroll) has pages 1..N stacked in
                // one container. Reloading page N in place would replace all of
                // them with just page N's rows, so restart from the top.
                if (this._dtAppends?.()) this.page = 1;

                await this._dtLoad({ append: false, bustCache: true });
            } finally {
                this._dtRefreshing = false;
            }
        },
    };
}

/**
 * Combine the server mixin with page-specific extras, preserving getter /
 * setter descriptors (which `...spread` would silently flatten into values).
 * Mirrors `composeDataTable`.
 */
export function composeDataTableServer(mixinOpts = {}, extras = {}) {
    const base = dataTableServerMixin(mixinOpts);
    for (const key of Object.getOwnPropertyNames(extras)) {
        Object.defineProperty(base, key, Object.getOwnPropertyDescriptor(extras, key));
    }
    return base;
}
