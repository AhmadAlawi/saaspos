import { composeDataTableServer } from './data-table-server.js';
import { posPost, applyValidationErrors } from '../lib/http.js';

/**
 * Alpine factory for the Customer Groups admin page.
 *
 * Master-detail picklist (list on the left, sticky editor on the right)
 * composed with `composeDataTable` so the list gets the system-wide
 * client-side pagination + search + per-page picker. Saves and deletes
 * flow through XHR; the server returns the freshly-rendered list HTML
 * and the rows snapshot, and we swap the tbody in place + refresh
 * `rowsById`. No page reloads.
 *
 * Mirrors `reasonsPage` exactly (same picklist shape) — the only
 * differences are the field set (no `code`, no `sort_order`, has
 * `default_discount_percent` instead) and the model name in copy.
 */
export function customerGroupsPage({
    endpoint = null,
    perPage = 25,
    total = 0,
    page = 1,
    totalPages = 1,
    storeUrl,
    updateUrlTemplate,
    deleteUrlTemplate,
    rows = [],
    initial = null,
} = {}) {
    const blank = () => ({
        id:        null,
        name:      '',
        discount:  '',
        is_active: true,
    });

    return composeDataTableServer(
        {
            endpoint,
            initialPageSize: perPage,
        },
        {
            mode:        'idle',       // 'idle' | 'edit' | 'new'
            form:        blank(),
            rowsById:    {},
            submitting:  false,
            togglingIds: [],
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

            get rowMeta() {
                return this.isEdit ? this.rowsById[this.form.id] ?? null : null;
            },

            /** The mixin manages the paginated `.cg-list`; the editor reads
             *  `rowsById` (seeded full) so it can open any group. */
            _dtContainer() {
                return this._dtRoot?.querySelector('.cg-list') ?? null;
            },

            init() {
                this._rootEl  = this.$el;
                this.rowsById = Object.fromEntries(rows.map((r) => [r.id, r]));

                if (initial && typeof initial === 'object' && initial.mode) {
                    this.mode = initial.mode;
                    this.form = {
                        id:        initial.id ?? null,
                        name:      initial.name ?? '',
                        discount:  initial.discount ?? '',
                        is_active: initial.is_active !== false,
                    };
                }

                this.initDataTableServer({ total, page, totalPages, perPage });
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
                this.form = {
                    id:        row.id,
                    name:      row.name ?? '',
                    discount:  row.default_discount_percent ?? '',
                    is_active: row.is_active !== false,
                };
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

            /** Flip a row's is_active via AJAX without opening the editor. */
            async toggleActive(id) {
                const row = this.rowsById[id];
                if (!row || this.togglingIds.includes(id)) return;
                const next = !row.is_active;

                row.is_active = next;
                this.togglingIds = [...this.togglingIds, id];

                const fd = new FormData();
                fd.append('_token',                   document.querySelector('meta[name="csrf-token"]')?.content || '');
                fd.append('_method',                  'PATCH');
                fd.append('name',                     row.name ?? '');
                fd.append('default_discount_percent', row.default_discount_percent ?? '');
                fd.append('is_active',                next ? '1' : '0');

                const url = updateUrlTemplate.replace('__ID__', id);
                const data = await this._send(url, fd);

                this.togglingIds = this.togglingIds.filter((x) => x !== id);

                if (!data) { row.is_active = !next; return; }

                this._applyServerResponse(data);
                if (data.message) {
                    this.$store.toasts.push({ type: 'success', message: data.message });
                }
            },

            confirmDeleteRow(id) {
                const row  = this.rowsById[id];
                const name = row?.name ?? 'this group';
                this.$store.confirm.show({
                    title:        `Delete "${name}"?`,
                    intent:       'danger',
                    confirmLabel: 'Delete group',
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
                // Reload the current page from the server (was: swap list_html).
                this.dtInvalidate();
                this._dtLoad({ bustCache: true });
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
