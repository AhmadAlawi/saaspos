/**
 * Topbar "End of day" summary popover. Fetches today's figures (sales,
 * refunds, payment mix, money out, ops counters) for the active store each
 * time it's opened, so the admin can glance at how the day went from any
 * screen. Mirrors notificationsPanel; money is formatted with the shared
 * currency formatter.
 */
export function daySummaryPanel(initial = {}) {
    return {
        open:   false,
        url:    initial.url || '',
        data:   null,
        busy:   false,

        toggle() {
            this.open = !this.open;
            if (this.open) this.load();   // refresh on every open — it's "live"
        },

        close() {
            this.open = false;
        },

        async load() {
            this.busy = true;
            try {
                const { data } = await this.$http.get(this.url);
                this.data = data;
            } catch (e) {
                // Silent — keep the last snapshot (if any).
            } finally {
                this.busy = false;
            }
        },

        money(v) {
            return window.posFormatMoney ? window.posFormatMoney(Number(v) || 0) : String(v ?? 0);
        },
        qty(v) {
            return window.posFormatQty ? window.posFormatQty(Number(v) || 0) : String(v ?? 0);
        },
    };
}
