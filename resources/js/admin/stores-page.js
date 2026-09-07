import { composeDataTable } from './data-table.js';
import { posPatch } from '../lib/http.js';

/**
 * Alpine factory for the Stores index page.
 *
 * Mirrors the Products table interaction model: search + sort + paginate
 * via the shared data-table mixin, a reactive per-row `is_active` toggle
 * (optimistic AJAX), row-click-to-edit, and a confirm-gated delete.
 */
export function storesPage() {
    return composeDataTable(
        {
            rowsSelector:       'tr[data-dt-row]',
            searchableSelector: '.store-row-name, .mono',
            initialPageSize:    25,
        },
        {
            togglingIds: [],
            activeFilter: 'all',

            get hasActiveFilters() {
                return this.activeFilter !== 'all'
                    || (this.search ?? '').trim() !== '';
            },

            resetFilters() {
                this.activeFilter = 'all';
                this.search       = '';
            },

            // Reactive per-row state ({ is_active }). The DOM dataset is
            // not reactive, so bindings read this map instead.
            rowState: {},

            get isTable() { return true; },

            init() {
                this.initDataTable();

                this._dtRoot?.querySelectorAll('tr[data-dt-store-id]').forEach((row) => {
                    const id = parseInt(row.dataset.dtStoreId, 10);
                    if (!Number.isFinite(id)) return;
                    this.rowState[id] = { is_active: row.dataset.dtActive === '1' };
                });

                this.$watch('activeFilter', () => { this.page = 1; this._dtRender(); });
            },

            _dtFilter(row) {
                if (this.activeFilter === 'all') return true;
                const id    = parseInt(row.dataset.dtStoreId, 10);
                const state = this.rowState[id];
                if (this.activeFilter === 'active'   && !state?.is_active) return false;
                if (this.activeFilter === 'inactive' && !!state?.is_active) return false;
                return true;
            },

            /** Edit-on-row-click, but let action buttons opt out via @click.stop. */
            openEdit(id) {
                window.location.href = `/admin/stores/${id}/edit`;
            },

            /**
             * Flip is_active without leaving the page. Optimistic — mutate
             * the reactive rowState first, revert on failure. The current
             * store can't be deactivated from here (guarded in the view).
             */
            async toggleActive(id) {
                if (this.togglingIds.includes(id)) return;
                if (!this.rowState[id]) return;

                const current = !!this.rowState[id].is_active;
                const next    = !current;

                this.rowState[id].is_active = next;
                this.togglingIds = [...this.togglingIds, id];

                let data;
                try {
                    ({ data } = await posPatch(`/admin/stores/${id}/toggle`, { is_active: next ? '1' : '0' }));
                } catch (e) {
                    this.rowState[id].is_active = current;
                    this.togglingIds = this.togglingIds.filter((x) => x !== id);
                    if (e?.status === undefined) {
                        this.$store.toasts?.push({ type: 'error', message: 'Network error.' });
                    } else {
                        // Surface the server's reason (e.g. "can't deactivate the default / last store").
                        this.$store.toasts?.push({ type: 'error', message: e?.message || 'Could not update store.' });
                    }
                    return;
                }

                this.togglingIds = this.togglingIds.filter((x) => x !== id);

                if (data?.message) {
                    this.$store.toasts?.push({ type: 'success', message: data.message });
                }
            },
        },
    );
}
