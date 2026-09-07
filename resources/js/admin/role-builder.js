/**
 * Alpine factory for the role permission matrix.
 *
 * `selected` holds the chosen permission ids (as strings). The visible
 * checkboxes are UI only — they drive `selected` via toggle(); the form
 * serializes `selected` through hidden inputs (an x-for), so collapsed
 * groups still submit correctly.
 *
 *   x-data="roleBuilder({ selected: [...], map: { roleId: [ids] } })"
 */
export function roleBuilder(config = {}) {
    return {
        selected: (config.selected || []).map(String),
        closedGroups: {},          // group → true when collapsed (open by default)

        isSelected(id) {
            return this.selected.includes(String(id));
        },

        toggle(id) {
            id = String(id);
            this.selected = this.isSelected(id)
                ? this.selected.filter((x) => x !== id)
                : [...this.selected, id];
        },

        groupSelectedCount(ids) {
            const set = new Set(this.selected);
            return ids.reduce((n, id) => n + (set.has(String(id)) ? 1 : 0), 0);
        },

        isGroupAll(ids) {
            return ids.length > 0 && this.groupSelectedCount(ids) === ids.length;
        },

        isGroupSome(ids) {
            const c = this.groupSelectedCount(ids);
            return c > 0 && c < ids.length;
        },

        toggleGroup(ids) {
            const strIds = ids.map(String);
            if (this.isGroupAll(ids)) {
                const drop = new Set(strIds);
                this.selected = this.selected.filter((x) => !drop.has(x));
            } else {
                this.selected = [...new Set([...this.selected, ...strIds])];
            }
        },

        isOpen(group) {
            return this.closedGroups[group] !== true;
        },

        toggleOpen(group) {
            this.closedGroups[group] = !this.closedGroups[group];
        },

        get selectedCount() {
            return this.selected.length;
        },
    };
}
