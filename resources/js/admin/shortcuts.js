/**
 * Global keyboard shortcuts for the admin shell.
 *   ⌘K  /  Ctrl-K          open command palette
 *   ⌘\  /  Ctrl-\          toggle sidebar collapse
 *   Esc                    close any open overlay (handled at component level)
 *
 * The palette opens by emitting a custom event; the palette component
 * listens for it (see admin-layout.blade.php).
 *
 * Note: a "jump to cashier" hotkey will land when the cashier screen
 * exists. It must NOT use Ctrl-Shift-R — that's the browser's universal
 * hard-refresh keybind.
 */
export function registerShortcuts(Alpine) {
    window.addEventListener('keydown', (e) => {
        const mod = e.metaKey || e.ctrlKey;
        if (!mod) return;

        // Skip when typing into an input/textarea (palette opens itself there).
        const target = e.target;
        const isField = target && (target.matches('input, textarea, [contenteditable]'));

        // ⌘K — open command palette (allowed even inside fields).
        if (e.key === 'k' || e.key === 'K') {
            e.preventDefault();
            window.dispatchEvent(new CustomEvent('pos:cmd-open'));
            return;
        }

        if (isField) return;

        // ⌘\  — toggle sidebar
        if (e.key === '\\') {
            e.preventDefault();
            Alpine.store('sidebar').toggle();
            return;
        }
    });
}
