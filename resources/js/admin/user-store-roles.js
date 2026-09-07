/**
 * Repeatable "store + role" rows for the user form. A user holds one
 * role per store; this manages the editable list. Each row serializes as
 * stores[i][store_id] / stores[i][role_id] via :name bindings.
 *
 *   x-data="userStoreRoles({ rows: [{store_id, role_id}, …] })"
 */
export function userStoreRoles(config = {}) {
    // Stable per-row id so the x-for key survives add/remove — required for
    // the enhancedSelect (TomSelect) instances inside each row to stay in
    // sync instead of showing a stale chip.
    let uid = 0;
    const make = (r = {}) => ({
        _uid: uid++,
        store_id: String(r.store_id ?? ''),
        role_id: String(r.role_id ?? ''),
    });

    return {
        rows: (config.rows && config.rows.length) ? config.rows.map(make) : [make()],

        addRow() {
            this.rows.push(make());
        },

        removeRow(i) {
            this.rows.splice(i, 1);
            if (this.rows.length === 0) {
                this.addRow();
            }
        },
    };
}
