import { posPost, applyValidationErrors } from '../lib/http.js';
import { openDrawer, DrawerKickUnavailable } from '../hardware/print-bridge.js';
import { terminalPrinterConfig } from '../hardware/terminal-config.js';

/**
 * Alpine factory behind the "Open drawer (no sale)" form on an open
 * shift (docs/features/hardware.md §8.4). Two things happen on submit:
 *
 *   1. Physical kick — only when the active terminal is configured for a
 *      WebUSB printer. We gate on the terminal's printer mode so non-
 *      thermal terminals never trigger a USB device picker.
 *   2. Audit — records a `drawer_open_no_sale` cash-drawer entry via the
 *      existing endpoint so the Z-report counts the open.
 *
 * In browser-print mode the printer never receives raw bytes, so there's
 * no kick to send — the cashier opens the drawer by hand (§8.3); we still
 * record the event.
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

                // Only nudge "open it manually" when a kick was expected
                // (WebUSB terminal) but couldn't be delivered.
                if (cfg.mode === 'webusb' && !kicked) {
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
