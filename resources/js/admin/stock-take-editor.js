/**
 * Alpine factory for the Stock Take (cycle count) editor.
 *
 * Owns the spreadsheet-like rows array, the live KPI / variance math, the
 * client-side pagination/sort (via the composed data-table mixin), and the
 * barcode scan behaviour. Lines submit via plain `<input>` rows named
 * `items[i][...]` so the server controller needs no JSON endpoint.
 *
 * `counted` is intentionally a string (so empty means "skipped") — the
 * server-side UpdateStockTake reads `'' / null` as "skipped" and persists
 * `counted_quantity` as NULL.
 *
 * Composing the data-table mixin (rather than nesting a separate `dataTable`
 * component) means a scan can jump the pager to the page holding the matched
 * row and focus its count field — impossible across two Alpine scopes.
 */
import { composeDataTable } from './data-table.js';
import { barcodeScanMixin } from './barcode-scan-mixin.js';

export function stockTakeEditor({ rows = [], scanUrl = null, labels = {} } = {}) {
    return composeDataTable(
        { rowsSelector: 'tbody > tr[data-dt-row]' },
        {
            // Shared barcode-scan behaviour (scan state, resolve, camera, F8).
            ...barcodeScanMixin({ scanUrl }),

            rows,

            // How a scan behaves:
            //   'locate'    — jump the pager to the row, highlight it, and focus
            //                 its count field so the operator types the real
            //                 quantity (you don't scan a shelf of 200 one unit at
            //                 a time). The count field's Enter returns focus to
            //                 the scan bar, so the next scan is safe.
            //   'increment' — +1 per scan, for tallying loose items piece by piece.
            scanMode: 'locate',

            // Row the last scan located — the count field the mixin should focus.
            _locatedId: null,

            init() {
                this.initDataTable();
                this.initBarcodeScan();
            },

            destroy() {
                this.destroyBarcodeScan();
            },

            /**
             * A stock take is a fixed snapshot of the store, so a scan FINDS the
             * matching count row (it never adds one). A product that isn't part
             * of the take warns. What happens next depends on `scanMode`.
             */
            _onScanResolved(row) {
                const match = this.rows.find((r) =>
                    String(r.product_id) === String(row.product_id) &&
                    String(r.variant_id || '') === String(row.variant_id || ''));

                if (!match) {
                    this._locatedId = null;
                    this.$store?.toasts?.push({
                        type: 'warning',
                        message: this._t(labels.not_in_take, { name: row.label }),
                    });
                    return;
                }

                this._revealRow(match.id);

                if (this.scanMode === 'increment') {
                    this._locatedId = null; // keep scan-bar focus for the next tally
                    const next = (parseFloat(match.counted) || 0) + 1;
                    match.counted = next.toString();
                    this.$store?.toasts?.push({
                        type: 'success',
                        message: this._t(labels.counted, { name: match.name, count: next }),
                        duration: 1500,
                    });
                } else {
                    // Locate: the mixin will focus this row's count field.
                    this._locatedId = match.id;
                    this.$store?.toasts?.push({
                        type: 'info',
                        message: this._t(labels.locate_prompt, { name: match.name }),
                        duration: 1500,
                    });
                }
            },

            /**
             * Where focus goes after a scan (read by the barcode-scan mixin). In
             * locate mode, the located row's count input — so the operator types
             * the quantity straight in. Otherwise null → the scan bar keeps focus.
             */
            _scanFocusTarget() {
                if (this.scanMode !== 'locate' || this._locatedId === null) return null;
                const input = this._dtRoot?.querySelector(`[data-line-uid="${this._locatedId}"] input[type="number"]`);
                this._locatedId = null;
                return input ?? null;
            },

            /** Page the list to the row (it may be paginated off-screen), scroll
             *  to it, and flash it, so the operator sees where the count lands. */
            _revealRow(id) {
                const rowEl = this._dtRoot?.querySelector(`[data-line-uid="${id}"]`);
                // Position in the *current* matched order (respects sort), so the
                // page maths is right even when the table is sorted.
                const pos = rowEl ? this._dtMatched.indexOf(rowEl) : -1;
                if (pos >= 0) this.page = Math.floor(pos / this.pageSize) + 1;

                this.$nextTick(() => {
                    const el = this._dtRoot?.querySelector(`[data-line-uid="${id}"]`);
                    if (!el) return;
                    el.classList.add('inv-scan-flash');
                    el.scrollIntoView({ block: 'center', behavior: 'smooth' });
                    setTimeout(() => el.classList.remove('inv-scan-flash'), 600);
                });
            },

            /** Interpolate a `:placeholder` lang template. */
            _t(tpl, params) {
                let s = tpl ?? '';
                for (const [k, v] of Object.entries(params)) s = s.replaceAll(':' + k, String(v));
                return s;
            },

            /** Signed variance for one row. Returns 0 for skipped rows so the
             *  KPI math doesn't get NaN. */
            variance(r) {
                if (r.counted === '' || r.counted === null) return 0;
                return (parseFloat(r.counted) || 0) - (parseFloat(r.expected) || 0);
            },

            /** Lines the operator has touched (counted_quantity is not blank). */
            get countedLines() {
                return this.rows.filter(r => r.counted !== '' && r.counted !== null).length;
            },

            /** Lines that will actually emit a movement on post. */
            get varianceLines() {
                return this.rows.filter(r => {
                    if (r.counted === '' || r.counted === null) return false;
                    return Math.abs(this.variance(r)) > 0.00005;
                }).length;
            },

            /** Sum of every variance — informational only. */
            get netVariance() {
                return this.rows.reduce((s, r) => s + this.variance(r), 0);
            },

            get hasAnyCount() {
                return this.countedLines > 0;
            },

            /** Quantity formatting — defers to the system-wide posFormatQty. */
            formatQty(v) {
                const n = parseFloat(v) || 0;
                return window.posFormatQty ? window.posFormatQty(n) : n.toString();
            },

            /** Variance with explicit sign and quasi-zero collapsed to '0'. */
            formatVariance(v) {
                const n   = parseFloat(v) || 0;
                const fmt = this.formatQty(Math.abs(n));
                if (n < -0.00005) return '−' + fmt;
                if (n >  0.00005) return '+' + fmt;
                return '0';
            },
        },
    );
}
