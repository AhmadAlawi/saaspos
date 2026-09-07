import { composeDataTableServer } from './data-table-server.js';
import { posPost, applyValidationErrors } from '../lib/http.js';

/**
 * Alpine factory for the Stock Adjustment Reasons admin page.
 *
 * Mirrors `drugSchedulesPage` shape — flat list + sticky editor + AJAX
 * swap on save / delete — but composed with `composeDataTable` so the
 * list gets the same client-side pagination + search + per-page picker
 * as every other admin list (Categories, Products, Brands, etc.).
 * The data-table mixin handles row collection from the DOM, so adding
 * or removing reasons via AJAX is reflected after a `_dtCollect()`.
 */
export function reasonsPage({
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
        id:         null,
        code:       '',
        name:       '',
        sort_order: 0,
        is_active:  true,
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
            /** Per-field validation errors from the last 422 — keyed by
             *  field name. Cleared when the user re-opens the editor or
             *  starts typing in the offending field. */
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

            /** The mixin manages the paginated `.rsn-list`; the editor reads
             *  `rowsById` (seeded full) so it can open any reason. */
            _dtContainer() {
                return this._dtRoot?.querySelector('.rsn-list') ?? null;
            },

            init() {
                this._rootEl  = this.$el;
                this.rowsById = Object.fromEntries(rows.map((r) => [r.id, r]));

                if (initial && typeof initial === 'object' && initial.mode) {
                    this.mode = initial.mode;
                    this.form = {
                        id:         initial.id ?? null,
                        code:       initial.code ?? '',
                        name:       initial.name ?? '',
                        sort_order: initial.sort_order ?? 0,
                        is_active:  initial.is_active !== false,
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
                    id:         row.id,
                    code:       row.code ?? '',
                    name:       row.name ?? '',
                    sort_order: row.sort_order ?? 0,
                    is_active:  row.is_active !== false,
                };
                this.fieldErrors = {};
            },

            closeEditor() {
                this.mode = 'idle';
                this.form = blank();
                this.fieldErrors = {};
            },

            /** Drop the error for a field as soon as the user starts fixing it. */
            clearFieldError(field) {
                if (this.fieldErrors[field]) {
                    const next = { ...this.fieldErrors };
                    delete next[field];
                    this.fieldErrors = next;
                }
            },

            togglingIds: [],

            /** Flip is_active without opening the editor. Re-sends the
             *  row's existing code/name/sort_order with the toggled
             *  is_active over the standard update endpoint. */
            async toggleActive(id) {
                if (this.togglingIds.includes(id)) return;
                const row = this.rowsById[id];
                if (!row) return;
                const next = !row.is_active;

                row.is_active = next;
                this.togglingIds = [...this.togglingIds, id];

                const fd = new FormData();
                fd.append('_token',     document.querySelector('meta[name="csrf-token"]')?.content || '');
                fd.append('_method',    'PATCH');
                fd.append('code',       row.code ?? '');
                fd.append('name',       row.name ?? '');
                fd.append('sort_order', row.sort_order ?? 0);
                fd.append('is_active',  next ? '1' : '0');

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

            /**
             * Delete from a list row (no editor open). Hands the id to the
             * confirm dialog, then deletes via AJAX.
             */
            confirmDeleteRow(id) {
                const row = this.rowsById[id];
                const name = row?.name ?? 'this reason';
                this.$store.confirm.show({
                    title:        `Delete "${name}"?`,
                    message:      'This removes it from future adjustments. History keeps the link.',
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

            /** Delete from inside the editor. */
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

            /**
             * Swap the freshly-rendered tbody HTML in, re-init Alpine on
             * the new rows, refresh `rowsById`, then re-collect + re-render
             * the data-table mixin so pagination / search reflect the
             * updated row set.
             */
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
                    // 5xx + 419 + 401 already surfaced globally by lib/http.js interceptors.
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
