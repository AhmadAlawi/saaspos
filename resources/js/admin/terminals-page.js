import { composeDataTableServer } from './data-table-server.js';
import { posPost } from '../lib/http.js';

/**
 * Alpine factory for the Terminals LIST page.
 *
 * Server-paginated (see `data-table-server.js`). The add/edit editor lives on
 * its own full-width page ({@see terminalForm}); this component owns only the
 * list: the searchable/paged `<ul>`, the per-row active toggle, binding this
 * workstation to a terminal, launching the kiosk, and delete.
 *
 * `rowsById` holds the rich per-row data (name/code/is_active/…) the row
 * actions read. It's seeded from the inline first page and merged from every
 * `rows()` response's `rows` payload — so it always covers the visible page,
 * which is all the row actions ever touch.
 *
 * @param {{
 *   endpoint: string, perPage: number, total: number, page: number, totalPages: number,
 *   updateUrlTemplate: string, deleteUrlTemplate: string, selectUrlTemplate: string,
 *   clearSelectionUrl: string, kioskUrl: string,
 *   activeTerminalId: number|null, rows: Array<object>,
 * }} config
 */
export function terminalsPage({
    endpoint,
    perPage = 25,
    total = 0,
    page = 1,
    totalPages = 1,
    updateUrlTemplate,
    deleteUrlTemplate,
    selectUrlTemplate,
    clearSelectionUrl,
    kioskUrl,
    activeTerminalId = null,
    rows = [],
} = {}) {
    return composeDataTableServer(
        {
            endpoint,
            initialPageSize: perPage,
        },
        {
            rowsById:    {},
            togglingIds: [],

            // Which terminal THIS browser is bound to (pos_terminal_id cookie).
            activeTerminalId,
            selectingId: null,

            init() {
                this.initDataTableServer({ total, page, totalPages, perPage });
                this._mergeRows(rows);
            },

            // ── data-table-server host contract ────────────────────
            _dtContainer() {
                return this._dtRoot?.querySelector('.term-list') ?? null;
            },

            /** Every rows() response carries the rich per-row payload — merge it
             *  so `rowsById` covers the freshly-swapped page. */
            _dtOnMeta(meta) {
                if (Array.isArray(meta.rows)) this._mergeRows(meta.rows);
            },

            _mergeRows(list) {
                if (!Array.isArray(list)) return;
                const fresh = {};
                list.forEach((r) => { if (r && r.id != null) fresh[r.id] = r; });
                this.rowsById = { ...this.rowsById, ...fresh };
            },

            // ── Row actions ────────────────────────────────────────
            async toggleActive(id) {
                const row = this.rowsById[id];
                if (!row || this.togglingIds.includes(id)) return;
                const next = !row.is_active;

                row.is_active    = next;   // optimistic
                this.togglingIds = [...this.togglingIds, id];

                const fd = new FormData();
                fd.append('_token',     document.querySelector('meta[name="csrf-token"]')?.content || '');
                fd.append('_method',    'PATCH');
                fd.append('name',       row.name ?? '');
                // Resend the existing code so the server doesn't regenerate a
                // fresh one (a blank code auto-derives on persist).
                fd.append('code',       row.code ?? '');
                fd.append('is_active',  next ? '1' : '0');
                fd.append('editing_id', id);

                const data = await this._send(updateUrlTemplate.replace('__ID__', id), fd);
                this.togglingIds = this.togglingIds.filter((x) => x !== id);

                if (!data) { row.is_active = !next; return; }

                // Reconcile with the server's authoritative rows, then drop any
                // cached pages that now describe the stale flag.
                this._mergeRows(data.rows);
                this.dtInvalidate();

                if (data.message) {
                    this.$store.toasts.push({ type: 'success', message: data.message });
                }
            },

            /** Bind this workstation to a terminal (server sets the cookie). */
            async selectTerminal(id) {
                if (this.selectingId) return;
                const row = this.rowsById[id];
                if (!row || row.is_active === false) return;

                this.selectingId = id;
                const data = await this._send(selectUrlTemplate.replace('__ID__', id), this._tokenForm());
                this.selectingId = null;
                if (!data) return;

                this.activeTerminalId = data.selected_id ?? id;
                if (data.message) {
                    this.$store.toasts.push({ type: 'success', message: data.message });
                }
            },

            /**
             * Launch the customer kiosk for a specific terminal. The kiosk has
             * no per-terminal URL — KioskController reads current_terminal() —
             * so this device must be bound to the terminal first, then opened.
             * The tab is pre-opened synchronously to survive the popup blocker.
             */
            async openKiosk(id) {
                const row = this.rowsById[id];
                if (!row || row.is_active === false || this.selectingId) return;

                const win = window.open('', '_blank');

                if (this.activeTerminalId !== id) {
                    await this.selectTerminal(id);
                    // Binding failed (inactive / error) — don't strand a blank tab.
                    if (this.activeTerminalId !== id) { win?.close(); return; }
                }

                if (win) win.location = kioskUrl;
                else window.location.assign(kioskUrl);   // popup blocked → same tab
            },

            /** Unbind this workstation from any terminal (clears the cookie). */
            async clearSelection() {
                const data = await this._send(clearSelectionUrl, this._tokenForm());
                if (!data) return;

                this.activeTerminalId = null;
                if (data.message) {
                    this.$store.toasts.push({ type: 'success', message: data.message });
                }
            },

            confirmDeleteRow(id) {
                const name = this.rowsById[id]?.name ?? 'this terminal';
                this.$store.confirm.show({
                    title:        `Delete "${name}"?`,
                    message:      'Past sales and shifts keep their history but lose the terminal label. This cannot be undone.',
                    intent:       'danger',
                    confirmLabel: 'Delete terminal',
                    cancelLabel:  'Cancel',
                    onConfirm: async () => {
                        const data = await this._send(deleteUrlTemplate.replace('__ID__', id), this._formWithMethod('DELETE'));
                        if (!data) return;

                        // Reload the current page from the server so the deleted
                        // row leaves and the pager re-counts.
                        this.dtInvalidate();
                        await this._dtLoad({ bustCache: true });

                        if (data.message) {
                            this.$store.toasts.push({ type: 'success', message: data.message });
                        }
                    },
                });
            },

            async _send(url, body) {
                try {
                    const { data } = await posPost(url, body);
                    return data;
                } catch (e) {
                    if (e?.status === 422) {
                        this.$store.toasts.push({ type: 'error', message: e?.message || 'Request failed.' });
                    }
                    return null;
                }
            },

            _formWithMethod(method) {
                const fd = new FormData();
                fd.append('_token',  document.querySelector('meta[name="csrf-token"]')?.content || '');
                fd.append('_method', method);
                return fd;
            },

            _tokenForm() {
                const fd = new FormData();
                fd.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
                return fd;
            },
        },
    );
}
