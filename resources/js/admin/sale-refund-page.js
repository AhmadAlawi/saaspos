/**
 * Alpine factory for /admin/sales/{id}/refund.
 *
 * Drives the line picker — per-row refund qty (clamped to the
 * remaining returnable amount), per-row restock override, header
 * restock toggle, and the live totals card. The server is the
 * authority on tax allocation; the totals here are display-only and
 * use a proportional ratio of the original line's tax for each line.
 */
export function saleRefundPage({ rows = [], defaultMethodId = null } = {}) {
    return {
        // Each row gets a `refundQty` (number) and `restock` (null / bool).
        // Empty restock = "inherit the header flag"; explicit on/off
        // wins. Initial qty is the full remaining amount so a one-click
        // full refund needs no edits.
        rows: rows.map((r) => ({
            ...r,
            refundQty: parseFloat(r.remaining) || 0,
            restock:   null,
        })),

        defaultMethodId,
        headerRestock: true,

        money(v) {
            return window.posFormatMoney
                ? window.posFormatMoney(v)
                : Number(v || 0).toFixed(2);
        },

        /**
         * Trim trailing zeros from a stock quantity for display.
         * Quantities are stored at 4dp in the DB but cashiers expect
         * "10" not "10.0000" and "1.5" not "1.5000".
         */
        fmtQty(v) {
            const n = parseFloat(v) || 0;
            if (Number.isInteger(n)) return String(n);
            return String(n.toFixed(4)).replace(/0+$/, '').replace(/\.$/, '');
        },

        /**
         * Per-row line total — refund qty × unit price, less the
         * proportional slice of the original line discount, plus the
         * proportional slice of the original line tax. Matches the
         * server-side allocation in RecordSaleReturn so the cashier
         * sees the same number the receipt will print.
         */
        lineTotal(r) {
            const qty   = parseFloat(r.refundQty) || 0;
            const orig  = parseFloat(r.quantity)  || 0;
            if (qty <= 0 || orig <= 0) return 0;
            const ratio = qty / orig;
            const sub   = qty * (parseFloat(r.unit_price) || 0);
            const disc  = (parseFloat(r.discount_amount) || 0) * ratio;
            const tax   = (parseFloat(r.tax_amount)      || 0) * ratio;
            return Math.max(0, sub - disc + tax);
        },

        get hasAnyQty() {
            return this.rows.some((r) => (parseFloat(r.refundQty) || 0) > 0);
        },

        get totals() {
            let sub = 0, tax = 0, grand = 0;
            for (const r of this.rows) {
                const qty   = parseFloat(r.refundQty) || 0;
                const orig  = parseFloat(r.quantity)  || 0;
                if (qty <= 0 || orig <= 0) continue;
                const ratio = qty / orig;
                const s = qty * (parseFloat(r.unit_price) || 0);
                const d = (parseFloat(r.discount_amount) || 0) * ratio;
                const t = (parseFloat(r.tax_amount)      || 0) * ratio;
                sub   += Math.max(0, s - d);
                tax   += t;
                grand += Math.max(0, s - d + t);
            }
            return { subtotal: sub, tax, grand };
        },
    };
}
