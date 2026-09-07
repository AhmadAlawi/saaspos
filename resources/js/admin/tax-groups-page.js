import { composeDataTable } from './data-table.js';
import { posPost, posGet, applyValidationErrors } from '../lib/http.js';

/**
 * Alpine factory for Settings → Tax → Groups.
 *
 * Master-detail like the other picklists, plus:
 *   - multi-component picker in the editor (checkbox list)
 *   - delete-with-move modal lifted from Categories — when products or
 *     categories reference the group, the operator picks a replacement
 *     before delete (server falls back to the system default if blank)
 */
export function taxGroupsPage({
    storeUrl,
    updateUrlTemplate,
    deleteUrlTemplate,
    deleteInfoUrlTemplate,
    deactivateCheckUrlTemplate,
    searchReplacementUrl,
    rows = [],
    components = [],
    initial = null,
} = {}) {
    const blank = () => ({
        id:             null,
        code:           '',
        name:           '',
        classification: 'taxable',
        is_inclusive:   false,
        is_default:     false,
        is_active:      true,
        component_ids:  [],
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
            components,
            submitting:  false,
            fieldErrors: {},

            classificationFilter: 'all',
            activeFilter:         'all',

            get hasActiveFilters() {
                return this.classificationFilter !== 'all'
                    || this.activeFilter !== 'all';
            },

            resetFilters() {
                this.classificationFilter = 'all';
                this.activeFilter         = 'all';
            },

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

            /** Sum of currently-picked component rates as a "12.50%" string.
             *  TomSelect emits string values, the components catalog uses
             *  numeric ids — coerce both sides to string before lookup. */
            get rateTotalDisplay() {
                const sum = this.form.component_ids.reduce((s, id) => {
                    const c = this.components.find((x) => String(x.id) === String(id));
                    return c ? s + (parseFloat(c.rate) || 0) : s;
                }, 0);
                return sum.toFixed(2) + '%';
            },

            init() {
                this._rootEl  = this.$el;
                this.rowsById = Object.fromEntries(rows.map((r) => [r.id, r]));

                if (initial && typeof initial === 'object' && initial.mode) {
                    this.mode = initial.mode;
                    this.form = {
                        id:             initial.id ?? null,
                        code:           initial.code ?? '',
                        name:           initial.name ?? '',
                        classification: initial.classification ?? 'taxable',
                        is_inclusive:   !!initial.is_inclusive,
                        is_default:     !!initial.is_default,
                        is_active:      initial.is_active !== false,
                        // Strings so TomSelect's `<option value>` (always
                        // string after Alpine binding) matches on first paint.
                        component_ids:  Array.isArray(initial.component_ids) ? initial.component_ids.map(String) : [],
                    };
                }
                this.initDataTable();
                this.$watch('classificationFilter', () => { this.page = 1; this._dtRender(); });
                this.$watch('activeFilter',         () => { this.page = 1; this._dtRender(); });
            },

            _dtFilter(row) {
                if (this.classificationFilter !== 'all') {
                    if (row.dataset.dtClassification !== this.classificationFilter) return false;
                }
                if (this.activeFilter !== 'all') {
                    const id    = parseInt(row.dataset.dtId, 10);
                    const state = this.rowsById[id];
                    if (this.activeFilter === 'active'   && !state?.is_active) return false;
                    if (this.activeFilter === 'inactive' && !!state?.is_active) return false;
                }
                return true;
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
                    id:             row.id,
                    code:           row.code ?? '',
                    name:           row.name ?? '',
                    classification: row.classification ?? 'taxable',
                    is_inclusive:   !!row.is_inclusive,
                    is_default:     !!row.is_default,
                    is_active:      row.is_active !== false,
                    component_ids:  Array.isArray(row.component_ids) ? row.component_ids.map(String) : [],
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

            /** Add/remove a component id from the picker. Order matters —
             *  pivot rows persist with `sort_order` in selection order so
             *  receipts render CGST → SGST → Cess in the order the operator
             *  ticks them. */
            toggleComponent(id) {
                const i = this.form.component_ids.indexOf(id);
                if (i >= 0) {
                    this.form.component_ids.splice(i, 1);
                } else {
                    this.form.component_ids.push(id);
                }
            },

            togglingIds: [],

            /** Flip is_active without opening the editor. Re-sends the row
             *  through the standard update endpoint with every field the
             *  FormRequest needs (including the existing component_ids so
             *  the pivot isn't wiped). */
            async toggleActive(id) {
                if (this.togglingIds.includes(id)) return;
                const row = this.rowsById[id];
                if (!row) return;
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
                            message:      `This tax group is assigned to ${count} product${count > 1 ? 's' : ''}. Are you sure you want to deactivate it?`,
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
                if (this.togglingIds.includes(id)) return;
                const row = this.rowsById[id];
                if (!row) return;
                const next = !row.is_active;

                row.is_active = next;
                this.togglingIds = [...this.togglingIds, id];

                const fd = new FormData();
                fd.append('_token',         document.querySelector('meta[name="csrf-token"]')?.content || '');
                fd.append('_method',        'PATCH');
                fd.append('code',           row.code ?? '');
                fd.append('name',           row.name ?? '');
                fd.append('classification', row.classification ?? 'taxable');
                fd.append('is_inclusive',   row.is_inclusive ? '1' : '0');
                fd.append('is_default',     row.is_default ? '1' : '0');
                fd.append('is_active',      next ? '1' : '0');
                for (const cid of (row.component_ids || [])) {
                    fd.append('component_ids[]', String(cid));
                }

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

            // ── Delete-with-move dialog state ──────────────────────
            delMove: {
                open:        false,
                id:          null,
                name:        '',
                count:       0,
                defaultId:   null,
                defaultName: '',
                replacementId:    '',
                replacementLabel: '',
                submitting:  false,
            },

            /** Open the delete flow. Pre-flights the count + default
             *  fallback so the modal can show "X items will be moved to
             *  Y" with the right defaults. */
            async askDelete(id) {
                const name = this.rowsById[id]?.name ?? 'this group';

                let info = { count: 0, default: null, is_default: false };
                if (deleteInfoUrlTemplate) {
                    try {
                        const url = deleteInfoUrlTemplate.replace('__ID__', id);
                        const { data } = await posGet(url);
                        info = data || info;
                    } catch (e) { /* fall through */ }
                }

                if (info.is_default) {
                    this.$store.toasts?.push({
                        type:    'error',
                        message: `"${name}" is the system default group and cannot be deleted.`,
                    });
                    return;
                }

                this.delMove = {
                    open:             true,
                    id,
                    name,
                    count:            info.count,
                    defaultId:        info.default?.id ?? null,
                    defaultName:      info.default?.name ?? '',
                    replacementId:    info.default?.id && info.count > 0 ? String(info.default.id) : '',
                    replacementLabel: info.count > 0 ? (info.default?.name || '') : '',
                    submitting:       false,
                };
            },

            closeDelMove() {
                if (this.delMove.submitting) return;
                this.delMove.open = false;
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
                    if (!data) {
                        this.delMove.submitting = false;
                        return;
                    }
                    this._applyServerResponse(data);
                    if (this.form.id === this.delMove.id) this.closeEditor();
                    this.delMove.open = false;
                    if (data.message) {
                        this.$store.toasts.push({ type: 'success', message: data.message });
                    }
                } finally {
                    this.delMove.submitting = false;
                }
            },

            get delMoveSearchUrl() {
                return searchReplacementUrl || '';
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
                    const tbody = this._rootEl.querySelector('.txg-list');
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
