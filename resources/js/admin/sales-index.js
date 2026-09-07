import { composeDataTableServer } from './data-table-server.js';

/**
 * Sales list page — server-paginated (see `data-table-server.js`).
 *
 * The filter form is Alpine self-managed: `applyFilters()` reads the whole
 * form and fetches page 1 from `/admin/sales/rows`, so inv-filter-ajax leaves
 * it alone. Read-only rows (row links to the sale detail); the void/refund
 * actions in each row's menu post + redirect, so they need no special handling.
 *
 * @param {{ endpoint: string, perPage: number, total: number, page: number, totalPages: number }} config
 */
export function salesIndexPage(config = {}) {
    return composeDataTableServer(
        {
            endpoint:        config.endpoint,
            initialPageSize: config.perPage ?? 25,
        },
        {
            init() {
                // Cache the form BEFORE initDataTableServer so the mixin's first
                // param-key (its no-op guard) is computed against the real filter
                // values the server already rendered — no redundant boot fetch.
                this._filterForm = this.$el.querySelector('form.inv-filter');

                this.initDataTableServer({
                    total:      config.total ?? 0,
                    page:       config.page ?? 1,
                    totalPages: config.totalPages ?? 1,
                    perPage:    config.perPage ?? 25,
                });
            },

            _dtContainer() {
                return this._dtRoot?.querySelector('[data-dt-rows="table"]') ?? null;
            },

            /** Every filter rides along as a query param, read straight from the
             *  form so the remote customer picker + date pickers work unchanged. */
            _dtExtraParams() {
                const params = {};
                if (this._filterForm) {
                    new FormData(this._filterForm).forEach((v, k) => {
                        const s = (v ?? '').toString().trim();
                        if (s !== '') params[k] = s;
                    });
                }
                return params;
            },

            _dtOnMeta(meta) {
                if (meta.summary) this._writeSummary(meta.summary);
            },

            /** The cards describe the FULL filtered set, so they arrive
             *  display-ready with every rows() response. */
            _writeSummary(summary) {
                const root = this._dtRoot;
                if (!root) return;
                Object.entries(summary).forEach(([key, value]) => {
                    root.querySelectorAll(`[data-card-value="${key}"]`)
                        .forEach((el) => { el.textContent = value; });
                });
            },

            /** Any filter change restarts at page 1. */
            applyFilters() {
                this._dtGo(1);
            },

            /** Clear every control back to its default, then reload page 1. */
            resetFilters() {
                const form = this._filterForm;
                if (form) {
                    form.querySelectorAll('select').forEach((sel) => {
                        if (sel.tomselect) sel.tomselect.setValue('', true);
                        else sel.value = '';
                    });
                    form.querySelectorAll('input').forEach((inp) => {
                        if (inp.type === 'hidden') return;
                        if (inp._flatpickr) inp._flatpickr.clear();
                        else inp.value = '';
                    });
                }
                this._dtGo(1);
            },
        },
    );
}
