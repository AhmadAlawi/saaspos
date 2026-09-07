/**
 * Alpine factory for the Stock Adjustment editor (draft create + edit).
 *
 * Owns the line-items array, the product-search dropdown, and the
 * computed totals. Lines submit via plain `<input type="hidden">` rows
 * named `items[i][field]` so the server controller doesn't need a
 * special JSON endpoint.
 *
 * Batch support: when a product has `track_batches=true` the line shows
 * an inline batch picker that fetches available batches from the server
 * for the currently-selected store. Picking a batch records `batch_id`
 * on the line; clearing returns it to "no batch" (batch_id = null).
 *
 * New batches: on an "In" line the picker also offers "Create new batch",
 * which flips `creatingBatch=true` and reveals a sub-row capturing
 * `batch_number` + `manufacture_date` + `expiry_date`. The batch row
 * itself is materialised server-side at post time (see PostStockAdjustment).
 * Picking an existing batch and creating a new one are mutually exclusive.
 */
import { posGet, posPost } from '../lib/http.js';
import { barcodeScanMixin } from './barcode-scan-mixin.js';

export function stockAdjustmentEditor({
    searchUrl,
    batchesUrl,
    stockUrl   = null,
    scanUrl    = null,
    storeId = '',
    items   = [],
    rsnUrl  = null,
} = {}) {
    let _uid = 0;

    /**
     * Build a line view-model from either:
     *   - a row coming back from the server (`quantity_delta` is signed)
     *   - a freshly-picked product from the picker
     */
    const lineFrom = (raw = {}) => {
        const signed = raw.quantity_delta !== undefined && raw.quantity_delta !== null && raw.quantity_delta !== ''
            ? parseFloat(raw.quantity_delta)
            : null;
        return {
            _uid:          ++_uid,
            product_id:    raw.product_id ?? '',
            variant_id:    raw.variant_id ?? '',
            product_label: raw.product_label ?? '',
            sku:           raw.sku ?? '',
            direction:     signed !== null && signed < 0 ? 'out' : 'in',
            quantity_abs:  signed !== null ? Math.abs(signed).toString() : '',
            // Current on-hand for this product/variant in the chosen store.
            // null = "not yet loaded"; filled in by _refreshAvailability().
            available:     raw.available ?? null,
            unit_cost:     raw.unit_cost ?? '',
            notes:         raw.notes ?? '',
            // Batch fields
            track_batches:    raw.track_batches ?? false,
            batch_id:         raw.batch_id ?? null,
            batch_number:     raw.batch_number ?? '',
            // New-batch capture (only meaningful when creatingBatch + In).
            creatingBatch:    raw.creating_batch ?? false,
            manufacture_date: raw.manufacture_date ?? '',
            expiry_date:      raw.expiry_date ?? '',
        };
    };

    /** Signed quantity for a given line — used by the totals + the hidden submit input. */
    const signedDeltaOf = (line) => {
        const n = parseFloat(line.quantity_abs) || 0;
        return line.direction === 'out' ? -n : n;
    };

    return {
        // Shared barcode-scan behaviour (scan state, resolve, camera, F8).
        ...barcodeScanMixin({ scanUrl }),

        items: items.map(lineFrom),
        storeId: String(storeId || ''),

        _el: null,  // cached during init(); $el/$root are not reliable in async callbacks
        init() {
            this._el = this.$el;
            this._refreshAvailability(); // seed the "Available" column for existing lines
            this.initBarcodeScan();
        },

        destroy() {
            this.destroyBarcodeScan();
        },

        /**
         * Load current on-hand for every line's product/variant in the
         * selected store and stamp it onto `line.available`. One round-trip
         * for all lines; safe to call repeatedly (add product, change store).
         */
        async _refreshAvailability() {
            if (!stockUrl || !this.storeId) return;
            const lookup = this.items
                .filter((l) => l.product_id)
                .map((l) => ({ product_id: l.product_id, variant_id: l.variant_id || null }));
            if (!lookup.length) return;

            try {
                const { data } = await posPost(stockUrl, { store_id: this.storeId, items: lookup });
                const map = {};
                (data || []).forEach((r) => { map[`${r.product_id}:${r.variant_id ?? ''}`] = r.quantity; });
                this.items.forEach((l, i) => {
                    const key = `${l.product_id}:${l.variant_id || ''}`;
                    if (map[key] !== undefined) this.items[i].available = map[key];
                });
            } catch (_) {
                // Leave availability as-is on failure — it's informational.
            }
        },

        // Product search dropdown state.
        query:        '',
        results:      [],
        searching:    false,
        searchOpen:   false,
        _searchTimer: null,

        // Batch picker — one open at a time, identified by line._uid.
        batchPickerUid:     null,
        batchPickerResults: [],
        batchPickerLoading: false,

        // Cached field-error map populated on 422.
        fieldErrors: {},

        // ── Computed totals ─────────────────────────────────────────
        signedDeltaFor(line) { return signedDeltaOf(line); },

        /** System-wide quantity formatting (respects the qty-decimals setting). */
        formatQty(v) {
            const n = parseFloat(v) || 0;
            return window.posFormatQty ? window.posFormatQty(n) : n.toString();
        },

        /** True when an Out line wants more than the store has on hand. */
        isOverDrawn(line) {
            if (line.direction !== 'out' || line.available === null || line.available === undefined) return false;
            return (parseFloat(line.quantity_abs) || 0) > parseFloat(line.available);
        },

        /** True when the product's current on-hand is negative (oversold).
         *  An In line's entered quantity nets this off automatically. */
        isOversold(line) {
            if (line.available === null || line.available === undefined) return false;
            return parseFloat(line.available) < 0;
        },

        /** How much a line is oversold by (positive number), for display. */
        oversoldBy(line) {
            return Math.abs(parseFloat(line.available) || 0);
        },

        setDirection(line, direction) {
            line.direction = direction;
            // A new batch only makes sense on an inflow line. Switching to
            // Out cancels any in-progress new batch (existing picks stay).
            if (direction !== 'in' && line.creatingBatch) {
                this.cancelNewBatch(line);
            }
        },

        get totalIn() {
            return this.items.reduce((sum, l) => {
                return l.direction === 'in' ? sum + (parseFloat(l.quantity_abs) || 0) : sum;
            }, 0);
        },
        get totalOut() {
            return this.items.reduce((sum, l) => {
                return l.direction === 'out' ? sum + (parseFloat(l.quantity_abs) || 0) : sum;
            }, 0);
        },
        get totalNet() { return this.totalIn - this.totalOut; },

        // ── Store tracking ───────────────────────────────────────────
        /** Called when the store_id select changes. Clears stale batch picks. */
        setStoreId(id) {
            this.storeId = String(id || '');
            this.batchPickerUid = null;
            // Clear batch selections + stale availability — they belong to
            // the old store — then reload on-hand for the new one.
            this.items.forEach((_, i) => {
                this.items[i].batch_id         = null;
                this.items[i].batch_number     = '';
                this.items[i].creatingBatch    = false;
                this.items[i].manufacture_date = '';
                this.items[i].expiry_date      = '';
                this.items[i].available        = null;
            });
            this._refreshAvailability();
        },

        // ── Product search ───────────────────────────────────────────
        onSearchInput() {
            this.searchOpen = true;
            clearTimeout(this._searchTimer);
            this._searchTimer = setTimeout(() => this._fetch(), 250);
        },

        focusSearch() {
            this.searchOpen = true;
            if (!this.results.length) this._fetch();
        },

        closeSearch() {
            setTimeout(() => { this.searchOpen = false; }, 120);
        },

        async _fetch() {
            this.searching = true;
            try {
                const { data } = await posGet(searchUrl, this.query ? { q: this.query } : null);
                this.results = Array.isArray(data) ? data : [];
            } catch (_) {
                this.results = [];
            } finally {
                this.searching = false;
            }
        },

        addProduct(row) {
            this.items.unshift(lineFrom({
                product_id:     row.product_id,
                variant_id:     row.variant_id,
                product_label:  row.label,
                sku:            row.sku,
                track_batches:  row.track_batches ?? false,
                quantity_delta: '',
            }));
            this.query      = '';
            this.results    = [];
            this.searchOpen = false;
            this._refreshAvailability();
            this.$nextTick(() => {
                this._el?.querySelectorAll('.adj-line-qty input')[0]?.focus();
            });
        },

        removeLine(uid) {
            if (this.batchPickerUid === uid) this.batchPickerUid = null;
            this.items = this.items.filter((l) => l._uid !== uid);
        },

        // ── Barcode scanning (host hook for barcodeScanMixin) ────────
        /**
         * A scan of an item already in the list bumps its quantity by 1 rather
         * than stacking duplicate lines — the natural "scan each unit" flow.
         * A new item is added as an inflow line with quantity 1.
         */
        _onScanResolved(row) {
            const existing = this.items.find((l) =>
                String(l.product_id) === String(row.product_id) &&
                String(l.variant_id || '') === String(row.variant_id || ''));

            if (existing) {
                const next = (parseFloat(existing.quantity_abs) || 0) + 1;
                existing.quantity_abs = next.toString();
                this._flashLine(existing._uid);
            } else {
                this.addProduct(row);
                // addProduct unshifts, so the new line is items[0]. Seed qty 1.
                if (this.items[0]) this.items[0].quantity_abs = '1';
            }

            this.$store?.toasts?.push({ type: 'success', message: row.label, duration: 1500 });
        },

        /** Briefly highlight a line the scan just touched. */
        _flashLine(uid) {
            this.$nextTick(() => {
                const el = this._el?.querySelector(`[data-line-uid="${uid}"]`);
                if (!el) return;
                el.classList.add('inv-scan-flash');
                setTimeout(() => el.classList.remove('inv-scan-flash'), 600);
            });
        },

        // ── Batch picker ─────────────────────────────────────────────
        isBatchPickerOpen(line) {
            return this.batchPickerUid === line._uid;
        },

        async openBatchPicker(line) {
            if (this.batchPickerUid === line._uid) {
                this.batchPickerUid = null;
                return;
            }
            this.batchPickerUid     = line._uid;
            this.batchPickerResults = [];
            this.batchPickerLoading = true;
            try {
                const params = {
                    product_id: line.product_id,
                    store_id:   this.storeId,
                };
                if (line.variant_id) params.variant_id = line.variant_id;
                const { data } = await posGet(batchesUrl, params);
                this.batchPickerResults = Array.isArray(data) ? data : [];
            } catch (_) {
                this.batchPickerResults = [];
            } finally {
                this.batchPickerLoading = false;
            }
        },

        closeBatchPicker() {
            this.batchPickerUid = null;
        },

        pickBatch(line, batch) {
            const i = this.items.findIndex((l) => l._uid === line._uid);
            if (i !== -1) {
                this.items[i].batch_id         = batch.id;
                this.items[i].batch_number     = batch.batch_number;
                // Existing pick wins — drop any half-entered new batch.
                this.items[i].creatingBatch    = false;
                this.items[i].manufacture_date = '';
                this.items[i].expiry_date      = '';
            }
            this.batchPickerUid = null;
        },

        clearBatch(line) {
            const i = this.items.findIndex((l) => l._uid === line._uid);
            if (i !== -1) {
                this.items[i].batch_id         = null;
                this.items[i].batch_number     = '';
                this.items[i].creatingBatch    = false;
                this.items[i].manufacture_date = '';
                this.items[i].expiry_date      = '';
            }
            this.batchPickerUid = null;
        },

        // ── New batch (inflow lines only) ────────────────────────────
        /** Switch a line into "create new batch" mode with a fresh form. */
        startNewBatch(line) {
            const i = this.items.findIndex((l) => l._uid === line._uid);
            if (i !== -1) {
                this.items[i].batch_id         = null;
                this.items[i].batch_number     = '';
                this.items[i].manufacture_date = '';
                this.items[i].expiry_date      = '';
                this.items[i].creatingBatch    = true;
            }
            this.batchPickerUid = null;
        },

        /** Discard the in-progress new batch and return the line to "no batch". */
        cancelNewBatch(line) {
            const i = this.items.findIndex((l) => l._uid === line._uid);
            if (i !== -1) {
                this.items[i].creatingBatch    = false;
                this.items[i].batch_number     = '';
                this.items[i].manufacture_date = '';
                this.items[i].expiry_date      = '';
            }
        },

        clearFieldError(field) {
            if (this.fieldErrors[field]) {
                const next = { ...this.fieldErrors };
                delete next[field];
                this.fieldErrors = next;
            }
        },
    };
}
