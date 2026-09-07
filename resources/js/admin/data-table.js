/**
 * Reusable Alpine "data table" behavior.
 *
 * Works on any DOM list — `<ul>`, `<tbody>`, anything that contains a set
 * of row elements. The server still renders the markup; this module just
 * hides/shows rows via a `dt-hidden` class to implement client-side
 * search and pagination.
 *
 * **State model.** All public reactive state — `matchedCount`, `isEmpty`,
 * `totalPages`, `pageStart`, `pageEnd`, `isFiltered`, `isPaginated`,
 * `isPristine` — is stored as plain reactive properties and rewritten
 * by `_dtRender()` after every collect / search / page change. This
 * deliberately avoids chained getters: when `<ul>.innerHTML` is swapped
 * by an AJAX refresh, the host calls `refreshDataTable()` and the
 * direct-write pattern guarantees every template binding picks up the
 * new values on the next flush. Earlier getter-based versions tracked
 * `_dtMatched.length` through two indirections and the dependency
 * tracking proved unreliable across DOM swaps + initTree calls.
 *
 * **Why no spread.** Object spread (`...dataTableMixin(opts)`) invokes
 * every getter at spread time and copies the resulting *value* as a
 * data property, which permanently breaks reactivity for the
 * `totalRows` getter below. Use {@link composeDataTable} instead — it
 * copies getter/setter *descriptors* and keeps them live.
 */

const TRIM = (s) => (s ?? '').toString().trim();

const pageSlot = (page) => ({ key: `p${page}`, page, gap: false });
const gapSlot  = (side) => ({ key: `gap-${side}`, page: null, gap: true });

/**
 * Collapse a page range into at most 7 slots so the pager stays one row wide no
 * matter how many pages exist. A server-paginated 7,548-row table at 10/page has
 * 755 pages; rendering a button each would blow the card apart.
 *
 * Returns slot objects, gaps standing in for the elided runs:
 *   page 1   of 755  →  1 2 3 4 … 755
 *   page 43  of 755  →  1 … 42 43 44 … 755
 *   page 755 of 755  →  1 … 752 753 754 755
 *
 * Each slot carries a stable, unique `key` (`p43`, `gap-left`, `gap-right`) so
 * an `x-for` can key on identity. Keying on the array index instead would let
 * Alpine recycle the element that was an ellipsis into one that shows a page
 * number — one stale binding away from a page you can see but cannot click.
 *
 * @returns {Array<{key: string, page: number|null, gap: boolean}>}
 */
export function buildPageWindow(page, totalPages) {
    const total = Math.max(1, totalPages | 0);
    if (total <= 7) return Array.from({ length: total }, (_, i) => pageSlot(i + 1));

    const cur = Math.min(Math.max(1, page | 0), total);

    let start = Math.max(2, cur - 1);
    let end   = Math.min(total - 1, cur + 1);
    if (cur <= 3)         { start = 2;         end = 4; }
    if (cur >= total - 2) { start = total - 3; end = total - 1; }

    const out = [pageSlot(1)];
    if (start > 2) out.push(gapSlot('left'));
    for (let i = start; i <= end; i++) out.push(pageSlot(i));
    if (end < total - 1) out.push(gapSlot('right'));
    out.push(pageSlot(total));
    return out;
}

/**
 * Re-decode any Cloudflare-obfuscated email addresses inside a freshly
 * inserted DOM subtree.
 *
 * When Cloudflare's "Email Address Obfuscation" is on, it rewrites emails
 * in the HTML to `<a class="__cf_email__" data-cfemail="<hex>">[email&nbsp;
 * protected]</a>` and ships a script that decodes them on first load. That
 * script never re-runs on nodes we swap in via an AJAX refresh, so the
 * placeholder text "[email protected]" would stick. We reverse the (public,
 * trivial) XOR encoding ourselves so refreshed rows read correctly.
 */
export function decodeCfEmails(root) {
    if (!root) return;
    root.querySelectorAll('[data-cfemail]').forEach((el) => {
        const enc = el.getAttribute('data-cfemail');
        if (!enc) return;
        try {
            const key = parseInt(enc.substr(0, 2), 16);
            let email = '';
            for (let i = 2; i < enc.length; i += 2) {
                email += String.fromCharCode(parseInt(enc.substr(i, 2), 16) ^ key);
            }
            el.textContent = email;
            el.removeAttribute('data-cfemail');
        } catch {
            /* leave the placeholder rather than render garbage */
        }
    });
}

/**
 * The mixin form — returns an object literal with state + methods.
 * Combine with {@link composeDataTable}, never with the spread operator.
 */
export function dataTableMixin({
    rowsSelector       = '[data-dt-row]',
    searchableSelector = null,             // null = search whole row text
    initialPageSize    = 25,
    pageSizes          = [10, 25, 50, 100],
} = {}) {
    return {
        // ── User-controlled state ──────────────────────────────────
        search:   '',
        page:     1,
        pageSize: initialPageSize,
        pageSizes,
        // Open-state for the custom (non-native) page-size dropdown in
        // the pager — see <x-admin.data-table>'s pager markup.
        pageSizeOpen: false,

        // Sort state — set via sortBy(col). `sortCol === null` means
        // "use server order" (DOM document order). Cycle on repeated
        // clicks: null → 'asc' → 'desc' → null.
        sortCol: null,
        sortDir: null,

        // ── Derived state (rewritten by _dtRender) ─────────────────
        // Initial values update once _dtRender runs. `isEmpty` starts
        // FALSE and the empty-state markup is gated behind `dtReady` so
        // the "No matches" panel never flashes before the first render
        // has actually counted the rows.
        matchedCount: 0,
        totalPages:   1,
        pageStart:    0,
        pageEnd:      0,
        isFiltered:   false,
        isPaginated:  false,
        isEmpty:      false,
        isPristine:   true,
        // True only after the first _dtRender — empty state waits for it.
        dtReady:      false,

        // Default `isTable` so the pager's `isTable !== false` check
        // resolves to true on every host that doesn't define one
        // (e.g. the standalone `dataTable()` factory used by the
        // Products list). Hosts that need conditional pagination
        // (Categories' tree view) override with a getter.
        isTable: true,

        // Total row count — live after initDataTable(); used by toolbar
        // title spans so the count updates after AJAX create/delete.
        get rowCount() { return this._dtRows.length; },

        // Windowed page numbers for the pager — see `buildPageWindow`.
        get pageWindow() { return buildPageWindow(this.page, this.totalPages); },

        // ── Internal scratch ───────────────────────────────────────
        _dtRows:        [],
        _dtMatched:     [],
        _dtEverSorted:  false, // tracks whether DOM was ever reordered so "Default" can restore
        _dtRefreshing:  false, // true while an AJAX row-refresh is in flight
        _dtBody:        null,  // the rows container (tbody / ul) — set in _dtCollect()
        // Stable root reference. `this.$root` only resolves while a
        // directive expression is evaluating; once an async method
        // awaits, the magic context is gone and `this.$root` is
        // undefined. Caching the element here in initDataTable() (which
        // runs synchronously from the host's init() — where `$el`
        // *is* the root) gives us a reference that stays valid for the
        // lifetime of the component.
        _dtRoot:    null,

        // ── Setup ──────────────────────────────────────────────────
        initDataTable() {
            // Cache the root element BEFORE any await/$nextTick — see
            // `_dtRoot` docblock above.
            this._dtRoot = this.$el;

            // The shared `.inv-filter` AJAX handler (lib/inv-filter-ajax.js)
            // swaps the table region itself on search / filter / reset, then
            // fires this so the mixin re-scans the new rows and re-applies
            // client-side pagination / sort / empty-state. Harmless on pages
            // without a filter form.
            this._dtRoot.addEventListener('dt:recollect', () => this.refreshDataTable());

            this.$watch('search',   () => { this.page = 1; this._dtRender(); });
            this.$watch('pageSize', () => { this.page = 1; this._dtRender(); });
            this.$watch('page',     () => this._dtRender());
            this.$watch('sortCol',  () => { this.page = 1; this._dtRender(); });
            this.$watch('sortDir',  () => this._dtRender());

            // Defer the initial DOM scan to next tick so Alpine has
            // finished wiring directives on every descendant first.
            // Render twice across two ticks — the first tick computes
            // matched/visibility against the freshly-scanned rows; the
            // second runs after Alpine has flushed the reactive writes
            // from the first (matchedCount, totalPages, isTable…) so
            // every x-show / x-text in the pager + empty state lines
            // up with the values the imperative DOM toggle just wrote.
            // Without the second pass, the pager has been observed to
            // stay hidden on first paint until any user interaction
            // (sort click, search keypress) re-fires _dtRender.
            this.$nextTick(() => {
                this._dtCollect();
                this._dtRender();
                this.$nextTick(() => this._dtRender());
            });
        },

        /** Re-scan the DOM and re-render. Call this after replacing the
         *  row markup (e.g. an AJAX list refresh). */
        refreshDataTable() {
            this._dtCollect();
            this._dtRender();
        },

        _dtCollect() {
            this._dtRows = Array.from(this._dtRoot.querySelectorAll(rowsSelector));
            this._dtBody = this._dtRows[0]?.parentElement ?? null;
            this._dtRows.forEach((row, i) => {
                row._dtOriginalIndex = i;
                if (searchableSelector) {
                    // `querySelectorAll` so multi-selectors like
                    // `.ds-row-name, .ds-row-code` index BOTH targets.
                    // The previous `querySelector` returned only the
                    // first match in document order — Drug Schedules'
                    // name was invisible to search because `.ds-row-code`
                    // appears earlier in the markup.
                    const targets = row.querySelectorAll(searchableSelector);
                    row._dtText = Array.from(targets)
                        .map((el) => TRIM(el.textContent))
                        .filter(Boolean)
                        .join(' ')
                        .toLowerCase();
                } else {
                    row._dtText = TRIM(row.textContent).toLowerCase();
                }
            });
        },

        _dtRender() {
            const term    = TRIM(this.search).toLowerCase();
            let matched = term
                ? this._dtRows.filter((r) => r._dtText.includes(term))
                : this._dtRows.slice();

            // Optional host-provided extra filter (e.g. category-pill,
            // status-toggle filters on the Products list). Hosts that
            // define `_dtFilter(row)` get an extra .filter() pass
            // after the text-search. Hosts that don't are unaffected.
            if (typeof this._dtFilter === 'function') {
                matched = matched.filter((r) => this._dtFilter(r));
            }

            this._dtApplySort(matched);
            this._dtMatched = matched;

            const noPaging = this._dtPagingDisabled === true;
            const pageSize = noPaging ? Math.max(matched.length, 1) : this.pageSize;

            const totalPages = Math.max(1, Math.ceil(matched.length / pageSize));
            if (this.page > totalPages) this.page = totalPages;

            const start = noPaging ? 0 : (this.page - 1) * pageSize;
            const end   = noPaging ? matched.length : start + pageSize;

            const visibleArr = matched.slice(start, end);
            const visible    = new Set(visibleArr);
            this._dtRows.forEach((r) => {
                r.classList.toggle('dt-hidden', !visible.has(r));
            });

            // Reorder the DOM so visible rows appear in the correct order.
            // Runs when a sort is active OR when reverting to default order
            // after a previous sort (otherwise rows stay in the last sorted
            // order permanently once the user clicks "Default").
            if ((this.sortCol || this._dtEverSorted) && visibleArr.length > 1) {
                const parent = visibleArr[0].parentNode;
                if (parent) {
                    visibleArr.forEach((row) => parent.appendChild(row));
                }
            }


            // Explicit reactive writes — every template binding
            // depending on these picks up the new value on the next
            // Alpine flush.
            this.matchedCount = matched.length;
            this.totalPages   = totalPages;
            this.pageStart    = matched.length === 0 ? 0 : start + 1;
            this.pageEnd      = Math.min(end, matched.length);
            this.isFiltered   = term !== '';
            this.isPaginated  = totalPages > 1;
            this.isEmpty      = matched.length === 0;
            this.isPristine   = !this.isFiltered && !this.isPaginated;
            // First render done — the empty state may now reveal itself.
            this.dtReady      = true;

            // Belt-and-suspenders imperative toggle for the empty-state
            // and pager. `x-show` SHOULD also flip these via reactivity
            // when isEmpty / matchedCount change above — but across
            // AJAX-driven innerHTML swaps + initTree calls that has
            // proven unreliable. The straight DOM write here guarantees
            // the visible state always matches the freshly-computed row
            // count, regardless of Alpine's flush timing.
            const root    = this._dtRoot;
            const emptyEl = root?.querySelector('.dt-empty');
            const pagerEl = root?.querySelector('.dt-pager');
            if (emptyEl) emptyEl.style.display = this.isEmpty ? '' : 'none';
            if (pagerEl) pagerEl.style.display = (this.matchedCount > 0 && this.isTable !== false) ? '' : 'none';

            // Optional hook for the composing factory — e.g. summary cards
            // that must recompute from the matched (filtered, pre-paginate)
            // rows on every search/filter. No-op when not defined.
            if (typeof this._dtOnRender === 'function') {
                this._dtOnRender(matched);
            }
        },

        // ── Derived counts that aren't recomputed in _dtRender ─────
        get totalRows() { return this._dtRows.length; },

        // ── Pager actions ──────────────────────────────────────────
        prev()      { if (this.page > 1)               this.page -= 1; },
        next()      { if (this.page < this.totalPages) this.page += 1; },
        gotoPage(p) { this.page = Math.max(1, Math.min(this.totalPages, parseInt(p, 10) || 1)); },
        clearSearch() { this.search = ''; },

        /** Pick a page size from the custom pager dropdown + close it. */
        setPageSize(n) { this.pageSize = n; this.pageSizeOpen = false; },

        // ── Column sort ────────────────────────────────────────────
        /**
         * Cycle the sort on `col`. Clicking the same column toggles
         * asc → desc → off. Clicking a different column starts fresh
         * with asc. Off-state ("no sort") restores the server's
         * document order.
         */
        sortBy(col) {
            if (this.sortCol !== col) {
                this.sortCol = col;
                this.sortDir = 'asc';
                return;
            }
            if (this.sortDir === 'asc')  { this.sortDir = 'desc'; return; }
            if (this.sortDir === 'desc') { this.sortCol = null; this.sortDir = null; return; }
            this.sortDir = 'asc';
        },

        /** Helper for templates: returns 'asc', 'desc', or null for `col`. */
        sortDirFor(col) { return this.sortCol === col ? this.sortDir : null; },

        /**
         * Refresh the table rows via an AJAX HTML fetch — no full page reload.
         * Swaps only the rows container (tbody / ul) from a fresh server render,
         * then re-initialises Alpine on the new elements and re-runs the
         * client-side collect + render cycle so search/sort/page are preserved.
         */
        async dtRefresh() {
            if (this._dtRefreshing) return;
            this._dtRefreshing = true;
            try {
                const html = await window.posGetHtml(window.location.href);
                if (!html) { window.location.reload(); return; }

                const doc       = new DOMParser().parseFromString(html, 'text/html');
                const container = this._dtBody;
                if (!container) { window.location.reload(); return; }

                // Find the matching rows container in the fresh HTML.
                // If the table is now empty, freshRow will be null — create an
                // empty element of the same tag so the swap clears the container.
                const freshRow       = doc.querySelector('[data-dt-row]');
                const freshContainer = freshRow?.parentElement
                    ?? doc.createElement(container.tagName);

                container.innerHTML = freshContainer.innerHTML;

                // Cloudflare's email-obfuscation decode script won't re-run on
                // swapped-in nodes — decode them ourselves so emails don't show
                // as "[email protected]".
                decodeCfEmails(container);

                // Re-wire Alpine directives (@click, :class, x-show, …) on
                // the newly inserted row elements.
                if (window.Alpine?.initTree) window.Alpine.initTree(container);

                // Also refresh the toolbar title (row count) if it's present.
                const freshTitle = doc.querySelector('.dt-toolbar-title');
                const localTitle = this._dtRoot.querySelector('.dt-toolbar-title');
                if (freshTitle && localTitle) localTitle.innerHTML = freshTitle.innerHTML;

                // DOM is now in server order — reset sort-tracking so that
                // "Default order" doesn't incorrectly re-sort afterwards.
                this._dtEverSorted = false;
                this._dtCollect();
                this._dtRender();
            } catch {
                window.location.reload();
            } finally {
                this._dtRefreshing = false;
            }
        },

        /**
         * Set an explicit sort without the click-cycle behavior of `sortBy`.
         * Pass `(null, null)` to restore default (server) order.
         */
        setSort(col, dir) {
            this.sortCol = dir ? col : null;
            this.sortDir = dir || null;
        },

        /**
         * Sort `matched` in place by the active `sortCol` / `sortDir`.
         * Reads the value from `row.dataset['dt' + capitalCol]` (i.e.
         * `data-dt-name` → `dataset.dtName`). If both values parse as
         * numbers we sort numerically; otherwise we localeCompare.
         * When no sort is active but the DOM was previously reordered,
         * restores the original server-document order.
         */
        _dtApplySort(matched) {
            if (!this.sortCol || !this.sortDir) {
                // Restore server order when reverting from an active sort.
                if (this._dtEverSorted) {
                    matched.sort((a, b) => (a._dtOriginalIndex ?? 0) - (b._dtOriginalIndex ?? 0));
                }
                return;
            }
            this._dtEverSorted = true;
            const key  = this.sortCol;
            const attr = 'dt' + key.charAt(0).toUpperCase() + key.slice(1);
            const dir  = this.sortDir === 'desc' ? -1 : 1;

            matched.sort((a, b) => {
                const av = (a.dataset[attr] ?? '').trim();
                const bv = (b.dataset[attr] ?? '').trim();
                if (av === bv) return 0;

                const an = av === '' ? NaN : Number(av);
                const bn = bv === '' ? NaN : Number(bv);
                if (Number.isFinite(an) && Number.isFinite(bn)) {
                    return (an - bn) * dir;
                }
                return av.localeCompare(bv, undefined, { sensitivity: 'base' }) * dir;
            });
        },
    };
}

/**
 * Combine the data-table mixin with page-specific extras while preserving
 * every getter / setter descriptor (which `...spread` would silently strip).
 *
 * `extras` wins when keys collide. Define `init()` in `extras` and call
 * `this.initDataTable()` from it.
 */
export function composeDataTable(mixinOpts = {}, extras = {}) {
    const base = dataTableMixin(mixinOpts);
    for (const key of Object.getOwnPropertyNames(extras)) {
        Object.defineProperty(base, key, Object.getOwnPropertyDescriptor(extras, key));
    }
    return base;
}

/**
 * Standalone factory — for pages that just need a data table. Equivalent
 * to `composeDataTable(opts, { init() { this.initDataTable(); } })`.
 */
export function dataTable(opts = {}) {
    return composeDataTable(opts, {
        init() { this.initDataTable(); },
    });
}
