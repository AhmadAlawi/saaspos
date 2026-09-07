/**
 * Shared "click-outside-to-close" dropdown factory used by the topbar
 * notifications bell, profile menu, and any other anchored dropdown.
 *
 * In Blade:
 *   <div x-data="dropdown">
 *     <button @click="toggle" :class="{ 'is-open': open }">…</button>
 *     <div x-show="open" @click.outside="close">…</div>
 *   </div>
 */
export function dropdown() {
    return {
        open: false,
        toggle() { this.open = !this.open; },
        close()  { this.open = false; },
    };
}
