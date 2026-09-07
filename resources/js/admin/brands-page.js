import { composeDataTableServer } from './data-table-server.js';
import { posPost, posGet, applyValidationErrors } from '../lib/http.js';

/**
 * Alpine factory for the Brands index page.
 *
 * Trimmed sibling of `categoriesPage`: same master-detail shape (list +
 * sticky editor), same AJAX swap, but flat — no tree, no sortable, no
 * parent/color/tax. See `categories-page.js` for the comments on
 * `_rootEl` caching, the 422 toast flavours, and the
 * `_applyServerResponse` reset pattern.
 */
export function brandsPage({
    endpoint = null,
    perPage = 25,
    total = 0,
    page = 1,
    totalPages = 1,
    storeUrl,
    updateUrlTemplate,
    deleteUrlTemplate,
    deleteInfoUrlTemplate,
    deactivateCheckUrlTemplate,
    searchReplacementUrl,
    rows = [],
    initial = null,
} = {}) {
    const blank = () => ({
        id:          null,
        name:        '',
        description: '',
        logo_url:    null,
        is_active:   true,
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

            // IDs whose <img> fired `@error` — typically because the
            // stored file is missing or the URL is wrong. Templates fall
            // back to the initial-letter tile when an id is in here.
            // Reset whenever the server returns a fresh row payload so
            // a re-upload gets a clean re-try.
            logoFailedIds: [],

            _rootEl: null,

            get isIdle() { return this.mode === 'idle'; },
            get isEdit() { return this.mode === 'edit' && !!this.form.id; },
            get isNew()  { return this.mode === 'new'; },

            /** The mixin manages the paginated `.brand-list`; the side-editor
             *  reads `rowsById` (seeded full) so it can open any brand. */
            _dtContainer() {
                return this._dtRoot?.querySelector('.brand-list') ?? null;
            },

            /** True when the brand at `id` has a logo URL AND the browser
             *  hasn't already failed to load it. */
            brandHasLogo(id) {
                const row = this.rowsById[id];
                return !!(row?.logo_url) && !this.logoFailedIds.includes(id);
            },

            /** Same check as `brandHasLogo` but driven by the editor's
             *  current form. New-mode previews are blob URLs (never 404),
             *  so we skip the failed-set check there. */
            get formHasLogo() {
                if (!this.form.logo_url) return false;
                if (!this.form.id) return true;
                return !this.logoFailedIds.includes(this.form.id);
            },

            _onLogoError(id) {
                if (!id) return;
                if (! this.logoFailedIds.includes(id)) {
                    this.logoFailedIds = [...this.logoFailedIds, id];
                }
            },

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
                        id:          initial.id ?? null,
                        name:        initial.name ?? '',
                        description: initial.description ?? '',
                        logo_url:    initial.logo_url ?? null,
                        is_active:   initial.is_active !== false,
                    };
                }

                this.initDataTableServer({ total, page, totalPages, perPage });

                // Push the initial logo URL into the image-upload child
                // once Alpine has wired it.
                this.$nextTick(() => this._syncLogoUpload());

                // Reset the image-upload whenever the editor switches
                // to a different brand OR flips between edit/new/idle.
                // This is the catch-all: any code path that mutates
                // `form.id` (openEdit, openNew, _ajaxDelete, server
                // response that re-opens a brand) flows through here.
                // Without this watcher, picking a file for brand A and
                // then clicking brand B would carry A's picked file
                // forward into B's editor.
                this.$watch('form.id', () => this.$nextTick(() => this._syncLogoUpload()));
                this.$watch('mode',    () => this.$nextTick(() => this._syncLogoUpload()));
            },

            /**
             * Reach into the embedded image-upload component and reset
             * its preview to match the editor's current form state.
             * Tagged via `[data-logo-upload]` on the field wrapper; the
             * Alpine scope hangs off the inner `.img-upload` element.
             */
            _syncLogoUpload() {
                const wrapper = this._rootEl?.querySelector('[data-logo-upload] .img-upload');
                if (!wrapper || !window.Alpine) return;
                const scope = window.Alpine.$data(wrapper);
                scope?.sync?.(this.form.logo_url);
            },

            openNew() {
                this.mode = 'new';
                this.form = blank();
                // image-upload is reset via the `mode` / `form.id` watcher in init().
            },

            openEdit(id) {
                const row = this.rowsById[id];
                if (!row) return;
                this.mode = 'edit';
                this.form = {
                    id:          row.id,
                    name:        row.name ?? '',
                    description: row.description ?? '',
                    logo_url:    row.logo_url ?? null,
                    is_active:   row.is_active !== false,
                };
                // image-upload is reset via the `form.id` watcher in init().
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
                            message:      `This brand is assigned to ${count} product${count > 1 ? 's' : ''}. Are you sure you want to deactivate it?`,
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
                fd.append('_token',      document.querySelector('meta[name="csrf-token"]')?.content || '');
                fd.append('_method',     'PATCH');
                fd.append('name',        row.name ?? '');
                fd.append('description', row.description ?? '');
                fd.append('is_active',   next ? '1' : '0');
                fd.append('editing_id',  id);

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

            /** Per-row delete. Probes the server for the linked product
             *  count first; if any, opens the move-to-target modal so
             *  the operator can pick a target brand (defaults to the
             *  system "Generic"). If zero, falls back to a plain confirm. */
            async confirmDeleteRow(id, _legacyMessage = null) {
                const name = this.rowsById[id]?.name ?? 'this brand';

                let info = { count: 0, default: null };
                if (deleteInfoUrlTemplate) {
                    try {
                        const url = deleteInfoUrlTemplate.replace('__ID__', id);
                        const { data } = await posGet(url);
                        info = data || info;
                    } catch (e) { /* fall through */ }
                }

                if (!info.count) {
                    this.$store.confirm.show({
                        title:        `Delete "${name}"?`,
                        message:      'This brand has no products. Deleting it is safe.',
                        intent:       'danger',
                        confirmLabel: 'Delete brand',
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
                    return;
                }

                this.delMove = {
                    open:        true,
                    id,
                    name,
                    count:       info.count,
                    defaultId:   info.default?.id ?? null,
                    defaultName: info.default?.name ?? '',
                    replacementId:    info.default?.id ? String(info.default.id) : '',
                    replacementLabel: info.default?.name || '',
                    submitting:  false,
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
                    if (data.message) {
                        this.$store.toasts.push({ type: 'success', message: data.message });
                    }
                    this.delMove.open = false;
                } finally {
                    this.delMove.submitting = false;
                }
            },

            get delMoveSearchUrl() {
                return searchReplacementUrl || '';
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
                // Refresh `rowsById` FIRST (the full set the editor + every
                // visible row's bindings read) so the reloaded page renders
                // against fresh data on its first evaluation.
                if (Array.isArray(data.rows)) {
                    this.rowsById = Object.fromEntries(data.rows.map((r) => [r.id, r]));
                    // The URL of every row may have changed (new upload, logo
                    // removed). Drop the failed-load memo for a clean re-try.
                    this.logoFailedIds = [];
                }

                // Reload the current page from the server (was: swap the whole
                // list_html). `data.list_html` is now ignored.
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
