import { composeDataTable } from './data-table.js';
import { posPost, applyValidationErrors } from '../lib/http.js';

/**
 * Alpine factory for the Return Reasons admin page.
 *
 * Forked from `reasonsPage` (stock-adjustment reasons) because the
 * shape of the form differs: return reasons carry two extra booleans
 * (`default_restock`, `requires_permission`) that need to round-trip
 * through `openEdit` / `toggleActive`. The rest of the page (list,
 * sticky editor, AJAX swap, data-table mixin) is identical.
 */
export function returnReasonsPage({
    storeUrl,
    updateUrlTemplate,
    deleteUrlTemplate,
    rows = [],
    initial = null,
} = {}) {
    const blank = () => ({
        id:                  null,
        code:                '',
        name:                '',
        sort_order:          0,
        default_restock:     true,
        requires_permission: false,
        is_active:           true,
    });

    return composeDataTable(
        {
            rowsSelector:       'tr[data-dt-row]',
            searchableSelector: null,
            initialPageSize:    25,
        },
        {
            mode:        'idle',
            form:        blank(),
            rowsById:    {},
            submitting:  false,
            fieldErrors: {},

            _rootEl: null,

            get isIdle() { return this.mode === 'idle'; },
            get isEdit() { return this.mode === 'edit' && !!this.form.id; },
            get isNew()  { return this.mode === 'new'; },

            get formAction() {
                return this.isEdit
                    ? updateUrlTemplate.replace('__ID__', this.form.id)
                    : storeUrl;
            },

            get deleteAction() {
                return this.isEdit
                    ? deleteUrlTemplate.replace('__ID__', this.form.id)
                    : '';
            },

            get rowMeta() {
                return this.isEdit ? this.rowsById[this.form.id] ?? null : null;
            },

            init() {
                this._rootEl  = this.$el;
                this.rowsById = Object.fromEntries(rows.map((r) => [r.id, r]));

                if (initial && typeof initial === 'object' && initial.mode) {
                    this.mode = initial.mode;
                    this.form = this._formFromRow(initial);
                }

                this.initDataTable();
            },

            /** Build a form-shaped object from any source row — used by
             *  both the initial hydrate and openEdit so the two extra
             *  booleans land consistently. */
            _formFromRow(r) {
                return {
                    id:                  r.id ?? null,
                    code:                r.code ?? '',
                    name:                r.name ?? '',
                    sort_order:          r.sort_order ?? 0,
                    default_restock:     r.default_restock !== false,
                    requires_permission: r.requires_permission === true,
                    is_active:           r.is_active !== false,
                };
            },

            openNew() {
                this.mode = 'new';
                this.form = blank();
                this.fieldErrors = {};
            },

            openEdit(id) {
                const row = this.rowsById[id];
                if (!row) return;
                this.mode = 'edit';
                this.form = this._formFromRow(row);
                this.fieldErrors = {};
            },

            closeEditor() {
                this.mode = 'idle';
                this.form = blank();
                this.fieldErrors = {};
            },

            clearFieldError(field) {
                if (this.fieldErrors[field]) {
                    const next = { ...this.fieldErrors };
                    delete next[field];
                    this.fieldErrors = next;
                }
            },

            togglingIds: [],

            async toggleActive(id) {
                if (this.togglingIds.includes(id)) return;
                const row = this.rowsById[id];
                if (!row) return;
                const next = !row.is_active;

                row.is_active = next;
                this.togglingIds = [...this.togglingIds, id];

                const fd = new FormData();
                fd.append('_token',              document.querySelector('meta[name="csrf-token"]')?.content || '');
                fd.append('_method',             'PATCH');
                fd.append('code',                row.code ?? '');
                fd.append('name',                row.name ?? '');
                fd.append('sort_order',          row.sort_order ?? 0);
                fd.append('default_restock',     row.default_restock ? '1' : '0');
                fd.append('requires_permission', row.requires_permission ? '1' : '0');
                fd.append('is_active',           next ? '1' : '0');

                const url  = updateUrlTemplate.replace('__ID__', id);
                const data = await this._send(url, fd);

                this.togglingIds = this.togglingIds.filter((x) => x !== id);

                if (!data) {
                    row.is_active = !next;
                    return;
                }
                this._applyServerResponse(data);
                if (data.message) {
                    this.$store.toasts.push({ type: 'success', message: data.message });
                }
            },

            confirmDeleteRow(id) {
                const row = this.rowsById[id];
                const name = row?.name ?? 'this reason';
                this.$store.confirm.show({
                    title:        `Delete "${name}"?`,
                    message:      'Past refunds keep the name on their receipts. New refunds won\'t see this reason in the picklist.',
                    intent:       'danger',
                    confirmLabel: 'Delete reason',
                    cancelLabel:  'Cancel',
                    onConfirm: async () => {
                        const url = deleteUrlTemplate.replace('__ID__', id);
                        const data = await this._send(url, this._formWithMethod('DELETE'));
                        if (!data) return;
                        this._applyServerResponse(data);
                        if (this.form.id === id) this.closeEditor();
                        if (data.message) {
                            this.$store.toasts.push({ type: 'success', message: data.message });
                        }
                    },
                });
            },

            confirmDelete() {
                if (!this.isEdit) return;
                this.confirmDeleteRow(this.form.id);
            },

            async submitEditor(evt) {
                if (this.submitting) return;
                this.submitting = true;

                this.fieldErrors = {};
                const data = await this._send(this.formAction, new FormData(evt.target), evt.target);
                if (!data) { this.submitting = false; return; }

                this._applyServerResponse(data);

                if (data.message) {
                    this.$store.toasts.push({ type: 'success', message: data.message });
                }
                if (data.id) this.openEdit(data.id);
                this.submitting = false;
            },

            _applyServerResponse(data) {
                if (Array.isArray(data.rows)) {
                    this.rowsById = Object.fromEntries(data.rows.map((r) => [r.id, r]));
                }
                if (data.list_html) {
                    const tbody = this._rootEl.querySelector('.rsn-list');
                    if (tbody) {
                        tbody.replaceChildren();
                        tbody.insertAdjacentHTML('beforeend', data.list_html);
                        if (window.Alpine) window.Alpine.initTree(tbody);
                    }
                }
                this._dtCollect();
                this._dtRender();
                this.$nextTick(() => { this._dtCollect(); this._dtRender(); });
            },

            async _send(url, body, formEl = null) {
                try {
                    const { data } = await posPost(url, body);
                    return data;
                } catch (e) {
                    if (e?.status === 422) {
                        const errs = e.errors ?? {};
                        this.fieldErrors = Object.fromEntries(
                            Object.entries(errs).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])
                        );
                        const messages = Object.values(errs).flat().filter(Boolean);
                        if (messages.length) {
                            this.$store.toasts.push({
                                type:    'error',
                                title:   messages.length === 1 ? 'Please review the form' : 'Please fix the errors below',
                                messages,
                            });
                        } else {
                            this.$store.toasts.push({ type: 'error', message: e?.message || 'Request failed.' });
                        }
                        if (formEl) applyValidationErrors(formEl, errs);
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
        },
    );
}
