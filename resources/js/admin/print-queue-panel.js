import {
    listFailedPrints,
    removeFailedPrint,
    bumpAttempt,
    MAX_SILENT_RETRIES,
} from '../hardware/print-queue.js';
import { printReceipt } from '../hardware/print-bridge.js';
import { terminalPrinterConfig } from '../hardware/terminal-config.js';

/**
 * Topbar failed-print queue (docs/features/hardware.md §9.2-9.3).
 *
 * Lists prints that failed and parks them for retry. A silent loop
 * re-attempts each every 30s until it succeeds or hits the retry cap, at
 * which point it waits for the operator to fix the printer and retry by
 * hand. Reuses the print bridge, so a retry is a no-server-round-trip
 * re-send of the stored payload.
 */
export function printQueuePanel() {
    return {
        open:      false,
        items:     [],
        retrying:  [],   // ids currently being retried
        _timer:    null,
        _onChange: null,

        get count() { return this.items.length; },

        async init() {
            await this.refresh();

            // Refresh whenever the queue changes (a new failure enqueued,
            // an item removed) — print-queue.js dispatches this event.
            this._onChange = () => this.refresh();
            window.addEventListener('pos:print-queue-changed', this._onChange);

            // Silent auto-retry every 30s.
            this._timer = setInterval(() => this.autoRetry(), 30000);

            this.$watch('open', (isOpen) => { if (isOpen) this.refresh(); });
        },

        destroy() {
            if (this._timer) clearInterval(this._timer);
            if (this._onChange) window.removeEventListener('pos:print-queue-changed', this._onChange);
        },

        toggle() { this.open = !this.open; },
        close()  { this.open = false; },

        async refresh() {
            this.items = await listFailedPrints();
        },

        /** Re-send one queued print. Silent retries don't toast. */
        async retry(id, { silent = false } = {}) {
            if (this.retrying.includes(id)) return false;
            const item = this.items.find((i) => i.id === id);
            if (!item) return false;

            this.retrying = [...this.retrying, id];
            let success = false;
            try {
                await printReceipt(item.payload, terminalPrinterConfig());
                await removeFailedPrint(id);
                success = true;
                if (!silent) {
                    this.$store?.toasts?.push({ type: 'success', message: 'Reprinted successfully.' });
                }
            } catch (e) {
                await bumpAttempt(id, e?.message ?? 'Retry failed');
                if (!silent) {
                    this.$store?.toasts?.push({ type: 'error', message: 'Still can\'t reach the printer.' });
                }
            } finally {
                this.retrying = this.retrying.filter((x) => x !== id);
                await this.refresh();
            }
            return success;
        },

        /** Silent sweep — retry everything still under the attempt cap. */
        async autoRetry() {
            const due = this.items.filter((i) => (i.attempts || 0) < MAX_SILENT_RETRIES);
            for (const item of due) {
                // Stop early if one succeeds-then-removes mid-loop; refresh
                // keeps `items` current between attempts.
                await this.retry(item.id, { silent: true }); // eslint-disable-line no-await-in-loop
            }
        },

        async discard(id) {
            await removeFailedPrint(id);
            await this.refresh();
        },

        /** True once an item has exhausted its silent retries. */
        isStalled(item) {
            return (item.attempts || 0) >= MAX_SILENT_RETRIES;
        },
    };
}
