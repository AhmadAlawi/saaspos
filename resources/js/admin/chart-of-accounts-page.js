import TomSelect from 'tom-select';
import 'tom-select/dist/js/plugins/dropdown_input.js';
import { posPost, applyValidationErrors } from '../lib/http.js';
import { submitForm } from '../lib/submit-form.js';

/**
 * Alpine factory for Accounting → Chart of Accounts.
 *
 * Master-detail like `taxComponentsPage`, but the "list" is a grouped tree.
 * ACCOUNT create / edit / delete post over AJAX and swap the freshly rendered
 * `tree_html` in (no reload). GROUP create / edit / delete are structural and
 * infrequent, so they post via `submitForm` and reload the page — which
 * reflows the tree and every dropdown from the server. Guard failures come
 * back as a 422 with a `message` and surface as an error toast.
 *
 * Two TomSelects are owned here (not via the enhancedSelect component) because
 * both need their value pushed reactively as the editor switches records:
 * the account editor's group picker and the group editor's parent picker.
 */
export function chartOfAccountsPage({
    storeUrl,
    updateUrlTemplate,
    deleteUrlTemplate,
    groupStoreUrl,
    groupUpdateUrlTemplate,
    groupDeleteUrlTemplate,
    rows = [],
    groups = [],
    selectedId = null,
    parentPlaceholder = 'Select a parent…',
} = {}) {
    const blank = () => ({
        id:               null,
        code:             '',
        name:             '',
        account_group_id: '',
        is_active:        true,
        is_system:        false,
        code_locked:      false,
        can_delete:       false,
    });

    const blankGroup = () => ({
        id:         null,
        name:       '',
        parent_id:  '',
        is_system:  false,
        can_delete: false,
    });

    return {
        mode:        'idle',   // idle | new | edit | group-new | group-edit
        form:        blank(),
        groupForm:   blankGroup(),
        rowsById:    {},
        groupsById:  {},
        submitting:  false,
        fieldErrors: {},
        togglingIds: [],
        _rootEl:     null,
        groupTs:     null,
        parentTs:    null,

        get isIdle()        { return this.mode === 'idle'; },
        get isEdit()        { return this.mode === 'edit' && !!this.form.id; },
        get isNew()         { return this.mode === 'new'; },
        get isAccountMode() { return this.mode === 'new' || this.mode === 'edit'; },
        get isGroupMode()   { return this.mode === 'group-new' || this.mode === 'group-edit'; },
        get isGroupNew()    { return this.mode === 'group-new'; },
        get isGroupEdit()   { return this.mode === 'group-edit'; },

        get formAction() {
            return this.isEdit ? updateUrlTemplate.replace('__ID__', this.form.id) : storeUrl;
        },

        init() {
            this._rootEl    = this.$el;
            this.rowsById   = Object.fromEntries(rows.map((r) => [r.id, r]));
            this.groupsById = Object.fromEntries(groups.map((g) => [g.id, g]));

            // Editors live under x-show (always in the DOM), so both selects
            // can be upgraded once here and reused across every record.
            this.$nextTick(() => {
                this._initGroupSelect();
                this._initParentSelect();
                if (selectedId && this.rowsById[selectedId]) {
                    this.openEdit(selectedId);
                }
            });
        },

        // ── Account group picker (account editor) ──────────────────────

        _initGroupSelect() {
            const el = this.$refs.group;
            if (!el || el.tomselect) return;

            this.groupTs = new TomSelect(el, {
                allowEmptyOption: true,
                maxOptions:       300,
                searchField:      ['text'],
                sortField:        { field: '$order' },
                plugins:          ['dropdown_input'],
                placeholder:      'Search…',
            });
            this.groupTs.on('change', () => {
                this.form.account_group_id = this.groupTs.getValue();
                this.clearFieldError('account_group_id');
            });
        },

        _setGroup(value) {
            const v = (value === null || value === undefined) ? '' : String(value);
            if (this.groupTs && String(this.groupTs.getValue()) !== v) {
                this.groupTs.setValue(v, true);
            }
        },

        // ── Parent picker (group editor) ───────────────────────────────

        _initParentSelect() {
            const el = this.$refs.parent;
            if (!el || el.tomselect) return;

            this.parentTs = new TomSelect(el, {
                allowEmptyOption: true,
                maxOptions:       300,
                searchField:      ['text'],
                sortField:        { field: '$order' },
                plugins:          ['dropdown_input'],
                placeholder:      'Search…',
            });
            this.parentTs.on('change', () => this.clearFieldError('parent_id'));
        },

        /** Repopulate the parent picker with the valid parents for a mode. */
        _setParentOptions(validGroups, currentValue) {
            const ts = this.parentTs;
            if (!ts) return;
            ts.clearOptions();
            ts.addOption({ value: '', text: parentPlaceholder });
            validGroups.forEach((g) => ts.addOption({ value: String(g.id), text: g.path_label }));
            ts.refreshOptions(false);
            ts.setValue(currentValue ? String(currentValue) : '', true);
        },

        _descendantIds(rootId) {
            const out   = new Set();
            const stack = [rootId];
            while (stack.length) {
                const id = stack.pop();
                for (const g of Object.values(this.groupsById)) {
                    if (g.parent_id === id && !out.has(g.id)) { out.add(g.id); stack.push(g.id); }
                }
            }
            return out;
        },

        // ── Account editor ─────────────────────────────────────────────

        openNew() {
            this.mode        = 'new';
            this.form        = blank();
            this.fieldErrors = {};
            this._setGroup('');
        },

        openEdit(id) {
            const row = this.rowsById[id];
            if (!row) return;
            this.mode = 'edit';
            this.form = {
                id:               row.id,
                code:             row.code ?? '',
                name:             row.name ?? '',
                account_group_id: row.account_group_id ?? '',
                is_active:        row.is_active !== false,
                is_system:        row.is_system === true,
                code_locked:      row.code_locked === true,
                can_delete:       row.can_delete === true,
            };
            this.fieldErrors = {};
            this._setGroup(this.form.account_group_id);
        },

        closeEditor() {
            this.mode        = 'idle';
            this.form        = blank();
            this.groupForm   = blankGroup();
            this.fieldErrors = {};
        },

        clearFieldError(field) {
            if (this.fieldErrors[field]) {
                const next = { ...this.fieldErrors };
                delete next[field];
                this.fieldErrors = next;
            }
        },

        async submitEditor(evt) {
            if (this.submitting) return;
            this.submitting  = true;
            this.fieldErrors = {};

            const data = await this._send(this.formAction, new FormData(evt.target), evt.target);
            if (!data) { this.submitting = false; return; }

            this._applyServerResponse(data);
            if (data.message) this.$store.toasts.push({ type: 'success', message: data.message });
            if (data.id) this.openEdit(data.id);
            this.submitting = false;
        },

        confirmDelete() {
            if (this.isEdit) this.confirmDeleteAccount(this.form.id);
        },

        /** Delete a specific account — from the row kebab or the editor. */
        confirmDeleteAccount(id) {
            const name = this.rowsById[id]?.name || 'this account';
            this.$store.confirm.show({
                title:        `Delete "${name}"?`,
                message:      'Only a custom account with no posted entries and no business mapping can be deleted.',
                intent:       'danger',
                confirmLabel: 'Delete account',
                cancelLabel:  'Cancel',
                onConfirm: async () => {
                    const url  = deleteUrlTemplate.replace('__ID__', id);
                    const data = await this._send(url, this._formWithMethod('DELETE'));
                    if (!data) return;
                    this._applyServerResponse(data);
                    if (this.form.id === id) this.closeEditor();
                    if (data.message) this.$store.toasts.push({ type: 'success', message: data.message });
                },
            });
        },

        // ── Group editor ───────────────────────────────────────────────

        openGroupNew() {
            this.mode        = 'group-new';
            this.groupForm   = blankGroup();
            this.fieldErrors = {};
            const all = Object.values(this.groupsById)
                .slice()
                .sort((a, b) => a.path_label.localeCompare(b.path_label));
            this._setParentOptions(all, '');
        },

        openGroupEdit(id) {
            const g = this.groupsById[id];
            if (!g) return;
            this.mode      = 'group-edit';
            this.groupForm = {
                id:         g.id,
                name:       g.name,
                parent_id:  g.parent_id ?? '',
                is_system:  g.is_system === true,
                can_delete: g.can_delete === true,
            };
            this.fieldErrors = {};

            // Valid new parents: same reporting type, not self or a descendant.
            const desc  = this._descendantIds(g.id);
            const valid = Object.values(this.groupsById)
                .filter((x) => x.type === g.type && x.id !== g.id && !desc.has(x.id))
                .sort((a, b) => a.path_label.localeCompare(b.path_label));
            this._setParentOptions(valid, g.parent_id ?? '');
        },

        submitGroup() {
            if (this.submitting) return;
            this.submitting = true;

            const isEdit = this.mode === 'group-edit';
            const url    = isEdit ? groupUpdateUrlTemplate.replace('__ID__', this.groupForm.id) : groupStoreUrl;
            const fields = {
                name:      this.groupForm.name ?? '',
                parent_id: this.parentTs ? this.parentTs.getValue() : (this.groupForm.parent_id ?? ''),
            };
            if (isEdit) fields._method = 'PATCH';

            // submitForm reloads the page on success (reflowing the whole tree);
            // on a 422 it toasts the guard message and rejects.
            submitForm(url, fields).catch(() => { this.submitting = false; });
        },

        confirmDeleteGroup() {
            if (!this.isGroupEdit) return;
            const name = this.groupForm.name || 'this group';
            this.$store.confirm.show({
                title:        `Delete "${name}"?`,
                message:      'Only an empty custom group can be deleted.',
                intent:       'danger',
                confirmLabel: 'Delete group',
                cancelLabel:  'Cancel',
                onConfirm: () => submitForm(
                    groupDeleteUrlTemplate.replace('__ID__', this.groupForm.id),
                    { _method: 'DELETE' },
                ),
            });
        },

        // ── Tree row helpers ───────────────────────────────────────────

        async toggleActive(id) {
            if (this.togglingIds.includes(id)) return;
            const row = this.rowsById[id];
            if (!row) return;
            const next = !row.is_active;

            this.togglingIds = [...this.togglingIds, id];

            const fd = this._formWithMethod('PATCH');
            fd.append('code',             row.code ?? '');
            fd.append('name',             row.name ?? '');
            fd.append('account_group_id', row.account_group_id ?? '');
            fd.append('is_active',        next ? '1' : '0');

            const data = await this._send(updateUrlTemplate.replace('__ID__', id), fd);
            this.togglingIds = this.togglingIds.filter((x) => x !== id);
            if (!data) return;

            this._applyServerResponse(data);
            if (data.message) this.$store.toasts.push({ type: 'success', message: data.message });
        },

        _applyServerResponse(data) {
            if (Array.isArray(data.rows)) {
                this.rowsById = Object.fromEntries(data.rows.map((r) => [r.id, r]));
            }
            if (data.tree_html) {
                const tree = this._rootEl.querySelector('.coa-tree');
                if (tree) {
                    tree.replaceChildren();
                    tree.insertAdjacentHTML('beforeend', data.tree_html);
                    if (window.Alpine) window.Alpine.initTree(tree);
                }
            }
        },

        async _send(url, body, formEl = null) {
            try {
                const { data } = await posPost(url, body);
                return data;
            } catch (e) {
                if (e?.status === 422) {
                    const errs = e.errors ?? {};
                    this.fieldErrors = Object.fromEntries(
                        Object.entries(errs).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]),
                    );
                    const messages = Object.values(errs).flat().filter(Boolean);
                    if (messages.length) {
                        this.$store.toasts.push({
                            type:  'error',
                            title: messages.length === 1 ? 'Please review the form' : 'Please fix the errors below',
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
    };
}
