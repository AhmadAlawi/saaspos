/**
 * Alpine stores specific to the admin shell.
 *
 *  $store.sidebar  — collapsed / mobileOpen state, persisted to localStorage.
 *                    The shell CSS reads body.sb-* classes, so syncBody()
 *                    mirrors Alpine state into the DOM on every change.
 *
 * The global $store.ui (theme) lives in resources/js/stores/ui.js and is
 * registered from app.js so non-admin pages (auth, installer, cashier) get
 * the same theme behaviour without pulling in the rest of the admin module.
 */
export function registerAdminStores(Alpine) {
    Alpine.store('sidebar', {
        collapsed: false,
        mobileOpen: false,

        init() {
            try {
                this.collapsed = localStorage.getItem('pos_sidebar_collapsed') === '1';
            } catch (e) {}
            this.syncBody();
        },

        toggle() {
            this.collapsed = !this.collapsed;
            try { localStorage.setItem('pos_sidebar_collapsed', this.collapsed ? '1' : '0'); } catch (e) {}
            this.syncBody();
        },

        openMobile()  { this.mobileOpen = true;  this.syncBody(); },
        closeMobile() { this.mobileOpen = false; this.syncBody(); },

        /* Sidebar CSS reads body classes, not Alpine state — mirror them. */
        syncBody() {
            const b = document.body;
            b.classList.toggle('sb-collapsed',   this.collapsed);
            b.classList.toggle('sb-mobile-open', this.mobileOpen);
        },
    });
}
