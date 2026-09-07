import { composeDataTableServer } from './data-table-server.js';

/**
 * Minimal server-paginated factory for plain list tables that use the
 * `<x-admin.data-table>` component's built-in search box + sortable headers and
 * have NO extra filter form or summary cards (e.g. Users, Roles).
 *
 * The component's search binds `x-model="search"` and the sortable headers call
 * `sortBy(col)` — both already live on the server mixin, which turns them into
 * `?q=` / `?sort=&dir=` query params. So all this factory adds is the endpoint
 * wiring and the rows container.
 *
 * @param {{ endpoint: string, perPage: number, total: number, page: number, totalPages: number }} config
 */
export function serverTablePage(config = {}) {
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
        },
    );
}
