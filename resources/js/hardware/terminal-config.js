/**
 * Reads the active terminal's receipt-printer config, published by the
 * admin layout as `<meta name="pos-terminal-printer">`. Returns `{}` when
 * no terminal is selected or it has no printer configured — callers then
 * fall back to browser-print / manual-drawer behaviour.
 */
export function terminalPrinterConfig() {
    const raw = document.querySelector('meta[name="pos-terminal-printer"]')?.content;
    if (!raw) return {};
    try {
        const cfg = JSON.parse(raw);
        return cfg && typeof cfg === 'object' ? cfg : {};
    } catch (_) {
        return {};
    }
}

/** The configured print mode for the active terminal (default browser-print). */
export function terminalPrinterMode() {
    return terminalPrinterConfig().mode || 'browser_print';
}
