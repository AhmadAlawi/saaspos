/**
 * WebUSB thermal-printer driver (docs/features/hardware.md §5.3-5.5).
 *
 * Chromium browsers (Chrome / Edge / Opera, desktop + Android) expose
 * `navigator.usb`, letting us push raw ESC/POS bytes straight to a USB
 * thermal printer — no driver, no print dialog, drawer-kick included.
 * Everything here is best-effort: any failure throws and the bridge
 * falls back to browser-print.
 */

/** Known thermal-printer vendor IDs — keeps the device picker tidy. */
export const KNOWN_PRINTER_FILTERS = [
    { vendorId: 0x04b8 }, // Epson
    { vendorId: 0x0519 }, // Star Micronics
    { vendorId: 0x0fe6 }, // Bixolon
    { vendorId: 0x1504 }, // Bixolon (alt)
    { vendorId: 0x067b }, // Prolific (some generic ESC/POS)
    { vendorId: 0x28e9 }, // Generic Chinese thermal
    { classCode: 7 },     // USB Printer Class — catches generic ESC/POS
];

export function webUsbSupported() {
    return typeof navigator !== 'undefined' && !!navigator.usb;
}

/** Decode a base64 ESC/POS payload into a byte array. */
export function base64ToUint8Array(b64) {
    const binary = atob(b64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
    return bytes;
}

/**
 * Resolve a printer the browser has already been granted access to,
 * preferring one matching the saved vendor/product descriptor.
 */
async function getPairedDevice(descriptor = null) {
    if (!webUsbSupported()) return null;
    const devices = await navigator.usb.getDevices();
    if (!devices.length) return null;

    if (descriptor?.vendor_id) {
        const vid = parseInt(descriptor.vendor_id, 16) || Number(descriptor.vendor_id);
        const pid = descriptor.product_id
            ? (parseInt(descriptor.product_id, 16) || Number(descriptor.product_id))
            : null;
        const match = devices.find(
            (d) => d.vendorId === vid && (pid == null || d.productId === pid),
        );
        if (match) return match;
    }
    return devices[0];
}

/** Show the browser device picker (must run inside a user gesture). */
async function requestDevice() {
    return navigator.usb.requestDevice({ filters: KNOWN_PRINTER_FILTERS });
}

/** Find the first bulk-OUT endpoint so we transfer on the right number. */
function findOutEndpoint(device) {
    const cfg = device.configuration;
    for (const iface of cfg?.interfaces ?? []) {
        for (const alt of iface.alternates ?? []) {
            const ep = alt.endpoints?.find((e) => e.direction === 'out');
            if (ep) return { interfaceNumber: iface.interfaceNumber, endpoint: ep.endpointNumber };
        }
    }
    // Sensible default for the overwhelming majority of ESC/POS printers.
    return { interfaceNumber: 0, endpoint: 1 };
}

/**
 * Open/claim a paired (or freshly picked) printer, push the given bytes
 * in bulk-OUT chunks, then release. The low-level primitive shared by
 * receipt printing and the standalone cash-drawer kick.
 *
 * @param {Uint8Array} bytes
 * @param {object} [printerConfig]  receipt_printer config (may hold a saved descriptor)
 */
export async function sendBytesViaWebUsb(bytes, printerConfig = {}) {
    if (!webUsbSupported()) throw new Error('WebUSB not supported in this browser.');

    let device = await getPairedDevice(printerConfig.webusb_device_descriptor);
    if (!device) device = await requestDevice();

    await device.open();
    if (device.configuration === null) await device.selectConfiguration(1);

    const { interfaceNumber, endpoint } = findOutEndpoint(device);
    await device.claimInterface(interfaceNumber);

    try {
        // Chunked transfer — large payloads (logos) can exceed a single
        // bulk packet, and some printers choke on oversized writes.
        for (let i = 0; i < bytes.length; i += 1024) {
            await device.transferOut(endpoint, bytes.slice(i, i + 1024));
        }
    } finally {
        try { await device.releaseInterface(interfaceNumber); } catch (_) { /* noop */ }
        try { await device.close(); } catch (_) { /* noop */ }
    }
}

/**
 * Send a base64 ESC/POS payload to a WebUSB printer.
 *
 * @param {string} escposBase64
 * @param {object} [printerConfig]  receipt_printer config (may hold a saved descriptor)
 */
export async function printViaWebUsb(escposBase64, printerConfig = {}) {
    await sendBytesViaWebUsb(base64ToUint8Array(escposBase64), printerConfig);
}
