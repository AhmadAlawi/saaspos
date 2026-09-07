import { posGet, posPost } from '../lib/http.js';
import { printReceipt } from '../hardware/print-bridge.js';
import { enqueueFailedPrint } from '../hardware/print-queue.js';

/**
 * Alpine factory behind <x-print-button> (docs/features/hardware.md
 * §15.1). On click it fetches the sale's dual-format print payload,
 * hands it to the print bridge (WebUSB → browser-print), and reports the
 * outcome to the print-log endpoint.
 *
 * Browser-print dialog cancellation isn't observable (§16.3), so that
 * path is always logged as a success — matching what the system can
 * actually know.
 */
export function printButton({
    payloadUrl,
    logUrl,
    referenceType = 'Sale',
    referenceId = null,
    referenceLabel = '',
} = {}) {
    return {
        printing: false,

        async print() {
            if (this.printing) return;
            this.printing = true;

            let payload = null;
            try {
                const { data } = await posGet(payloadUrl);
                payload = data;
            } catch (e) {
                this.$store.toasts.push({ type: 'error', message: 'Could not load the receipt.' });
                this.printing = false;
                return;
            }

            let mode = 'browser_print';
            let ok = true;
            let queued = false;
            let errorMessage = null;
            try {
                mode = await printReceipt(payload);
            } catch (e) {
                ok = false;
                errorMessage = e?.message ?? 'Print failed';
                // Park the failed print so it isn't lost — the topbar queue
                // surfaces it for retry once the printer is back.
                try {
                    await enqueueFailedPrint({
                        reference_type: referenceType,
                        reference_id:   referenceId,
                        label:          referenceLabel,
                        payload,
                        error:          errorMessage,
                    });
                    queued = true;
                    this.$store.toasts.push({ type: 'error', message: 'Print failed — added to the print queue to retry.' });
                } catch (_) {
                    this.$store.toasts.push({ type: 'error', message: 'Print failed. Check the printer and try again.' });
                }
            }

            // Best-effort audit log — never blocks the UI.
            try {
                await posPost(logUrl, {
                    reference_type: referenceType,
                    reference_id:   referenceId,
                    printer_type:   'receipt',
                    mode,
                    status:         ok ? 'success' : (queued ? 'queued' : 'failed'),
                    error_message:  errorMessage,
                    // base64 → byte length, for capacity tracking.
                    bytes_size:     payload?.escpos_bytes ? Math.floor((payload.escpos_bytes.length * 3) / 4) : null,
                });
            } catch (_) { /* logging is best-effort */ }

            this.printing = false;
        },
    };
}
