import { composeDataTableServer } from './data-table-server.js';
import { posPost, posGet, applyValidationErrors } from '../lib/http.js';

/**
 * Alpine factory for the Units index page.
 *
 * Same shape as `brandsPage` (master-detail flat list + sticky editor +
 * AJAX swap), plus the extra wrinkle that derived units carry a
 * `conversion_factor` only when a `base_unit_id` is set. The form
 * surfaces / hides the factor input reactively via `isBase`.
 */
export function unitsPage({
    endpoint = null,
    perPage = 25,
    total = 0,
    page = 1,
    totalPages = 1,
    storeUrl,
    updateUrlTemplate,
    deleteUrlTemplate,
    deactivateCheckUrlTemplate,
    rows = [],
    initial = null,
    defaultCategory = 'count',
} = {}) {
    const blank = () => ({
        id:                null,
        code:              '',
        name:              '',
        category:          defaultCategory,
        base_unit_id:      '',
        conversion_factor: '',
        is_active:         true,
    });

    return composeDataTableServer(
        {
            endpoint,
            initialPageSize: perPage,
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

            /** The mixin manages the paginated `.unit-list`; the editor reads
             *  `rowsById` (seeded full) so it can open any unit. */
            _dtContainer() {
                return this._dtRoot?.querySelector('.unit-list') ?? null;
            },

            /** True when the editor's base_unit_id is blank — i.e. the unit
             *  is (or will be) a base unit. Used to hide / disable the
             *  conversion_factor input since base units have no factor. */
            get isBase() { return !this.form.base_unit_id; },

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

            /** IDs that mustn't appear as a base-unit option for the row
             *  being edited: itself plus anything that already converts
             *  back to it (would create a conversion loop). */
            get unavailableBaseIds() {
                if (!this.form.id) return [];
                const blocked = [this.form.id];
                const walk = (parentId) => {
                    for (const row of rows) {
                        if (row.base_unit_id === parentId) {
                            blocked.push(row.id);
                            walk(row.id);
                        }
                    }
                };
                walk(this.form.id);
                return blocked;
            },

            /**
             * Driven by the base-unit select's `x-effect`. Does two
             * jobs every time `form.base_unit_id` (or the row being
             * edited) changes:
             *
             *   1. Push the form's current value INTO TomSelect so the
             *      visible chip matches what Alpine thinks is selected.
             *      Without this, switching between unit rows leaves
             *      TomSelect showing the previously-edited row's base.
             *      `setValue(_, true)` is silent — skips the onChange
             *      callback that would write back through x-model.
             *
             *   2. Enable / disable options that would form a conversion
             *      cycle (the row itself + anything that already converts
             *      back to it).
             */
            syncBaseSelect(ts) {
                if (!ts) return;

                // 1. Display sync. Coerce to string — TomSelect option
                //    values are strings, so setValue(4) silently misses
                //    the option whose value="4".
                ts.setValue(this.form.base_unit_id != null && this.form.base_unit_id !== ''
                    ? String(this.form.base_unit_id)
                    : '', true);

                // 2. Cycle-prevention. TomSelect's `disable()` /
                //    `enable()` take NO arguments — they toggle the
                //    ENTIRE control. To disable a single option we
                //    mutate its `disabled` field on the options map
                //    and call `refreshOptions(false)` to re-render
                //    the dropdown with the new state.
                const blocked = this.unavailableBaseIds;
                let changed = false;
                Object.keys(ts.options).forEach((value) => {
                    const id = parseInt(value, 10);
                    if (!Number.isFinite(id)) return;
                    const opt   = ts.options[value];
                    const next  = blocked.includes(id);
                    if (opt && !!opt.disabled !== next) {
                        opt.disabled = next;
                        changed = true;
                    }
                });
                if (changed) ts.refreshOptions(false);
            },

            init() {
                this._rootEl  = this.$el;
                this.rowsById = Object.fromEntries(rows.map((r) => [r.id, r]));

                if (initial && typeof initial === 'object' && initial.mode) {
                    this.mode = initial.mode;
                    this.form = {
                        id:                initial.id ?? null,
                        code:              initial.code ?? '',
                        name:              initial.name ?? '',
                        category:          initial.category ?? defaultCategory,
                        base_unit_id:      initial.base_unit_id ?? '',
                        conversion_factor: initial.conversion_factor ?? '',
                        is_active:         initial.is_active !== false,
                    };
                }

                this.initDataTableServer({ total, page, totalPages, perPage });
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
                    id:                row.id,
                    code:              row.code ?? '',
                    name:              row.name ?? '',
                    category:          row.category ?? defaultCategory,
                    // Cast to string so it matches TomSelect's option
                    // values (HTML attribute values are strings —
                    // setValue(4) doesn't select the option whose
                    // value="4", so the dropdown looks empty).
                    base_unit_id:      row.base_unit_id != null ? String(row.base_unit_id) : '',
                    conversion_factor: row.conversion_factor ?? '',
                    is_active:         row.is_active !== false,
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
                            message:      `This unit is assigned to ${count} product${count > 1 ? 's' : ''}. Are you sure you want to deactivate it?`,
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
                fd.append('_token',            document.querySelector('meta[name="csrf-token"]')?.content || '');
                fd.append('_method',           'PATCH');
                fd.append('code',              row.code ?? '');
                fd.append('name',              row.name ?? '');
                fd.append('category',          row.category ?? defaultCategory);
                fd.append('base_unit_id',      row.base_unit_id ?? '');
                fd.append('conversion_factor', row.conversion_factor ?? '');
                fd.append('is_active',         next ? '1' : '0');
                fd.append('editing_id',        id);

                const url = updateUrlTemplate.replace('__ID__', id);
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

            confirmDelete(message) {
                this.confirmDeleteRow(this.form.id, message);
            },

            /** Delete from a list row (no editor open). System-wide
             *  table convention — every row exposes a delete button. */
            confirmDeleteRow(id, message = null) {
                const row  = this.rowsById[id];
                const name = row?.name ?? 'this unit';
                this.$store.confirm.show({
                    title:        `Delete "${name}"?`,
                    message:      message,
                    intent:       'danger',
                    confirmLabel: 'Delete unit',
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

            async _ajaxDelete() {
                if (!this.isEdit || !this.form.id) return;
                const url = this.deleteAction;
                const data = await this._send(url, this._formWithMethod('DELETE'));
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
                if (data.id) {
                    this.openEdit(data.id);
                }
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
                        const messages = Object.values(errs).flat().filter(Boolean);
                        if (messages.length) {
                            this.$store.toasts.push({
                                type:    'error',
                                title:   messages.length === 1 ? 'Please review the form' : 'Please fix the errors below',
                                messages,
                            });
                        } else {
                            this.$store.toasts.push({
                                type:    'error',
                                message: e?.message || 'Request failed.',
                            });
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
