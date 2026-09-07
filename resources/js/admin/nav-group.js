/**
 * Collapsible sidebar sub-group (Catalog setup, Inventory reports, …).
 *
 * Sections themselves stay flat — only the read-only / configure-once tails
 * nest into one of these. A group holding the current page always starts
 * open regardless of what was stored, so you can never land on a page whose
 * nav row is hidden. Otherwise the last open/closed choice is remembered per
 * group in localStorage.
 *
 * Height is animated by hand (0 ↔ scrollHeight ↔ auto) rather than with
 * @alpinejs/collapse — the plugin isn't a dependency and this is ~15 lines.
 * `auto` is restored once the open transition finishes so a group whose
 * children change height (permission-filtered rows, badges) still fits.
 *
 *   <div x-data="navGroup({ id: 'inventory-reports', active: false })">
 *     <button @click="toggle()" :aria-expanded="open.toString()">…</button>
 *     <div class="nav-subgroup-body" x-ref="body">…</div>
 *   </div>
 */
export function navGroup(config = {}) {
    return {
        open: false,

        init() {
            const stored = this._read();

            // Active child wins over the stored preference.
            this.open = config.active === true ? true : (stored ?? false);

            // Paint the initial height without animating on first load.
            this.$nextTick(() => this._resize(false));
        },

        toggle() {
            this.open = !this.open;
            this._write(this.open);
            this._resize(true);
        },

        /** @param {boolean} animate */
        _resize(animate) {
            const el = this.$refs.body;
            if (!el) return;

            if (!animate) {
                el.style.height = this.open ? 'auto' : '0px';
                return;
            }

            if (this.open) {
                el.style.height = '0px';
                void el.offsetHeight;               // force reflow so 0 → N animates
                el.style.height = `${el.scrollHeight}px`;

                const settle = () => {
                    el.style.height = 'auto';       // let it grow with its content
                    el.removeEventListener('transitionend', settle);
                };
                el.addEventListener('transitionend', settle);
            } else {
                el.style.height = `${el.scrollHeight}px`;
                void el.offsetHeight;
                el.style.height = '0px';
            }
        },

        _key() {
            return `pos.nav.group.${config.id || 'unknown'}`;
        },

        /** @return {boolean|null} null = never chosen, fall back to closed. */
        _read() {
            try {
                const raw = localStorage.getItem(this._key());
                return raw === null ? null : raw === '1';
            } catch {
                return null;                        // private mode / storage disabled
            }
        },

        _write(value) {
            try {
                localStorage.setItem(this._key(), value ? '1' : '0');
            } catch {
                /* non-fatal — the group just won't remember next visit */
            }
        },
    };
}
