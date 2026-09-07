/**
 * The print bridge (docs/features/hardware.md §5.1, §5.3).
 *
 * One entry point — `printReceipt(payload)` — that picks the best path
 * for the current terminal: WebUSB when the payload says so (and the
 * browser supports it), falling back to browser-print otherwise, and
 * also if a WebUSB attempt throws mid-flight (printer unplugged, etc.).
 *
 * `payload` is exactly what the server's PreparePrintPayload action
 * returns: { mode, paper, html, escpos_bytes }.
 *
 * Resolves to the path actually used ('webusb' | 'browser_print') so the
 * caller can record an accurate print log.
 */
import { printViaWebUsb, sendBytesViaWebUsb, webUsbSupported } from './webusb-driver.js';
import { printViaBrowserPrint } from './browser-print-driver.js';

export async function printReceipt(payload, printerConfig = {}) {
    if (!payload) throw new Error('No print payload.');

    const wantsWebUsb = payload.mode === 'webusb' && webUsbSupported() && !!payload.escpos_bytes;

    if (wantsWebUsb) {
        try {
            await printViaWebUsb(payload.escpos_bytes, printerConfig);
            return 'webusb';
        } catch (e) {
            // WebUSB failed (no device, cancelled picker, transfer error) —
            // fall back so the customer still gets a receipt.
            console.warn('[print-bridge] WebUSB failed, falling back to browser-print', e);
        }
    }

    await printViaBrowserPrint(payload.html);
    return 'browser_print';
}

/** Convenience for callers that only know they want browser-print. */
export async function printHtml(html) {
    await printViaBrowserPrint(html);
    return 'browser_print';
}

/**
 * Thrown by openDrawer() when the kick can't be sent (no WebUSB). The
 * caller treats this as "record the event, tell the cashier to open the
 * drawer by hand" rather than a hard failure (docs §8.3).
 */
export class DrawerKickUnavailable extends Error {
    constructor(message = 'Drawer kick not available in this mode.') {
        super(message);
        this.name = 'DrawerKickUnavailable';
    }
}

/**
 * Build the ESC/POS generate-pulse command (ESC p m t1 t2) that opens a
 * drawer wired to the printer's RJ-11 port. `pin` 5 toggles the second
 * connector; everything else uses pin 2 (the common default).
 */
export function drawerKickBytes(pin = 2) {
    const m = pin === 5 ? 0x01 : 0x00;
    return new Uint8Array([0x1b, 0x70, m, 0x32, 0xfa]);
}

/**
 * Open the cash drawer by sending just the kick command to the printer
 * over WebUSB. There is no browser-print equivalent — without raw byte
 * access the printer never gets the pulse — so callers should fall back
 * to "open the drawer manually" when this throws DrawerKickUnavailable.
 *
 * @param {object} [printerConfig]  receipt_printer config (descriptor + drawer_pin)
 * @returns {Promise<'webusb'>}
 */
export async function openDrawer(printerConfig = {}) {
    if (!webUsbSupported()) {
        throw new DrawerKickUnavailable();
    }
    const pin = Number(printerConfig.drawer_pin) === 5 ? 5 : 2;
    await sendBytesViaWebUsb(drawerKickBytes(pin), printerConfig);
    return 'webusb';
}
