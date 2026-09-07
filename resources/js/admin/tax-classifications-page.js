import { composeDataTable } from './data-table.js';
import { posPost, posGet, applyValidationErrors } from '../lib/http.js';

export function taxClassificationsPage({
    storeUrl,
    updateUrlTemplate,
    deleteUrlTemplate,
    deactivateCheckUrlTemplate,
    rows = [],
    initial = null,
} = {}) {
    const blank = () => ({
        id:          null,
        name:        '',
        slug:        '',
        description: '',
        sort_order:  '0',
        is_active:   true,
    });

    return composeDataTable(
        {
            rowsSelector:       'li[data-dt-row]',
            searchableSelector: '.unit-row-name',
            initialPageSize:    25,
        },
        {
            mode:        'idle',
            form:        blank(),
            rowsById:    {},
            submitting:  false,
            togglingIds: [],
            fieldErrors: {},

            _rootEl: null,
            _slugManuallyEdited: false,

            get isIdle() { return this.mode === 'idle'; },
            get isEdit() { return this.mode === 'edit' && !!this.form.id; },
            get isNew()  { return this.mode === 'new'; },
            get isTable() { return true; },

            get formAction() {
                return this.isEdit
                    ? updateUrlTemplate.replace('__ID__', this.form.id)
                    : storeUrl;
            },

            get deleteAction() {
                return this.isEdit ? deleteUrlTemplate.replace('__ID__', this.form.id) : '';
            },

            get rowMeta() {
                return this.isEdit ? this.rowsById[this.form.id] ?? null : null;
            },

            autoSlug() {
                if (this._slugManuallyEdited) return;
                this.form.slug = this.form.name
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '_')
                    .replace(/^_+|_+$/g, '');
            },

            init() {
                this._rootEl  = this.$el;
                this.rowsById = Object.fromEntries(rows.map((r) => [r.id, r]));

                if (initial && typeof initial === 'object' && initial.mode) {
                    this.mode = initial.mode;
                    this.form = {
                        id:          initial.id ?? null,
                        name:        initial.name ?? '',
                        slug:        initial.slug ?? '',
                        description: initial.description ?? '',
                        sort_order:  initial.sort_order ?? '0',
                        is_active:   initial.is_active !== false,
                    };
                }

                this.initDataTable();
            },

            openNew() {
                this.mode = 'new';
                this.form = blank();
                this.fieldErrors = {};
                this._slugManuallyEdited = false;
            },

            openEdit(id) {
                const row = this.rowsById[id];
                if (!row) return;
                this.mode = 'edit';
                this.form = {
                    id:          row.id,
                    name:        row.name ?? '',
                    slug:        row.slug ?? '',
                    description: row.description ?? '',
                    sort_order:  String(row.sort_order ?? 0),
                    is_active:   row.is_active !== false,
                };
                this.fieldErrors = {};
                this._slugManuallyEdited = true;
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

            async toggleActive(id) {
                const row = this.rowsById[id];
                if (!row || this.togglingIds.includes(id)) return;
                const next = !row.is_active;

                if (!next && deactivateCheckUrlTemplate) {
                    let count = 0;
                    try {
                        const { data } = await posGet(deactivateCheckUrlTemplate.replace('__ID__', id));
                        count = data?.count ?? 0;
                    } catch (_) {}

                    if (count > 0) {
                        this.$store.confirm.show({
                            title:        `Deactivate "${row.name ?? ''}"?`,
                            message:      `This classification is used by ${count} tax group${count > 1 ? 's' : ''}. Are you sure you want to deactivate it?`,
                            intent:       'warning',
                            confirmLabel: 'Deactivate',
                            cancelLabel:  'Cancel',
                            onConfirm:    () => this._doToggleActive(id),
                        });
                        return;
                    }
                }

                this._doToggleActive(id);
            },

            async _doToggleActive(id) {
                const row = this.rowsById[id];
                if (!row || this.togglingIds.includes(id)) return;
                const next = !row.is_active;

                row.is_active    = next;
                this.togglingIds = [...this.togglingIds, id];

                const fd = new FormData();
                fd.append('_token',      document.querySelector('meta[name="csrf-token"]')?.content || '');
                fd.append('_method',     'PATCH');
                fd.append('name',        row.name ?? '');
                fd.append('slug',        row.slug ?? '');
                fd.append('description', row.description ?? '');
                fd.append('sort_order',  String(row.sort_order ?? 0));
                fd.append('is_active',   next ? '1' : '0');
                fd.append('editing_id',  id);

                const url = updateUrlTemplate.replace('__ID__', id);
                const data = await this._send(url, fd);

                this.togglingIds = this.togglingIds.filter((x) => x !== id);

                if (!data) { row.is_active = !next; return; }

                this._applyServerResponse(data);
                if (data.message) {
                    this.$store.toasts.push({ type: 'success', message: data.message });
                }
            },

            confirmDelete(message) {
                this.confirmDeleteRow(this.form.id, message);
            },

            confirmDeleteRow(id, message = null) {
                const row  = this.rowsById[id];
                const name = row?.name ?? 'this classification';
                this.$store.confirm.show({
                    title:        `Delete "${name}"?`,
                    message:      message,
                    intent:       'danger',
                    confirmLabel: 'Delete classification',
                    cancelLabel:  'Cancel',
                    onConfirm: async () => {
                        const url  = deleteUrlTemplate.replace('__ID__', id);
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
                    const list = this._rootEl.querySelector('.txcl-list');
                    if (list) {
                        list.replaceChildren();
                        list.insertAdjacentHTML('beforeend', data.list_html);
                        if (window.Alpine) window.Alpine.initTree(list);
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
