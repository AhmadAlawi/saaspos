/**
 * Alpine factory for the Stock Transfer editor (draft create + edit).
 *
 * Manages the line-items array, the product-search dropdown, and the
 * per-item available-quantity display. Stock levels are fetched from
 * the server whenever a product is added or the source store changes,
 * and the form submit is blocked if any line exceeds available stock.
 *
 * REACTIVITY NOTE: after any `await`, we must mutate line fields through
 * `this.items[idx]` (the reactive proxy), NOT through the raw `line`
 * reference captured before the await. Raw-object mutations bypass
 * Alpine/Vue reactivity and silently drop DOM updates.
 */
import { posGet, posPost } from '../lib/http.js';
import { barcodeScanMixin } from './barcode-scan-mixin.js';

export function stockTransferEditor({
    searchUrl,
    stockLevelsUrl,
    scanUrl = null,
    fromStoreId = '',
    items = [],
} = {}) {
    let _uid = 0;

    const lineFrom = (raw = {}) => ({
        _uid:               ++_uid,
        product_id:         raw.product_id ?? '',
        variant_id:         raw.variant_id ?? '',
        product_label:      raw.product_label ?? '',
        sku:                raw.sku ?? '',
        requested_quantity: raw.requested_quantity !== undefined ? String(raw.requested_quantity) : '',
        unit_cost:          raw.unit_cost ?? '',
        available_qty:      null,   // populated asynchronously by fetchAvailableQty
        qty_loading:        false,  // true while fetching
    });

    /** Format a quantity number with the system's configured decimal places. */
    const fmtQty = (n) =>
        typeof window.posFormatQty === 'function'
            ? window.posFormatQty(n)
            : Number(n).toFixed(2);

    return {
        // Shared barcode-scan behaviour (scan state, resolve, camera, F8).
        ...barcodeScanMixin({ scanUrl }),

        items: items.map(lineFrom),
        fromStoreId: String(fromStoreId || ''),

        query:        '',
        results:      [],
        searching:    false,
        searchOpen:   false,
        _searchTimer: null,
        _el:          null,  // cached during init(); $el/$root are not reliable in async callbacks

        init() {
            this._el = this.$el;
            if (this.items.length && this.fromStoreId) {
                this.refreshAllStockLevels();
            }
            this.initBarcodeScan();
        },

        destroy() {
            this.destroyBarcodeScan();
        },

        /**
         * Scan → add or bump a transfer line. Re-scanning an item already in the
         * list bumps its requested quantity by 1 rather than stacking a
         * duplicate row (the natural "scan each unit" flow).
         */
        _onScanResolved(row) {
            const existing = this.items.find((l) =>
                String(l.product_id) === String(row.product_id) &&
                String(l.variant_id || '') === String(row.variant_id || ''));

            if (existing) {
                const next = (parseFloat(existing.requested_quantity) || 0) + 1;
                existing.requested_quantity = next.toString();
                this._flashLine(existing._uid);
            } else {
                this.addProduct(row);
                // addProduct pushes, so the new line is last. Seed qty 1.
                const last = this.items[this.items.length - 1];
                if (last) last.requested_quantity = '1';
            }

            this.$store?.toasts?.push({ type: 'success', message: row.label, duration: 1500 });
        },

        /** Briefly highlight a line a scan just added or bumped. */
        _flashLine(uid) {
            this.$nextTick(() => {
                const el = this._el?.querySelector(`[data-line-uid="${uid}"]`);
                if (!el) return;
                el.classList.add('inv-scan-flash');
                setTimeout(() => el.classList.remove('inv-scan-flash'), 600);
            });
        },

        get totalItems() {
            return this.items.length;
        },

        get totalQty() {
            return this.items.reduce(
                (sum, l) => sum + (parseFloat(l.requested_quantity) || 0),
                0
            );
        },

        get hasStockErrors() {
            return this.items.some((l) => this.isOverStock(l));
        },

        isOverStock(line) {
            if (line.available_qty === null) return false;
            const req   = parseFloat(line.requested_quantity) || 0;
            const avail = parseFloat(line.available_qty);
            return req > avail;
        },

        /**
         * Called by the form's @submit handler. Prevents submission and
         * shows an error toast if any line exceeds available stock.
         */
        validateOnSubmit($event) {
            if (!this.hasStockErrors) return;
            $event.preventDefault();

            const msgs = this.items
                .filter((l) => this.isOverStock(l))
                .map((l) => {
                    const req   = fmtQty(l.requested_quantity);
                    const avail = fmtQty(l.available_qty);
                    return `${l.product_label}: requested ${req}, only ${avail} available`;
                });

            window.Alpine?.store?.('toasts')?.push({
                type:     'error',
                title:    'Insufficient stock',
                messages: msgs,
                duration: 0,
            });
        },

        onFromStoreChange(storeId) {
            this.fromStoreId = String(storeId || '');
            this.refreshAllStockLevels();
        },

        async fetchAvailableQty(line) {
            const uid = line._uid;
            if (!this.fromStoreId || !line.product_id) return;

            // Update through the reactive proxy (this.items[idx]), not through
            // the raw `line` reference — raw mutations are invisible after await.
            const set = (field, value) => {
                const i = this.items.findIndex((l) => l._uid === uid);
                if (i !== -1) this.items[i][field] = value;
            };

            set('qty_loading', true);
            try {
                const { data } = await posPost(stockLevelsUrl, {
                    store_id: this.fromStoreId,
                    items: [{ product_id: line.product_id, variant_id: line.variant_id || null }],
                });
                if (Array.isArray(data) && data.length) {
                    set('available_qty', data[0].quantity);
                }
            } catch (_) {
                // Non-critical — UI stays functional without availability data
            } finally {
                set('qty_loading', false);
            }
        },

        async refreshAllStockLevels() {
            if (!this.fromStoreId) {
                // null out through the proxy so Alpine tracks the change
                this.items.forEach((_, i) => { this.items[i].available_qty = null; });
                return;
            }
            if (!this.items.length) return;

            try {
                const { data } = await posPost(stockLevelsUrl, {
                    store_id: this.fromStoreId,
                    items: this.items.map((l) => ({
                        product_id: l.product_id,
                        variant_id: l.variant_id || null,
                    })),
                });
                if (!Array.isArray(data)) return;
                data.forEach((row) => {
                    const idx = this.items.findIndex(
                        (l) =>
                            String(l.product_id) === String(row.product_id) &&
                            (l.variant_id || null) == (row.variant_id || null)
                    );
                    if (idx !== -1) {
                        this.items[idx].available_qty = row.quantity;
                    }
                });
            } catch (_) {
                // Non-critical
            }
        },

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
            const line = lineFrom({
                product_id:    row.product_id,
                variant_id:    row.variant_id,
                product_label: row.label,
                sku:           row.sku,
            });
            this.items.push(line);
            this.query      = '';
            this.results    = [];
            this.searchOpen = false;
            this.fetchAvailableQty(line);
            this.$nextTick(() => {
                const inputs = this._el?.querySelectorAll('.trf-line-qty input');
                inputs?.[inputs.length - 1]?.focus();
            });
        },

        removeLine(uid) {
            this.items = this.items.filter((l) => l._uid !== uid);
        },
    };
}
