import { composeDataTableServer } from './data-table-server.js';

/**
 * Shifts list page — server-paginated (see `data-table-server.js`).
 *
 * Read-only list: the whole row links to the shift detail, so there are no
 * inline mutations. The only extra job is keeping the summary cards (Total
 * shifts · Open · With variance) in sync — they arrive display-ready in every
 * `rows()` response's `summary` payload and get written into the
 * `[data-card-value]` spans the `<x-admin.summary-cards>` component emits.
 *
 * @param {{ endpoint: string, perPage: number, total: number, page: number, totalPages: number }} config
 */
export function shiftsIndexPage(config = {}) {
    return composeDataTableServer(
        {
            endpoint:        config.endpoint,
            initialPageSize: config.perPage ?? 25,
        },
        {
            init() {
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

            _dtOnMeta(meta) {
                if (meta.summary) this._writeSummary(meta.summary);
            },

            _writeSummary(summary) {
                const root = this._dtRoot;
                if (!root) return;
                Object.entries(summary).forEach(([key, value]) => {
                    root.querySelectorAll(`[data-card-value="${key}"]`)
                        .forEach((el) => { el.textContent = value; });
                });
            },
        },
    );
}
