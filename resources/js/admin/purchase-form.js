/**
 * Alpine factory for the Purchase create / edit form (draft slice).
 *
 * Owns:
 *   - header state (supplier_id, store_id, dates, currency, invoice no)
 *   - items array (each row carries a stable `_uid` for x-for keys)
 *   - the product picker dropdown (single top-of-table search; results
 *     are added as new rows; the user fine-tunes qty / unit cost /
 *     discount / tax on the row)
 *   - reactive totals — subtotal / discount / tax / grand
 *
 * Server is authoritative on totals — the action layer recomputes
 * them at save (see `ComputePurchaseTotals`). The numbers shown here
 * are a live preview so the user can sanity-check before submit.
 *
 * Supplier selection auto-fills:
 *   - `currency_code` from the supplier's `default_currency_code`
 *     (unless the user has already touched the currency picker)
 *   - `due_date` = `purchase_date` + supplier.payment_terms_days
 *     (only when due_date is blank)
 */
import { posGet } from '../lib/http.js';
import { barcodeScanMixin } from './barcode-scan-mixin.js';

export function purchaseForm({
    searchUrl,
    scanUrl              = null,
    pricesUrl            = null,
    // Translated strings the price block needs at runtime. Blade owns __();
    // this factory never hardcodes user-facing English.
    labels               = {},
    items                = [],
    suppliers            = [],
    taxGroups            = [],
    initialSupplierId    = '',
    initialStoreId       = '',
    initialPurchaseDate  = '',
    initialDueDate       = '',
    initialCurrency      = '',
    initialInvoiceNumber = '',
    initialNotes         = '',
    baseCurrency         = '',
} = {}) {
    let _uid = 0;

    // Pre-index suppliers / tax groups for O(1) lookups during reactive
    // recompute.
    const suppliersById = Object.fromEntries(suppliers.map((s) => [String(s.id), s]));
    // Tax group → combined percent rate (sum of each component's `rate`,
    // which is stored as a percent on the `tax_components` table).
    const taxRateById = Object.fromEntries(taxGroups.map((g) => {
        const rate = (g.components || []).reduce((sum, c) => sum + (parseFloat(c.rate) || 0), 0);
        return [String(g.id), rate];
    }));

    const lineFrom = (raw = {}) => ({
        _uid:             ++_uid,
        product_id:       raw.product_id        ?? '',
        variant_id:       raw.variant_id        ?? '',
        product_label:    raw.product_label     ?? '',
        sku:              raw.sku               ?? '',
        quantity:         raw.quantity          ?? '',
        unit_cost:        raw.unit_cost         ?? '',
        discount_percent: raw.discount_percent  ?? '0',
        tax_group_id:     raw.tax_group_id      ?? '',
        // Batch / expiry — visible only on `track_batches` lines. The
        // values still ride through on every form submit even when the
        // row is hidden (e.g. duplicated from a batch-tracked line); the
        // server happily ignores them for non-batch products.
        track_batches:    !!raw.track_batches,
        track_expiry:     !!raw.track_expiry,
        batch_number:     raw.batch_number      ?? '',
        manufacture_date: raw.manufacture_date  ?? '',
        expiry_date:      raw.expiry_date       ?? '',
    });

    return {
        // Shared barcode-scan behaviour (scan state, resolve, camera, F8).
        ...barcodeScanMixin({ scanUrl }),

        _el: null,  // cached in init(); $root isn't reliable inside async callbacks

        init() {
            this._el = this.$el;
            this.initBarcodeScan();

            // Selling price / MRP resolve per store, so switching the store
            // invalidates every line's price at once (see _syncPrices).
            this.$watch('storeId', () => {
                this.priceMap = {};
                this._syncPrices();
            });

            this._syncPrices();
        },

        destroy() {
            this.destroyBarcodeScan();
        },

        // ── Header reactive state ─────────────────────────────────
        supplierId:    initialSupplierId  ? String(initialSupplierId)  : '',
        storeId:       initialStoreId     ? String(initialStoreId)     : '',
        purchaseDate:  initialPurchaseDate || new Date().toISOString().slice(0, 10),
        dueDate:       initialDueDate     || '',
        currencyCode:  initialCurrency    || baseCurrency || '',
        invoiceNumber: initialInvoiceNumber || '',
        notes:         initialNotes       || '',
        currencyTouched: !!initialCurrency,

        items: items.map(lineFrom),

        // ── Product picker state ─────────────────────────────────
        query:        '',
        results:      [],
        searching:    false,
        searchOpen:   false,
        _searchTimer: null,

        /**
         * Supplier change handler — invoked from `x-effect` on the
         * supplier `<select>`. `x-effect` re-runs whenever `supplierId`
         * changes (driven by x-model on TomSelect's underlying select),
         * which is the proven pattern from Categories.
         *
         * This must be safe on the initial mount (when supplierId is
         * blank) and idempotent (the effect re-fires after we write to
         * any tracked property).
         *
         * Writes the reactive props AND pokes the wrapped controls
         * directly — TomSelect's chip and flatpickr's calendar don't
         * follow x-model writes; only user input updates them.
         */
        onSupplierChange(supplierId) {
            if (!supplierId) return;
            const s = suppliersById[String(supplierId)];
            if (!s) return;

            if (!this.currencyTouched && s.default_currency_code) {
                if (this.currencyCode !== s.default_currency_code) {
                    // Just write the reactive prop — the currency
                    // <select>'s own `x-effect="ts && ts.setValue(...)"`
                    // pushes the value into TomSelect's visible chip.
                    this.currencyCode = s.default_currency_code;
                }
            }

            if (!this.dueDate && this.purchaseDate && s.payment_terms_days != null) {
                const d = new Date(this.purchaseDate);
                d.setDate(d.getDate() + parseInt(s.payment_terms_days, 10));
                const iso = d.toISOString().slice(0, 10);
                this.dueDate = iso;
                this.$refs.dueDateInput?._flatpickr?.setDate(iso, false);
            }
        },

        // ── Currency: mark touched so supplier auto-fill stops ──
        onCurrencyChange() { this.currencyTouched = true; },

        /**
         * Format an amount in the currently-picked currency using the
         * system-wide `posFormatMoney()` helper — same rules as PHP's
         * `format_money()` (decimals, separators, symbol placement).
         * See `resources/js/lib/format-money.js`.
         */
        formatMoney(amount) {
            return window.posFormatMoney
                ? window.posFormatMoney(amount, this.currencyCode)
                : Number(amount || 0).toFixed(2);
        },

        // ── Read-only price context ──────────────────────────────
        // What we sell each line's item for, its MRP, and the owner's target
        // markup — so the buyer can answer "am I still making money at this
        // cost?" without leaving the PO. Strictly read-only: a PO is a promise
        // to buy and must not reprice stock already on the shelf. Prices change
        // at receive (ReceivePurchase), where the goods actually exist.
        // See docs/features/suppliers-purchases.md §5.7.

        /** `{ "<product>-<variant>": {selling_price, mrp, markup_percent} }` */
        priceMap: {},

        /** Line identity in priceMap. `0` = no variant. */
        _priceKey(line) {
            return `${line.product_id}-${line.variant_id || 0}`;
        },

        /**
         * Fetch prices for any line we don't have yet, in one request.
         *
         * Prices are resolved server-side for the PO's store (the
         * product_store_prices → variant → product cascade), so this cannot
         * be answered from the product picker's payload — the picker doesn't
         * know which store the PO is for, and the store can change after a
         * line is added.
         */
        async _syncPrices() {
            if (!pricesUrl) return;

            const missing = [...new Set(
                this.items
                    .filter((l) => l.product_id)
                    .map((l) => this._priceKey(l)),
            )].filter((k) => !(k in this.priceMap));

            if (!missing.length) return;

            try {
                const { data } = await posGet(pricesUrl, {
                    store_id: this.storeId || '',
                    keys:     missing,
                });
                // Assign a fresh object: Alpine tracks the property, and
                // mutating in place won't re-render the x-for'd lines.
                this.priceMap = { ...this.priceMap, ...(data || {}) };
            } catch (e) {
                // Price context is decoration — a failed lookup must never
                // block building the PO. Lines simply show no price block.
            }
        },

        /**
         * True when cost and selling price are in different currencies.
         *
         * This form captures no exchange rate, so for a foreign-currency PO
         * there is no honest way to compare a cost in (say) USD against a
         * selling price in INR. A markup computed across them would be pure
         * noise — orders of magnitude wrong — so the whole block is hidden
         * and replaced with a one-line explanation.
         */
        get pricesUnavailableFx() {
            return !!(baseCurrency && this.currencyCode && this.currencyCode !== baseCurrency);
        },

        /** The resolved price row for a line, or null. */
        linePrices(line) {
            if (this.pricesUnavailableFx) return null;
            return this.priceMap[this._priceKey(line)] || null;
        },

        /**
         * The unit cost the markup is measured against: discount-adjusted,
         * because a buyer who negotiates 10% off has genuinely changed what
         * this unit costs them.
         *
         * Deliberately not `lineNet / quantity` — that divides by a qty the
         * user may have just cleared. Same number, no division by zero.
         */
        _effectiveUnitCost(line) {
            const unit = parseFloat(line.unit_cost) || 0;
            const dpct = parseFloat(line.discount_percent) || 0;
            return unit - (unit * dpct) / 100;
        },

        /**
         * Markup % this line's cost implies against the current selling price
         * — `(sell - cost) / cost × 100`. Null when it can't be computed.
         *
         * Markup, NOT margin: a 25% markup is a 20% margin. `markup_percent`,
         * the product form's KPI and ReceivePurchase all speak markup; mixing
         * the two would silently misprice.
         */
        lineMarkup(line) {
            const prices = this.linePrices(line);
            if (!prices) return null;

            const sell = parseFloat(prices.selling_price) || 0;
            const cost = this._effectiveUnitCost(line);
            if (sell <= 0 || cost <= 0) return null;

            return ((sell - cost) / cost) * 100;
        },

        /** The owner's target markup for a line, or null when unset. */
        lineTargetMarkup(line) {
            const prices = this.linePrices(line);
            const target = prices && prices.markup_percent !== null
                ? parseFloat(prices.markup_percent)
                : NaN;
            return Number.isFinite(target) && target > 0 ? target : null;
        },

        /** Chip tone: danger underwater, warning below target, else quiet. */
        markupTone(line) {
            const markup = this.lineMarkup(line);
            if (markup === null) return '';
            if (markup <= 0) return 'pur-mk-bad';

            const target = this.lineTargetMarkup(line);
            if (target === null) return '';
            return markup < target ? 'pur-mk-warn' : 'pur-mk-ok';
        },

        /** Hover text explaining a non-quiet chip. */
        markupTitle(line) {
            const markup = this.lineMarkup(line);
            if (markup === null) return '';
            if (markup <= 0) return labels.markupLoss || '';

            const target = this.lineTargetMarkup(line);
            if (target !== null && markup < target) {
                return (labels.markupBelow || '').replace(':percent', this.formatPercent(target));
            }
            return labels.markupTitle || '';
        },

        /** Whole percents unless the value genuinely needs a decimal. */
        formatPercent(value) {
            const n = Number(value) || 0;
            return (Math.round(n * 10) / 10).toString();
        },

        /** Money in the company's base currency — never the PO's currency. */
        formatBaseMoney(amount) {
            if (amount === null || amount === undefined || amount === '') {
                return labels.priceUnset || '—';
            }
            return window.posFormatMoney
                ? window.posFormatMoney(amount, baseCurrency)
                : Number(amount || 0).toFixed(2);
        },

        // ── Per-line computed numbers — preview only ─────────────
        lineNet(line) {
            const qty   = parseFloat(line.quantity)  || 0;
            const unit  = parseFloat(line.unit_cost) || 0;
            const dpct  = parseFloat(line.discount_percent) || 0;
            const gross = qty * unit;
            return gross - (gross * dpct) / 100;
        },
        lineTax(line) {
            const rate = taxRateById[String(line.tax_group_id)] || 0;
            return (this.lineNet(line) * rate) / 100;
        },
        lineTotal(line) {
            return this.lineNet(line) + this.lineTax(line);
        },

        // ── Aggregate totals — Alpine recomputes on any line change.
        get subtotal()      { return this.items.reduce((s, l) => s + this.lineNet(l), 0); },
        get discountTotal() {
            return this.items.reduce((s, l) => {
                const gross = (parseFloat(l.quantity) || 0) * (parseFloat(l.unit_cost) || 0);
                const dpct  = parseFloat(l.discount_percent) || 0;
                return s + (gross * dpct) / 100;
            }, 0);
        },
        get taxTotal()      { return this.items.reduce((s, l) => s + this.lineTax(l), 0); },
        get grandTotal()    { return this.subtotal + this.taxTotal; },

        // ── Product picker ───────────────────────────────────────
        focusSearch() {
            this.searchOpen = true;
            if (!this.results.length) this._fetch();
        },
        onSearchInput() {
            this.searchOpen = true;
            clearTimeout(this._searchTimer);
            this._searchTimer = setTimeout(() => this._fetch(), 250);
        },
        closeSearch() {
            // Tiny delay so a click on a result fires before the dropdown collapses.
            setTimeout(() => { this.searchOpen = false; }, 120);
        },
        async _fetch() {
            this.searching = true;
            try {
                const { data } = await posGet(searchUrl, this.query ? { q: this.query } : null);
                this.results = Array.isArray(data) ? data : [];
            } catch (e) {
                this.results = [];
            } finally {
                this.searching = false;
            }
        },
        addProduct(row, variant = null) {
            this.items.push(lineFrom({
                product_id:    row.value,
                variant_id:    variant ? variant.id : '',
                product_label: variant ? `${row.label} — ${variant.label}` : row.label,
                quantity:      '1',
                track_batches: !!row.track_batches,
                track_expiry:  !!row.track_expiry,
            }));
            this.query      = '';
            this.results    = [];
            this.searchOpen = false;
            this._syncPrices();
            this.$nextTick(() => {
                const inputs = this.$root.querySelectorAll('.pur-line-qty input');
                inputs[inputs.length - 1]?.focus();
            });
        },

        // ── Barcode scanning (host hook for barcodeScanMixin) ────
        /**
         * A scan of an item already on the PO bumps its quantity by 1 rather
         * than stacking duplicate lines — the natural "scan each unit in" flow.
         * A new item is appended with quantity 1.
         *
         * `unit_cost` is deliberately left blank, exactly as the search picker
         * does: the cost comes off the supplier's invoice, and guessing it from
         * the product's last cost would silently book the wrong number.
         *
         * `row` is the resolver shape: {product_id, variant_id, label, sku,
         * track_batches, track_expiry} — note it already folds the variant into
         * a single row, so this can't reuse `addProduct(row, variant)`.
         */
        _onScanResolved(row) {
            const existing = this.items.find((l) =>
                String(l.product_id) === String(row.product_id) &&
                String(l.variant_id || '') === String(row.variant_id || ''));

            if (existing) {
                existing.quantity = ((parseFloat(existing.quantity) || 0) + 1).toString();
                this._flashLine(existing._uid);
            } else {
                this.items.push(lineFrom({
                    product_id:    row.product_id,
                    variant_id:    row.variant_id,
                    product_label: row.label,
                    sku:           row.sku,
                    quantity:      '1',
                    track_batches: !!row.track_batches,
                    track_expiry:  !!row.track_expiry,
                }));
                this._flashLine(this.items[this.items.length - 1]._uid);
                this._syncPrices();
            }

            this.$store?.toasts?.push({ type: 'success', message: row.label, duration: 1500 });
        },

        /** Briefly highlight the line a scan just touched. */
        _flashLine(uid) {
            this.$nextTick(() => {
                const el = this._el?.querySelector(`[data-line-uid="${uid}"]`);
                if (!el) return;
                el.classList.add('inv-scan-flash');
                setTimeout(() => el.classList.remove('inv-scan-flash'), 600);
            });
        },

        duplicateLine(uid) {
            const src = this.items.find((l) => l._uid === uid);
            if (!src) return;
            const copy = lineFrom({ ...src, _uid: undefined });
            const i = this.items.indexOf(src);
            this.items.splice(i + 1, 0, copy);
        },

        removeLine(uid) {
            this.items = this.items.filter((l) => l._uid !== uid);
        },
    };
}
