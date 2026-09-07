import Sortable from 'sortablejs';
import { composeDataTable } from './data-table.js';
import { posPost, posGet, applyValidationErrors } from '../lib/http.js';

/**
 * Alpine factory for the Categories index page.
 *
 * Composes three behaviors into one component scope so they can share
 * reactive state cleanly:
 *
 *   1. **Editor** — owns `mode` ('idle' | 'edit' | 'new') and the
 *      `form` view-model. Clicking a row populates the form from the
 *      in-memory row snapshot the view ships in. Save submits a normal
 *      <form>; the server redirects to `?selected={id}` so the editor
 *      stays open on the saved row across the reload.
 *   2. **Data table** — search box + pagination on the list, pulled in
 *      via `composeDataTable` (NOT `...spread`, which would strip the
 *      mixin's reactive getters into static values).
 *   3. **Drag-to-reorder** — SortableJS on the list, auto-disabled
 *      whenever the data table is filtered or paged.
 *   4. **View toggle** — `'table'` (flat with paging) vs `'tree'`
 *      (indented hierarchy, no paging). Drag is also disabled in tree
 *      mode so children don't get yanked out of their parent.
 *
 * Server-side validation failure is the one path that needs the editor
 * to reopen across a full page reload — the view passes `initial` from
 * `old()` values in that case.
 */
export function categoriesPage({
    storeUrl,
    updateUrlTemplate,
    deleteUrlTemplate,
    deleteInfoUrlTemplate,
    deactivateCheckUrlTemplate,
    searchReplacementUrl,
    reorderUrl,
    rows = [],
    initial = null,
    defaultColor = '#F97316',
} = {}) {
    const blank = () => ({
        id:           null,
        name:         '',
        parent_id:    '',
        color:        defaultColor,
        tax_group_id: '',
        is_active:    true,
    });

    return composeDataTable(
        {
            rowsSelector:       'li[data-dt-row]',
            searchableSelector: '.cat-row-name',
            initialPageSize:    25,
        },
        {
            // ── Editor state ───────────────────────────────────────
            mode:        'idle',      // 'idle' | 'edit' | 'new'
            form:        blank(),
            rowsById:    {},
            submitting:  false,       // true while the save POST/PATCH is in flight
            togglingIds: [],          // ids whose status toggle is mid-flight

            // ── View toggle ────────────────────────────────────────
            view: 'table',            // 'table' | 'tree'

            // ── Sortable internals ─────────────────────────────────
            _sortable: null,

            get isIdle() { return this.mode === 'idle'; },
            get isEdit() { return this.mode === 'edit' && !!this.form.id; },
            get isNew()  { return this.mode === 'new'; },
            get isTree() { return this.view === 'tree';  },
            get isTable() { return this.view === 'table'; },

            // Asked by `_dtRender` — paging is suppressed in tree view.
            get _dtPagingDisabled() { return this.view === 'tree'; },

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

            /**
             * Driven by the parent select's `x-effect`. Does two jobs
             * every time the editor's form changes:
             *
             *   1. Push `form.parent_id` INTO TomSelect so the visible
             *      chip matches Alpine's idea of the selection. Without
             *      this, switching rows leaves TomSelect stuck on the
             *      previous row's parent. `setValue(_, true)` is silent
             *      — skips onChange to avoid a reactive loop with x-model.
             *
             *   2. Disable options that would form a cycle (the row
             *      itself plus every descendant).
             */
            syncParentDisabled(ts) {
                if (!ts) return;

                // 1. Display sync.
                ts.setValue(this.form.parent_id ?? '', true);

                // 2. Cycle prevention. TomSelect's `disable()` /
                //    `enable()` take NO arguments — they toggle the
                //    ENTIRE control. Mutate `disabled` on the options
                //    map and call `refreshOptions(false)` so the
                //    dropdown re-renders with the new state.
                const blocked = this.unavailableParentIds;     // reactive read — establishes the dep
                let changed = false;
                Object.keys(ts.options).forEach((value) => {
                    const id = parseInt(value, 10);
                    if (!Number.isFinite(id)) return;
                    const opt  = ts.options[value];
                    const next = blocked.includes(id);
                    if (opt && !!opt.disabled !== next) {
                        opt.disabled = next;
                        changed = true;
                    }
                });
                if (changed) ts.refreshOptions(false);
            },

            /** IDs that must not appear as parent options for the row being
             *  edited: the row itself plus every descendant (else we'd
             *  create a cycle in the hierarchy). */
            get unavailableParentIds() {
                if (!this.form.id) return [];
                const blocked = [this.form.id];
                const walk = (parentId) => {
                    for (const row of rows) {
                        if (row.parent_id === parentId) {
                            blocked.push(row.id);
                            walk(row.id);
                        }
                    }
                };
                walk(this.form.id);
                return blocked;
            },

            /**
             * Stable cached reference to the component's root element.
             * Captured once in `init()` (where `this.$el` IS the root)
             * and used in place of `this.$root` everywhere else —
             * because Alpine's `$root`/`$el` magics are tied to the
             * current directive-evaluation context and become
             * `undefined` once an async method awaits.
             */
            _rootEl: null,

            init() {
                // Cache the root element synchronously — see _rootEl docblock.
                this._rootEl = this.$el;

                // Editor: build the lookup once.
                this.rowsById = Object.fromEntries(rows.map((r) => [r.id, r]));
                if (initial && typeof initial === 'object' && initial.mode) {
                    this.mode = initial.mode;
                    this.form = {
                        id:           initial.id ?? null,
                        name:         initial.name ?? '',
                        parent_id:    initial.parent_id ?? '',
                        color:        initial.color ?? defaultColor,
                        tax_group_id: initial.tax_group_id ?? '',
                        is_active:    initial.is_active !== false,
                    };
                }

                // Data table: collect rows + wire watchers.
                this.initDataTable();

                // Re-render the row visibility whenever the view flips.
                // Tree mode shows every row (no paging); table mode goes
                // back to slicing by pageSize. We also:
                //   - reset `page` to 1 (the page that was valid in tree
                //     mode might not exist in the paged table view)
                //   - call refreshDataTable() instead of _dtRender() so
                //     `_dtCollect` re-scans the DOM first. Otherwise an
                //     AJAX swap that happened in tree mode leaves
                //     `_dtRows` pointing to detached <li>s, and the
                //     `dt-hidden` toggles land on orphans nobody can see.
                this.$watch('view', () => {
                    this.page = 1;
                    this.refreshDataTable();
                });

                // Sortable: only initialize if there's a list to drag.
                this.$nextTick(() => {
                    this._initSortable();
                    this.$watch('isPristine', () => this._sortable?.option('disabled', !this._canDrag));
                    this.$watch('view',       () => this._sortable?.option('disabled', !this._canDrag));
                    // A live sort reorders the DOM away from the stored
                    // sort_order, so drag must be off while one is active —
                    // otherwise a drop would persist the sorted positions.
                    this.$watch('sortCol',    () => this._sortable?.option('disabled', !this._canDrag));
                });
            },

            /**
             * (Re)build the SortableJS instance on the current `.cat-list`.
             * Called from `init()` AND after every AJAX list refresh
             * (because the underlying <li> nodes get replaced and the
             * old Sortable cache would otherwise reference detached
             * DOM).
             */
            _initSortable() {
                const list = this._rootEl.querySelector('.cat-list');
                if (!list || !reorderUrl) return;
                if (this._sortable) {
                    this._sortable.destroy();
                    this._sortable = null;
                }
                this._sortable = Sortable.create(list, {
                    handle:      '.cat-handle',
                    animation:   150,
                    ghostClass:  'is-dragging-ghost',
                    chosenClass: 'is-dragging',
                    dragClass:   'is-dragged',
                    onStart: () => {
                        this._preDragOrder = this._currentOrderIds();
                    },
                    onEnd: (evt) => this._handleDragEnd(evt),
                });
                this._sortable.option('disabled', !this._canDrag);
            },

            destroy() {
                this._sortable?.destroy();
                this._sortable = null;
            },

            /** Drag is meaningful whenever every row is visible — both flat
             *  and tree views support reorder. In tree view, `onMove` (see
             *  init) further restricts moves to same-parent siblings so the
             *  hierarchy stays intact. */
            get _canDrag() { return this.isPristine && !this.sortCol; },

            /** Snapshot of the current `<li data-id>` order — used to revert
             *  the DOM after a cancelled or rejected drag. */
            _currentOrderIds() {
                return Array.from(this._rootEl.querySelectorAll('.cat-list > li[data-id]'))
                    .map((el) => el.dataset.id);
            },

            /** Restore the list order from a snapshot taken via _currentOrderIds. */
            _revertOrder(snapshot) {
                if (!snapshot || !this._sortable) return;
                // Sortable's `sort()` accepts an array of element ids/values to reorder.
                this._sortable.sort(snapshot, true);
            },

            /** Returns IDs of `id`'s descendants by walking the in-memory
             *  `rows` (snapshot of the server-rendered tree). Used to
             *  block dropping a node under its own descendant. */
            _descendantsOf(id) {
                const result = [];
                const walk = (parentId) => {
                    for (const row of rows) {
                        if (row.parent_id === parentId) {
                            result.push(row.id);
                            walk(row.id);
                        }
                    }
                };
                walk(id);
                return result;
            },

            /**
             * Called after every drag. In tree view the rule is:
             *   "the dropped row becomes a child of whatever row is
             *    immediately above it; if nothing is above, it becomes
             *    a root."
             * For a pure same-parent reorder, no confirmation is needed.
             * For a parent change, ask the user before posting.
             */
            _handleDragEnd(evt) {
                if (!reorderUrl) return;

                const draggedEl   = evt.item;
                const draggedId   = parseInt(draggedEl.dataset.id, 10);
                const oldParent   = draggedEl.dataset.parent
                    ? parseInt(draggedEl.dataset.parent, 10)
                    : null;

                // Derive new parent based on view mode.
                let newParent;
                if (this.isTree) {
                    const prevEl = draggedEl.previousElementSibling;
                    newParent = prevEl && prevEl.dataset.id
                        ? parseInt(prevEl.dataset.id, 10)
                        : null;

                    // Block cycle: can't drop a row under one of its own descendants.
                    if (newParent !== null && this._descendantsOf(draggedId).includes(newParent)) {
                        this._revertOrder(this._preDragOrder);
                        this.$store.toasts.push({
                            type:    'error',
                            message: 'Cannot move a category under its own descendant.',
                        });
                        return;
                    }
                } else {
                    // Table view: drag never reparents.
                    newParent = oldParent;
                }

                const parentChanged = oldParent !== newParent;

                if (parentChanged) {
                    const draggedName = this.rowsById[draggedId]?.name ?? '#'+draggedId;
                    const newName     = newParent !== null
                        ? (this.rowsById[newParent]?.name ?? '#'+newParent)
                        : 'Top level';
                    const oldName     = oldParent !== null
                        ? (this.rowsById[oldParent]?.name ?? '#'+oldParent)
                        : 'Top level';

                    this.$store.confirm.show({
                        title:        `Move "${draggedName}" under "${newName}"?`,
                        message:      `"${draggedName}" will move from "${oldName}" to "${newName}". Sort order within "${newName}" is preserved from where you dropped the row.`,
                        intent:       'warning',
                        confirmLabel: 'Move category',
                        cancelLabel:  'Cancel',
                        // Async — returns the fetch Promise so the dialog
                        // shows a spinner in the confirm button while the
                        // server processes the move.
                        onConfirm: async () => {
                            // Reflect the new parent on the DOM node so the
                            // POST payload picks it up.
                            draggedEl.dataset.parent = newParent ?? '';
                            await this._postOrder(true);
                        },
                        onCancel: () => {
                            this._revertOrder(this._preDragOrder);
                        },
                    });
                    return;
                }

                this._postOrder(false);
            },

            /**
             * POST the full ordered list of rows (id + parent_id) to the
             * server. Returns a Promise so the confirm dialog can show a
             * spinner during the round-trip.
             *
             * On success with `parentChanged === true` the page reloads
             * so the server can redraw the tree with correct indentation;
             * we return a never-settling Promise in that case so the
             * confirm dialog's spinner stays visible until navigation
             * actually happens.
             */
            async _postOrder(parentChanged = false) {
                const lis = Array.from(this._rootEl.querySelectorAll('.cat-list > li[data-id]'));
                const payload = lis.map((el) => ({
                    id:        parseInt(el.dataset.id, 10),
                    parent_id: el.dataset.parent ? parseInt(el.dataset.parent, 10) : null,
                })).filter((r) => Number.isFinite(r.id));
                if (payload.length === 0) return;

                let data;
                try {
                    ({ data } = await posPost(reorderUrl, { rows: payload }));
                } catch (e) {
                    this.$store.toasts.push({
                        type:    'error',
                        message: e?.status === 422
                            ? 'That move would create a cycle in the hierarchy.'
                            : (e?.status === undefined
                                ? 'Network error saving the new order.'
                                : 'Could not save the new order.'),
                    });
                    this._revertOrder(this._preDragOrder);
                    throw e;
                }

                if (parentChanged && data?.list_html) {
                    // Swap the freshly-rendered tree in place — covers the
                    // indentation/depth change that came with the parent move.
                    this._applyServerResponse(data);
                }
            },

            // ── Editor methods ─────────────────────────────────────
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
                    name:         row.name ?? '',
                    parent_id:    row.parent_id ?? '',
                    color:        row.color ?? defaultColor,
                    tax_group_id: row.tax_group_id ?? '',
                    is_active:    row.is_active !== false,
                };
            },

            closeEditor() {
                this.mode = 'idle';
                this.form = blank();
            },

            /**
             * Flip a row's is_active via AJAX without opening the editor.
             * Optimistic — the toggle UI updates immediately. On server
             * failure the value reverts and an error toast appears.
             *
             * Re-uses the existing PATCH /admin/categories/{id} endpoint
             * since it already validates + fires hooks/events; the
             * payload carries the current name/parent/color/tax so the
             * required-field rules still pass.
             */
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
                            message:      `This category is assigned to ${count} product${count > 1 ? 's' : ''}. Are you sure you want to deactivate it?`,
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

                row.is_active = next;
                this.togglingIds = [...this.togglingIds, id];

                const fd = new FormData();
                fd.append('_token',       document.querySelector('meta[name="csrf-token"]')?.content || '');
                fd.append('_method',      'PATCH');
                fd.append('name',         row.name ?? '');
                fd.append('parent_id',    row.parent_id ?? '');
                fd.append('color',        row.color ?? '');
                fd.append('tax_group_id', row.tax_group_id ?? '');
                fd.append('is_active',    next ? '1' : '0');
                fd.append('editing_id',   id);

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
            // Populated by openDeleteFlow(). Drives the modal markup
            // in categories/index.blade.php.
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

            /**
             * Per-row delete. Probes the server for the linked product
             * count + default fallback first:
             *   - count == 0 → straight confirm dialog (legacy path)
             *   - count > 0  → custom modal asking where to move the
             *     products before delete. Server-side searchable picker
             *     defaults to "Uncategorized".
             */
            async confirmDeleteRow(id, _legacyMessage = null) {
                const name = this.rowsById[id]?.name ?? 'this category';

                let info = { count: 0, default: null };
                if (deleteInfoUrlTemplate) {
                    try {
                        const url = deleteInfoUrlTemplate.replace('__ID__', id);
                        const { data } = await posGet(url);
                        info = data || info;
                    } catch (e) { /* fall through to a simple confirm */ }
                }

                // No products attached → simple yes/no.
                if (!info.count) {
                    this.$store.confirm.show({
                        title:        `Delete "${name}"?`,
                        message:      'This category has no products. Deleting it is safe.',
                        intent:       'danger',
                        confirmLabel: 'Delete category',
                        cancelLabel:  'Cancel',
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

                // Products attached → custom modal with a searchable
                // "move to…" picker that defaults to the system default.
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
                    this.delMove.open = false;
                } finally {
                    this.delMove.submitting = false;
                }
            },

            /** URL for the remoteSelect picker — fetched fresh each time
             *  the modal opens so the `exclude` reflects the current id. */
            get delMoveSearchUrl() {
                return searchReplacementUrl || '';
            },

            /**
             * AJAX delete. Server responds with the freshly-rendered
             * list (children may have been re-rooted by nullOnDelete),
             * which we swap in via _applyServerResponse().
             */
            async _ajaxDelete() {
                if (!this.isEdit || !this.form.id) return;
                const url = this.deleteAction;

                const data = await this._send(url, this._formWithMethod('DELETE'));
                if (!data) return;                       // toast already shown

                this._applyServerResponse(data);
                this.closeEditor();                      // row is gone
            },

            /**
             * Intercept the editor form's native submit and POST as XHR.
             *
             * Server response shape:
             *   - 2xx { ok:true, id, message, list_html, rows }
             *         → swap the whole list + refresh data-table + toast
             *   - 422 { errors: {...}, message }
             *         → toast the first error, leave the form open
             *
             * No reloads, no per-row vs full-list branching — same code
             * path for create, simple edit, and parent-change edit.
             */
            async submitEditor(evt) {
                if (this.submitting) return;
                this.submitting = true;

                const data = await this._send(this.formAction, new FormData(evt.target), evt.target);
                if (!data) { this.submitting = false; return; }  // toast already shown

                this._applyServerResponse(data);

                if (data.message) {
                    this.$store.toasts.push({ type: 'success', message: data.message });
                }
                // If the server tells us a row to focus (e.g. just-created
                // category), switch the editor to it.
                if (data.id) {
                    this.openEdit(data.id);
                }
                this.submitting = false;
            },

            /**
             * Apply a server "freshList" payload: swap the list HTML in,
             * re-bind Alpine on the new nodes, refresh the in-memory
             * rowsById lookup, and re-collect the data-table rows.
             *
             * We refresh the data-table twice on purpose:
             *   1. Synchronously, right after the DOM swap — gives the
             *      immediately-following render an up-to-date row count.
             *   2. In $nextTick — covers the case where Alpine reactivity
             *      from the swap is still flushing and would otherwise
             *      re-evaluate `isEmpty` against the empty interim state.
             * Both runs hit the same `_dtCollect` + `_dtRender` path and
             * are idempotent, so the extra call costs nothing.
             */
            /**
             * Apply a server "freshList" payload: swap the list HTML in,
             * re-bind Alpine on the new nodes, rebuild Sortable, refresh
             * the in-memory `rowsById` lookup, and re-apply pagination
             * to the freshly-attached rows.
             *
             * `_dtCollect` + `_dtRender` are called directly (not through
             * a wrapper). The mixin's `refreshDataTable` does the same
             * thing, but inlining here makes it impossible for the chain
             * to silently no-op — if these methods aren't there, this
             * throws loudly.
             *
             * Queries go through `this._rootEl` (cached in init), NOT
             * `this.$el` or `this.$root`. Both Alpine magics are tied to
             * the directive-evaluation context: `$el` is the directive
             * host (e.g. a button, which doesn't contain `.cat-list`)
             * and `$root` becomes `undefined` once an async method has
             * awaited. The cached `_rootEl` is the only reference that
             * stays valid across awaits and `$nextTick` callbacks.
             */
            _applyServerResponse(data) {
                if (data.list_html) {
                    const list = this._rootEl.querySelector('.cat-list');
                    if (list) {
                        // Tear down Sortable first — its internal cache of
                        // children would otherwise keep references to the
                        // about-to-be-detached <li> nodes.
                        if (this._sortable) {
                            this._sortable.destroy();
                            this._sortable = null;
                        }

                        // Hard reset: clear, then write fresh HTML.
                        list.replaceChildren();
                        list.insertAdjacentHTML('beforeend', data.list_html);

                        if (window.Alpine) window.Alpine.initTree(list);

                        // Recreate Sortable on the now-attached fresh children.
                        this._initSortable?.();
                    }
                }
                if (Array.isArray(data.rows)) {
                    this.rowsById = Object.fromEntries(data.rows.map((r) => [r.id, r]));
                }

                // Re-collect rows from the freshly-populated DOM and
                // re-apply pagination. Sync first so the immediately-
                // following Alpine flush sees current `_dtRows`; nextTick
                // catches any reactive write that queued a stale render.
                this._dtCollect();
                this._dtRender();
                this.$nextTick(() => {
                    this._dtCollect();
                    this._dtRender();
                });
            },

            /**
             * Generic AJAX POST helper used by save and delete. Returns
             * the Response on success (2xx), or null after pushing an
             * error toast (network, 422, or other 4xx/5xx). Validation
             * errors get a friendly message extracted from `errors`.
             */
            async _send(url, body, formEl = null) {
                try {
                    const { data } = await posPost(url, body);
                    return data;
                } catch (e) {
                    if (e?.status === 422) {
                        // Two flavours of 422:
                        //   - form validation (has `errors` per-field map)
                        //     → list EVERY message so the user sees all
                        //       problems at once instead of one-at-a-time
                        //   - blocked action (e.g. can't delete with linked
                        //     products) → no title, just the server message
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

            /** Tiny helper: FormData with just CSRF + an override _method. */
            _formWithMethod(method) {
                const fd = new FormData();
                fd.append('_token',  document.querySelector('meta[name="csrf-token"]')?.content || '');
                fd.append('_method', method);
                return fd;
            },
        },
    );
}
