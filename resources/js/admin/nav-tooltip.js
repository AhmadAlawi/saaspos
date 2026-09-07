/**
 * Sidebar nav tooltip factory.
 *
 * The CSS-only `::after` tooltip pattern can't be used here: nav items live
 * inside `.nav-scroll` which has `overflow-y: auto`, and the browser forces
 * `overflow-x` to compute as `auto` on the same element, clipping anything
 * extending past the sidebar's right edge.
 *
 * Workaround: one shared `.nav-tooltip` element rendered as a sibling of
 * `.nav-scroll` (so outside the clipping container). On mouseenter of a
 * nav-item we read its data-tip + bounding rect and position the shared
 * tooltip via a CSS custom property. Only visible while the sidebar is in
 * collapsed mode (body.sb-collapsed) — open mode shows the label inline.
 *
 *   <aside x-data="navTooltip">
 *     <div class="nav-tooltip" :class="{'is-visible': tipVisible}"
 *          :style="`--tip-y: ${tipY}px`" x-text="tipText"></div>
 *     ...
 *     <a class="nav-item" data-tip="Sales"
 *        @mouseenter="showTip($event)" @mouseleave="hideTip()">…</a>
 *   </aside>
 */
export function navTooltip() {
    return {
        tipVisible: false,
        tipText: '',
        tipY: 0,
        _timer: null,

        showTip(event) {
            // Tooltip only makes sense when the sidebar is collapsed.
            if (!document.body.classList.contains('sb-collapsed')) return;

            const navItem    = event.currentTarget;
            const sidebar    = navItem.closest('.admin-sidebar');
            if (!sidebar) return;

            const itemRect    = navItem.getBoundingClientRect();
            const sidebarRect = sidebar.getBoundingClientRect();

            this.tipText = navItem.dataset.tip || '';
            this.tipY    = (itemRect.top - sidebarRect.top) + (itemRect.height / 2);

            clearTimeout(this._timer);
            this._timer = setTimeout(() => { this.tipVisible = true; }, 200);
        },

        hideTip() {
            clearTimeout(this._timer);
            this.tipVisible = false;
        },
    };
}
