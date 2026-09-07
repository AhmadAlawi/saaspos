import { composeDataTable } from './data-table.js';
import { posPost, posGet, applyValidationErrors } from '../lib/http.js';

/**
 * Alpine factory for the Drug Schedules admin page. Same shape as
 * brandsPage: master-detail flat list + sticky right-card editor +
 * AJAX swap on save / delete.
 */
export function drugSchedulesPage({
    storeUrl,
    updateUrlTemplate,
    deleteUrlTemplate,
    deleteInfoUrlTemplate,
    deactivateCheckUrlTemplate,
    searchReplacementUrl,
    lang = {},
    rows = [],
    initial = null,
} = {}) {
    const blank = () => ({
        id:           null,
        code:         '',
        name:         '',
        description:  '',
        country_code: '',
        is_active:    true,
    });

    return composeDataTable(
        {
            rowsSelector:       'li[data-dt-row]',
            searchableSelector: '.ds-row-name, .ds-row-code',
            initialPageSize:    25,
        },
        {
            mode:        'idle',
            form:        blank(),
            rowsById:    {},
            submitting:  false,
            togglingIds: [],

            _rootEl: null,

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
                    this.form = {
                        id:           initial.id ?? null,
                        code:         initial.code ?? '',
                        name:         initial.name ?? '',
                        description:  initial.description ?? '',
                        country_code: initial.country_code ?? '',
                        is_active:    initial.is_active !== false,
                    };
                }

                this.initDataTable();
            },

            openNew() {
                this.mode = 'new';
                this.form = blank();
            },

            openEdit(id) {
                const row = this.rowsById[id];
                if (!row) return;
                this.mode = 'edit';
                this.form = {
                    id:           row.id,
                    code:         row.code ?? '',
                    name:         row.name ?? '',
                    description:  row.description ?? '',
                    country_code: row.country_code ?? '',
                    is_active:    row.is_active !== false,
                };
            },

            closeEditor() {
                this.mode = 'idle';
                this.form = blank();
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
                            message:      `This drug schedule is assigned to ${count} product${count > 1 ? 's' : ''}. Are you sure you want to deactivate it?`,
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

                row.is_active   = next;
                this.togglingIds = [...this.togglingIds, id];

                const fd = new FormData();
                fd.append('_token',       document.querySelector('meta[name="csrf-token"]')?.content || '');
                fd.append('_method',      'PATCH');
                fd.append('code',         row.code ?? '');
                fd.append('name',         row.name ?? '');
                fd.append('description',  row.description ?? '');
                fd.append('country_code', row.country_code ?? '');
                fd.append('is_active',    next ? '1' : '0');
                fd.append('editing_id',   id);

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

            // ── Delete-with-move dialog state ──────────────────────
            // Populated by confirmDeleteRow(). Drives the modal markup
            // in drug-schedules/index.blade.php.
            delMove: {
                open:             false,
                id:               null,
                name:             '',
                count:            0,
                replacementId:    '',
                replacementLabel: '',
                submitting:       false,
            },

            /**
             * Per-row delete. Probes the server for the linked product
             * count first:
             *   - count == 0 → straight confirm dialog
             *   - count > 0  → custom modal asking where to move the
             *     products before delete (or leave blank to clear the
             *     schedule, since there is no default).
             */
            async confirmDeleteRow(id, _legacyMessage = null) {
                const name = this.rowsById[id]?.name ?? 'this schedule';

                let info = { count: 0 };
                if (deleteInfoUrlTemplate) {
                    try {
                        const { data } = await posGet(deleteInfoUrlTemplate.replace('__ID__', id));
                        info = data || info;
                    } catch (e) { /* fall through to a simple confirm */ }
                }

                // No products tagged → simple yes/no.
                if (!info.count) {
                    this.$store.confirm.show({
                        title:        `Delete "${name}"?`,
                        message:      this._lang('no_products'),
                        intent:       'danger',
                        confirmLabel: this._lang('confirm'),
                        cancelLabel:  this._lang('cancel'),
                        onConfirm: async () => {
                            const url  = deleteUrlTemplate.replace('__ID__', id);
                            const data = await this._send(url, this._formWithMethod('DELETE'));
                            if (!data) return;
                            this._applyServerResponse(data);
                            if (this.form.id === id) this.closeEditor();
                        },
                    });
                    return;
                }

                // Products tagged → custom modal with a searchable
                // "move to…" picker. Blank = clear the schedule.
                this.delMove = {
                    open:             true,
                    id,
                    name,
                    count:            info.count,
                    replacementId:    '',
                    replacementLabel: '',
                    submitting:       false,
                };
            },

            closeDelMove() {
                if (this.delMove.submitting) return;
                this.delMove.open = false;
            },

            /** URL for the remoteSelect picker on the delete dialog. */
            get delMoveSearchUrl() {
                return searchReplacementUrl || '';
            },

            async confirmDelMove() {
                if (this.delMove.submitting) return;
                this.delMove.submitting = true;
                try {
                    const url = deleteUrlTemplate.replace('__ID__', this.delMove.id);
                    const fd  = this._formWithMethod('DELETE');
                    if (this.delMove.replacementId) {
                        fd.append('replacement_id', this.delMove.replacementId);
                    }
                    const data = await this._send(url, fd);
                    if (!data) { this.delMove.submitting = false; return; }
                    this._applyServerResponse(data);
                    if (this.form.id === this.delMove.id) this.closeEditor();
                    this.delMove.open = false;
                } finally {
                    this.delMove.submitting = false;
                }
            },

            /** Pull a delete-dialog string from the lang map the view ships. */
            _lang(key) {
                return lang?.[key] || '';
            },

            async _ajaxDelete() {
                if (!this.isEdit || !this.form.id) return;
                const data = await this._send(this.deleteAction, this._formWithMethod('DELETE'));
                if (!data) return;
                this._applyServerResponse(data);
                this.closeEditor();
            },

            async submitEditor(evt) {
                if (this.submitting) return;
                this.submitting = true;

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
                    const list = this._rootEl.querySelector('.ds-list');
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
