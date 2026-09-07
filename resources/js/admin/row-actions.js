/**
 * Per-row action menu (the "⋮" kebab) used in every admin list table.
 *
 * Why this exists instead of the plain `dropdown` factory: list rows live
 * inside `.dt-scroll` (`overflow-x: auto`) and the admin content scrolls in
 * an inner `.page` container (`overflow-y: auto`), both of which clip a
 * normally-positioned absolute panel. This menu is `position: fixed` with
 * JS-computed coordinates anchored to the trigger, so it escapes every clip.
 * It also:
 *   - flips above the trigger when there isn't room below,
 *   - aligns to the trigger's inline-end edge (RTL-aware),
 *   - FOLLOWS the trigger as ANY ancestor scrolls and closes once the trigger
 *     leaves the viewport,
 *   - closes on outside-click and Escape,
 *   - guarantees only one row menu is open at a time.
 *
 * "Follow on scroll" is driven two ways so it can't silently fail:
 *   1. scroll/resize listeners bound to every scrollable ancestor of the
 *      trigger + window (the reliable signal — fires on the element that
 *      actually scrolls, capture so it's caught even though scroll doesn't
 *      bubble), and
 *   2. a `requestAnimationFrame` loop while open (covers layout shifts that
 *      don't emit a scroll event).
 * Both just call `_position()`, which is a no-op when nothing moved.
 *
 * Markup contract (see <x-admin.row-actions> / <x-admin.row-action>):
 *   <div class="row-actions" x-data="rowActionsMenu">
 *     <button x-ref="trigger" @click.stop="toggle()">⋮</button>
 *     <div class="row-actions-menu" x-ref="menu" x-show="open">…items…</div>
 *   </div>
 */
const CLOSE_ALL_EVENT = 'row-actions:close-all';

/** Collect every scrollable ancestor of `el` (for scroll-follow listeners). */
function scrollParents(el) {
    const parents = [];
    let node = el?.parentElement;
    while (node) {
        const s = getComputedStyle(node);
        if (/(auto|scroll|overlay)/.test(s.overflow + s.overflowX + s.overflowY)) {
            parents.push(node);
        }
        node = node.parentElement;
    }
    return parents;
}

export function rowActionsMenu() {
    return {
        open: false,
        _raf: null,
        _scrollers: [],
        _lastKey: '',

        toggle() {
            if (this.open) { this.close(); return; }
            // Close any other open row menu first.
            window.dispatchEvent(new CustomEvent(CLOSE_ALL_EVENT, { detail: { source: this.$el } }));
            this.open = true;
            this.$nextTick(() => {
                this._position(true);
                this._bindFollow();
            });
        },

        close() {
            if (! this.open) return;
            this.open = false;
            this._unbindFollow();
        },

        _bindFollow() {
            // 1) Listen on the real scroll ancestors + window.
            this._scrollers = [...scrollParents(this.$refs.trigger), window];
            this._scrollers.forEach((s) => s.addEventListener('scroll', this._onFollow, { passive: true }));
            window.addEventListener('resize', this._onFollow);
            // 2) rAF loop as a safety net for non-scroll layout shifts.
            this._loop();
        },

        _unbindFollow() {
            this._scrollers.forEach((s) => s.removeEventListener('scroll', this._onFollow));
            this._scrollers = [];
            window.removeEventListener('resize', this._onFollow);
            if (this._raf) { cancelAnimationFrame(this._raf); this._raf = null; }
        },

        _loop() {
            if (! this.open) return;
            this._position();
            this._raf = requestAnimationFrame(() => this._loop());
        },

        /**
         * Anchor the fixed menu to the trigger. Skips DOM writes when nothing
         * moved (cheap to call every frame / scroll tick). Closes the menu when
         * the trigger has scrolled out of the viewport. `force` re-applies even
         * if the cached position key matches (used on first open).
         */
        _position(force = false) {
            const btn  = this.$refs.trigger;
            const menu = this.$refs.menu;
            if (!btn || !menu) return;

            const r = btn.getBoundingClientRect();

            // Trigger scrolled out of view → close.
            if (r.bottom <= 0 || r.top >= window.innerHeight
                || r.right <= 0 || r.left >= window.innerWidth) {
                this.close();
                return;
            }

            const mw  = menu.offsetWidth;
            const mh  = menu.offsetHeight;
            const gap = 4;
            const pad = 8; // keep clear of the viewport edge
            const rtl = (document.documentElement.dir || document.dir) === 'rtl';

            // Align the menu's inline-end edge to the trigger's inline-end edge.
            let left = rtl ? r.left : (r.right - mw);
            left = Math.min(Math.max(pad, left), window.innerWidth - mw - pad);

            // Default below the trigger; flip above when it would overflow.
            let top = r.bottom + gap;
            if (top + mh > window.innerHeight - pad) {
                const above = r.top - gap - mh;
                top = above >= pad ? above : Math.max(pad, window.innerHeight - mh - pad);
            }

            top  = Math.round(top);
            left = Math.round(left);

            const key = `${top}:${left}`;
            if (!force && key === this._lastKey) return;
            this._lastKey = key;

            menu.style.top  = `${top}px`;
            menu.style.left = `${left}px`;
        },

        init() {
            this._onFollow = () => this._position();
            this._onCloseAll = (e) => {
                // Don't close the menu that just asked everyone else to close.
                if (e.detail?.source === this.$el) return;
                this.close();
            };
            window.addEventListener(CLOSE_ALL_EVENT, this._onCloseAll);
        },

        destroy() {
            this._unbindFollow();
            window.removeEventListener(CLOSE_ALL_EVENT, this._onCloseAll);
        },
    };
}
