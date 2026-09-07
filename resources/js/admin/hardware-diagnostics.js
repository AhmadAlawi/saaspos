import { posPost } from '../lib/http.js';
import { printReceipt, openDrawer, DrawerKickUnavailable } from '../hardware/print-bridge.js';
import { terminalPrinterConfig } from '../hardware/terminal-config.js';

/**
 * Hardware diagnostics page (docs/features/hardware.md §10). Runs
 * non-destructive checks against the active terminal's peripherals:
 * fetches a test-print payload and sends it through the bridge, kicks the
 * drawer, and reports camera availability.
 */
export function hardwareDiagnostics({ testPrintUrl } = {}) {
    return {
        testingPrint:  false,
        testingDrawer: false,
        cameraSupported: !!(typeof navigator !== 'undefined'
            && navigator.mediaDevices
            && navigator.mediaDevices.getUserMedia),

        async testPrint() {
            if (this.testingPrint) return;
            this.testingPrint = true;
            try {
                const { data } = await posPost(testPrintUrl, {});
                const mode = await printReceipt(data, terminalPrinterConfig());
                this.$store.toasts.push({
                    type: 'success',
                    message: mode === 'webusb' ? 'Test sent to the thermal printer.' : 'Test sent to the browser print dialog.',
                });
            } catch (e) {
                this.$store.toasts.push({ type: 'error', message: 'Test print failed. Check the printer connection.' });
            } finally {
                this.testingPrint = false;
            }
        },

        async testDrawer() {
            if (this.testingDrawer) return;
            this.testingDrawer = true;
            try {
                await openDrawer(terminalPrinterConfig());
                this.$store.toasts.push({ type: 'success', message: 'Drawer kick sent.' });
            } catch (e) {
                if (e instanceof DrawerKickUnavailable) {
                    this.$store.toasts.push({ type: 'info', message: 'Drawer kick needs a WebUSB thermal printer. Open the drawer manually.' });
                } else {
                    this.$store.toasts.push({ type: 'error', message: 'Could not reach the printer to kick the drawer.' });
                }
            } finally {
                this.testingDrawer = false;
            }
        },
    };
}
