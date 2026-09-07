/**
 * Command palette (⌘K) Alpine factory.
 *
 * Searches across server-provided indexes: pages (sidebar nav items),
 * plus any future dynamic indexes (products, orders, customers).
 * For now the index source is window.POS_CMD_INDEX, populated by the
 * admin layout — see resources/views/components/admin/command-palette.blade.php.
 */
export function commandPalette() {
    return {
        open: false,
        query: '',
        scope: 'all',
        activeIndex: 0,

        scopes: [
            { id: 'all',       label: 'All' },
            { id: 'pages',     label: 'Pages' },
            { id: 'products',  label: 'Products' },
            { id: 'orders',    label: 'Orders' },
            { id: 'customers', label: 'Customers' },
        ],

        show()  { this.open = true;  this.query = ''; this.activeIndex = 0;
                  this.$nextTick(() => this.$refs.input?.focus()); },
        hide()  { this.open = false; },
        toggle(){ this.open ? this.hide() : this.show(); },

        /* The index lives on window so it can be hydrated server-side
           without duplicating the schema in JS. */
        get index() { return window.POS_CMD_INDEX || []; },

        get filtered() {
            const q = this.query.trim().toLowerCase();
            return this.index.filter(row => {
                if (this.scope !== 'all' && row.scope !== this.scope) return false;
                if (q === '') return true;
                return (row.title?.toLowerCase().includes(q)
                     || row.sub?.toLowerCase().includes(q));
            });
        },
        get grouped() {
            const groups = {};
            for (const row of this.filtered) {
                const g = row.group || 'Other';
                (groups[g] ||= []).push(row);
            }
            return groups;
        },
        countFor(scopeId) {
            if (scopeId === 'all') return this.index.length;
            return this.index.filter(r => r.scope === scopeId).length;
        },

        move(direction) {
            const max = this.filtered.length - 1;
            if (max < 0) return;
            this.activeIndex = (this.activeIndex + direction + max + 1) % (max + 1);
        },
        activate() {
            const row = this.filtered[this.activeIndex];
            if (row?.href) window.location.href = row.href;
        },
        setScope(id) { this.scope = id; this.activeIndex = 0; },
    };
}
