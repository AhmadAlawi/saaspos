import { composeDataTableServer } from './data-table-server.js';
import { posPatch } from '../lib/http.js';

/**
 * Alpine factory for the Customers index page.
 *
 * Server-paginated (see `data-table-server.js`). The server renders only the
 * first page; search, the status/group filters, sort and paging each fetch one
 * page of rows from `/admin/customers/rows`. Composes the server mixin with
 * row-state tracking + an AJAX `toggleActive` so the inline status toggle works
 * without leaving the page.
 *
 * @param {{
 *   endpoint: string, toggleUrlTemplate: string,
 *   perPage: number, total: number, page: number, totalPages: number,
 * }} config
 */
export function customersIndexPage(config = {}) {
    const toggleUrlTemplate = config.toggleUrlTemplate ?? '';

    return composeDataTableServer(
        {
            endpoint:        config.endpoint,
            initialPageSize: config.perPage ?? 25,
        },
        {
            // ── Filter state ───────────────────────────────────────
            statusFilter: 'all',   // 'all' | 'active' | 'inactive'
            groupFilter:  'all',   // 'all' | customer-group id (as string)

            // ── Row-level quick action ─────────────────────────────
            /** Mirror of the visible row data the template reads (`is_active`),
             *  keyed by id. Rebuilt from the server's markup after every swap. */
            rowState:    {},
            togglingIds: [],

            get hasActiveFilters() {
                return this.statusFilter !== 'all'
                    || this.groupFilter  !== 'all'
                    || (this.search ?? '').trim() !== '';
            },

            resetFilters() {
                this.statusFilter = 'all';
                this.groupFilter  = 'all';
                this.search       = '';
            },

            init() {
                this.initDataTableServer({
                    total:      config.total ?? 0,
                    page:       config.page ?? 1,
                    totalPages: config.totalPages ?? 1,
                    perPage:    config.perPage ?? 25,
                });

                // Seed rowState from the first page, which the server rendered
                // inline — no fetch needed on boot.
                this._syncRowState(this._dtContainer());

                // Every filter change restarts at page 1. `_dtLoad` collapses
                // the burst these fire during `resetFilters()` into one request.
                ['statusFilter', 'groupFilter'].forEach((prop) =>
                    this.$watch(prop, () => this._dtGo(1)));
            },

            // ── data-table-server host contract ────────────────────
            _dtContainer() {
                return this._dtRoot?.querySelector('[data-dt-rows="table"]') ?? null;
            },

            _dtExtraParams() {
                return {
                    status:   this.statusFilter,
                    group_id: this.groupFilter,
                };
            },

            _dtAfterSwap(container) {
                this._syncRowState(container);
            },

            _dtOnMeta(meta) {
                if (meta.summary) this._writeSummary(meta.summary);
            },

            // ── Summary cards ──────────────────────────────────────
            /**
             * The cards describe the *filtered* set, so they arrive display-ready
             * from the server with every rows() response rather than being
             * counted from the visible rows (which are now only one page).
             */
            _writeSummary(summary) {
                const root = this._dtRoot;
                if (!root) return;
                Object.entries(summary).forEach(([key, value]) => {
                    root.querySelectorAll(`[data-card-value="${key}"]`)
                        .forEach((el) => { el.textContent = value; });
                });
            },

            // ── Row state ──────────────────────────────────────────
            /** Rebuild `rowState` from the server's `data-dt-*` attributes.
             *  Merged, not replaced, so it survives across swaps. */
            _syncRowState(container) {
                if (!container) return;
                const fresh = {};
                container.querySelectorAll('[data-dt-row]').forEach((row) => {
                    const id = parseInt(row.dataset.dtId, 10);
                    if (!Number.isFinite(id)) return;
                    fresh[id] = { is_active: row.dataset.dtActive === '1' };
                });
                Object.assign(this.rowState, fresh);
            },

            // ── Quick toggle ───────────────────────────────────────
            async toggleActive(id) {
                if (this.togglingIds.includes(id)) return;
                if (!this.rowState[id]) return;

                const current = !!this.rowState[id].is_active;
                const next    = !current;

                // Optimistic flip — Alpine repaints instantly.
                this.rowState[id] = { ...this.rowState[id], is_active: next };
                this.togglingIds = [...this.togglingIds, id];

                const url = toggleUrlTemplate.replace('__ID__', id);
                let data;
                try {
                    ({ data } = await posPatch(url));
                } catch (e) {
                    this.rowState[id] = { ...this.rowState[id], is_active: current };
                    this.togglingIds = this.togglingIds.filter((x) => x !== id);
                    if (e?.status === undefined) {
                        this.$store.toasts.push({ type: 'error', message: 'Network error.' });
                    } else {
                        const msg = e?.message
                            || e?.errors?._action?.[0]
                            || 'Could not toggle status.';
                        this.$store.toasts.push({ type: 'error', message: msg });
                    }
                    return;
                }

                this.togglingIds = this.togglingIds.filter((x) => x !== id);

                // Reconcile with the authoritative server value.
                if (typeof data?.is_active === 'boolean') {
                    this.rowState[id] = { ...this.rowState[id], is_active: data.is_active };
                }

                if (data?.message) {
                    this.$store.toasts.push({ type: 'success', message: data.message });
                }

                // Cached pages now describe a stale flag.
                this.dtInvalidate();

                // With a status filter active the row may no longer belong on this
                // page — reload so it leaves, and so the summary comes from the
                // server's count rather than a stale card.
                if (this.statusFilter !== 'all') {
                    this._dtLoad({ bustCache: true });
                }
            },
        },
    );
}
