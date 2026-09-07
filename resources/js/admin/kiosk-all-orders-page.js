import { composeDataTableServer } from './data-table-server.js';

/**
 * Kiosk orders → "All orders" tab.
 *
 * A separate Alpine scope from `kioskOrdersPage`, nested inside it: that
 * component's dataTableServer is already bound to the PENDING endpoint, and one
 * scope can't drive two independent paginated tables.
 *
 * Read-only history. The two live queues only ever hold open work — an order
 * leaves them for good once it's paid, collected or rejected — so this is where
 * staff find one again. Server-paginated and date-windowed (defaults to today);
 * the filter form is Alpine self-managed exactly like the sales list.
 *
 * @param {{ endpoint: string, perPage: number, total: number, page: number, totalPages: number }} config
 */
export function kioskAllOrdersPage(config = {}) {
    return composeDataTableServer(
        {
            endpoint:        config.endpoint,
            initialPageSize: config.perPage ?? 25,
        },
        {
            init() {
                // Cache the form BEFORE initDataTableServer so the mixin's
                // first param-key (its no-op guard) is computed against the
                // filter values the server already rendered — no boot fetch.
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

            /** Every filter rides along as a query param, read straight from
             *  the form so the date pickers work unchanged. */
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

            /** Any filter change restarts at page 1. */
            applyFilters() {
                this._dtGo(1);
            },

            /**
             * Clear every control, then reload page 1.
             *
             * Note the dates are cleared to empty rather than back to today:
             * the server re-defaults a missing from/to to today anyway, so this
             * lands on the same rows without the client having to know the
             * store's date.
             */
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
