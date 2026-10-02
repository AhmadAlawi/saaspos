import { posPost, applyValidationErrors } from '../lib/http.js';
import { openDrawer, printReceipt, DrawerKickUnavailable } from '../hardware/print-bridge.js';
import { terminalPrinterConfig } from '../hardware/terminal-config.js';

/**
 * Alpine factory behind the "Open drawer (no sale)" form on an open
 * shift (docs/features/hardware.md §8.4). Two things happen on submit:
 *
 *   1. Physical kick — WebUSB when the terminal's configured for it;
 *      otherwise `printReceipt()` against the server-resolved
 *      `print_payload` (same bridge + same PrinterConfig::fromTerminal
 *      resolution the sale receipt uses), which pops the drawer as a
 *      hardware side-effect on a printer with it wired into the RJ-11
 *      port (browser-print has no byte-level command of its own — see
 *      openDrawer()'s docblock).
 *   2. Audit — records a `drawer_open_no_sale` cash-drawer entry via the
 *      existing endpoint so the Z-report counts the open.
 */
export function drawerOpener({ recordUrl } = {}) {
    return {
        busy: false,

        async open(evt) {
            if (this.busy) return;
            const form = evt.target;
            const reason = form.querySelector('[name="reason"]')?.value?.trim() ?? '';
            this.busy = true;

            const cfg = terminalPrinterConfig();
            let kicked = false;
            if (cfg.mode === 'webusb') {
                try {
                    await openDrawer(cfg);
                    kicked = true;
                } catch (e) {
                    if (!(e instanceof DrawerKickUnavailable)) {
                        console.warn('[drawer] kick failed', e);
                    }
                }
            }

            try {
                const { data } = await posPost(recordUrl, { type: 'drawer_open_no_sale', reason });

                this.$store.toasts.push({
                    type:    'success',
                    message: data?.message ?? 'Drawer open recorded.',
                });

                // No WebUSB kick — fall back to printing the server-
                // resolved slip through the same bridge checkout uses,
                // which pops the drawer as a hardware side-effect on a
                // printer wired for it.
                if (!kicked && data?.print_payload) {
                    try {
                        await printReceipt(data.print_payload, cfg);
                        kicked = true;
                    } catch (e) {
                        console.warn('[drawer] slip print failed', e);
                    }
                }

                if (!kicked) {
                    this.$store.toasts.push({
                        type:    'info',
                        message: 'Couldn\'t reach the printer — open the drawer manually.',
                    });
                }

                // Refresh so the entries log + X-report numbers update,
                // matching the pay-in / pay-out forms' redirect behaviour.
                setTimeout(() => window.location.reload(), 600);
            } catch (e) {
                this.busy = false;
                if (e?.status === 422) {
                    const errs = e.errors ?? {};
                    const messages = Object.values(errs).flat().filter(Boolean);
                    this.$store.toasts.push({ type: 'error', title: 'Please review the form', messages });
                    applyValidationErrors(form, errs);
                } else {
                    this.$store.toasts.push({
                        type:    'error',
                        message: e?.message ?? 'Could not record the drawer open.',
                    });
                }
            }
        },
    };
}
