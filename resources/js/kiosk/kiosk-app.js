/**
 * Self-Ordering Kiosk — standalone entry (Slice 1: shell + browse + cart).
 *
 * A customer-operated re-skin of the cashier. It reads the SAME offline
 * catalog the cashier syncs into IndexedDB (Dexie: catalog_products /
 * catalog_categories) so it works with the internet down and adds no new
 * catalog endpoint. It owns an attract → browse → cart state machine with a
 * hard inactivity reset. Checkout (payment) and place-order (staff queue)
 * land in Slices 2–3; here the primary action is stubbed.
 *
 * Bundled separately from admin.js so the kiosk doesn't pull the whole
 * back-office. See docs/features/kiosk-self-ordering.md.
 */
import Alpine from 'alpinejs';
import QRCode from 'qrcode';
import { posGet, posPost } from '../lib/http.js';
import { posFormatMoney, posFormatQty } from '../lib/format-money.js';
import {
    readCatalogBlob,
    applyCatalogMeta,
    putCatalogProducts,
    finalizeCatalogProducts,
    enqueueKioskOrder,
} from '../offline/dexie-schema.js';
import { startConnectivity } from '../offline/connectivity.js';
import { startSyncEngine, drainQueue } from '../offline/sync-engine.js';
import { setSoundEnabled, playAdd, playReject, playSuccess } from './sound.js';

const SYNC_PAGE = 200;
const MAX_PAGES = 200;

/** How long staff must hold the brand mark to reveal the exit prompt.
 *  Long enough that an idle customer never trips it by accident. */
const EXIT_HOLD_MS = 3000;

export function kioskApp(bootstrap = {}) {
    return {
        // ── Bootstrap (server-rendered) ──────────────────────────
        mode:         bootstrap.mode || 'checkout',   // checkout | order
        syncUrl:      bootstrap.syncUrl || '/cashier/sync',
        placeUrl:     bootstrap.placeUrl || null,
        checkoutStartUrl:    bootstrap.checkoutStartUrl || null,
        checkoutCompleteUrl: bootstrap.checkoutCompleteUrl || null,
        sessionStatusUrl:    bootstrap.sessionStatusUrl || null,
        sessionCancelUrl:    bootstrap.sessionCancelUrl || null,
        exitUrl:      bootstrap.exitUrl || null,
        cashierUrl:   bootstrap.cashierUrl || '/cashier',
        idleTimeout:  Math.max(20, Number(bootstrap.idleTimeout) || 60),
        currencyCode: bootstrap.currencyCode || null,
        welcome:      bootstrap.welcome || '',
        labels:       bootstrap.labels || {},
        // Slice 5 config.
        customerMode: bootstrap.customerMode || 'off',       // off | optional | required
        allowNote:    bootstrap.allowNote !== false,
        categoryIds:  Array.isArray(bootstrap.categoryIds) ? bootstrap.categoryIds.map(String) : [],
        pinRequired:  !!bootstrap.pinRequired,
        attract:      bootstrap.attract || { media: [], seconds: 8 },
        soundEnabled: bootstrap.soundEnabled !== false,
        thankyouSeconds: Math.max(5, Number(bootstrap.thankyouSeconds) || 25),
        upi:          bootstrap.upi || null,   // { method_id, name, vpa, payee_name } | null

        // ── Live state ───────────────────────────────────────────
        state:       'attract',   // attract | browse | cart | payment | thankyou
        loading:     true,
        products:    [],
        categories:  [],
        selectedCat: null,        // category id (string) or null = all
        search:      '',
        cart:        [],
        note:        '',
        nextLid:     1,
        variantPicker: null,      // product whose variant sheet is open (or null)
        kitDetail:     null,      // kit/combo product whose "what's included" sheet is open
        exitOpen:    false,
        exitEmail:   '',
        exitPassword: '',
        exitError:   '',
        holding:     false,       // staff is press-and-holding the brand mark
        toast:       '',
        toastKind:   'success',   // success | error — drives the toast icon/colour
        custName:    '',          // customer details prompt (Slice 5)
        custPhone:   '',
        promoIx:     0,           // attract slideshow index
        placing:     false,       // order submit in flight
        pickupCode:  '',          // shown on the thank-you screen (order mode)
        receiptUrl:  '',          // set on a paid checkout — receipt QR target
        receiptQr:   '',          // data-URL of the receipt QR (checkout mode)
        savedOffline: false,      // order queued locally (no connection)
        _tyTimer:    null,
        _cartUuid:   null,        // idempotency key — one per cart, NOT per tap

        // ── Static UPI QR (pay at the machine, verified at the counter) ──
        upiQr:       '',          // data-URL of the `upi://pay?…` deep link
        upiClaiming: false,       // "I've paid" submit in flight
        upiClaimed:  false,       // this order carries an unverified UPI claim

        // ── Checkout (Slice 3) ───────────────────────────────────
        paySession:  null,        // { uuid, pay_url, amount, ... }
        payStatus:   'idle',      // idle | starting | waiting | paid | failed
        payQr:       '',          // data-URL of the pay_url QR
        payAmount:   '',
        _payUuid:    null,
        _payPoll:    null,

        _lastActivity: 0,
        _idleTimer:    null,
        _scanBuf:      '',
        _scanAt:       0,
        _toastTimer:   null,
        _holdTimer:    null,

        // ── Derived ──────────────────────────────────────────────
        get whitelist() { return new Set(this.categoryIds); },

        /** Category chips, limited to the merchant's whitelist (if any). */
        get visibleCategories() {
            if (!this.categoryIds.length) return this.categories;
            return this.categories.filter((c) => this.whitelist.has(String(c.id)));
        },

        /**
         * Effective kiosk price for a product — the parent's selling price,
         * or (for variant products, which advertise a "from" price) the
         * cheapest variant. Zero / unset means the merchant hasn't priced it
         * yet, so it isn't sellable to a self-serve customer.
         */
        _kioskPrice(p) {
            const base = Number(p?.charge_price ?? p?.selling_price) || 0;
            if (base > 0) return base;
            return Number(p?.price_min) || 0;
        },

        /**
         * Regular-price strikethrough — mirrors the cashier's tileWasLabel.
         * Null when there's no active sale_price, so the tile shows just
         * the one price as before.
         */
        tileWasLabel(p) {
            if (p?.has_variants || !p?.sale_price) return null;
            return this.money(p.selling_price);
        },

        /**
         * Units still sellable for a product (or one of its variants).
         *
         * `on_hand` from /cashier/sync is already net of held-ticket + kiosk
         * reservations. Products that don't track stock (services, kits) are
         * unlimited. A NULL `on_hand` on a stock-tracked product means no
         * stock row exists yet — the server counts that as ZERO (CompleteSale
         * and PlaceKioskOrder both refuse it), so we must too, or the shopper
         * would fill a cart that can't be rung up.
         */
        _availableFor(p, vid = null) {
            if (!p || !p.track_stock) return Infinity;
            if (vid) {
                const v = (p.variants || []).find((x) => Number(x.id) === Number(vid));
                return (v == null || v.on_hand == null) ? 0 : Number(v.on_hand) || 0;
            }
            return p.on_hand == null ? 0 : Number(p.on_hand) || 0;
        },

        /** Sellable units across the whole product (sums variants when present). */
        _productAvailable(p) {
            if (!p || !p.track_stock) return Infinity;
            if (p.has_variants && Array.isArray(p.variants) && p.variants.length) {
                return p.variants.reduce((sum, v) => sum + (v.on_hand == null ? 0 : Number(v.on_hand) || 0), 0);
            }
            return p.on_hand == null ? 0 : Number(p.on_hand) || 0;
        },

        /** True when nothing is left to sell — the tile is hidden entirely. */
        outOfStock(p) { return this._productAvailable(p) <= 0; },

        /** Tell the shopper they've hit the shelf limit. */
        _flashStockLimit(available) {
            const tpl = this.labels.stock_limit || 'Only :count left.';
            this._fail(tpl.replace(':count', posFormatQty(available)));
        },

        get filtered() {
            const q = this.search.trim().toLowerCase();
            const hasWhitelist = this.categoryIds.length > 0;
            return this.products.filter((p) => {
                // Merchant category whitelist — hide everything outside it.
                if (hasWhitelist && !this.whitelist.has(String(p.category_id))) return false;
                // A customer can't order an unpriced item on a self-serve
                // kiosk — hide products the merchant hasn't priced yet
                // (they still show on the staff cashier, priced at the till).
                if (this._kioskPrice(p) <= 0) return false;
                // Nothing left on the shelf — a self-serve customer must never
                // be able to order what the store can't hand over.
                if (this.outOfStock(p)) return false;
                const inCat = this.selectedCat === null
                    || String(p.category_id) === String(this.selectedCat);
                if (!inCat) return false;
                if (!q) return true;
                return (p.name || '').toLowerCase().includes(q)
                    || String(p.sku || '').toLowerCase().includes(q)
                    || String(p.barcode || '').toLowerCase().includes(q);
            });
        },

        // Which cart actions to offer. `both` shows the shopper both — pay now
        // (cashless) or order & pay at the counter — and lets them choose.
        get showCheckout() { return this.mode === 'checkout' || this.mode === 'both'; },
        get showOrder()    { return this.mode === 'order'    || this.mode === 'both'; },

        /** Static UPI QR is offered only when the merchant configured a VPA
         *  method AND this station takes payment at the machine. */
        get showUpi() { return !!(this.upi && this.upi.vpa) && this.showCheckout; },

        get hasAttract() {
            return Array.isArray(this.attract?.media) && this.attract.media.length > 0;
        },

        /** Required-mode gate: a name or phone must be present to submit. */
        get canSubmit() {
            if (this.customerMode !== 'required') return true;
            return this.custName.trim() !== '' || this.custPhone.trim() !== '';
        },

        get itemCount() { return this.cart.reduce((n, l) => n + l.qty, 0); },

        /** Per-line tax preview — mirrors the cashier's inclusive/exclusive
         *  handling. Display-only; the server recomputes authoritatively at
         *  checkout/place (Slices 2–3). */
        _lineTax(line) {
            const p = this.byId(line.pid);
            const base = line.price * line.qty;
            if (!p || !p.tax_taxable || !p.tax_rate_total) return 0;
            const rate = Number(p.tax_rate_total);
            return p.tax_inclusive
                ? base * rate / (100 + rate)   // tax embedded in the price
                : base * rate / 100;           // tax added on top
        },

        get tax() { return this.cart.reduce((s, l) => s + this._lineTax(l), 0); },

        get subtotal() {
            // Net of any tax that's already embedded in inclusive prices.
            return this.cart.reduce((s, l) => {
                const p = this.byId(l.pid);
                const base = l.price * l.qty;
                return s + (p && p.tax_inclusive ? base - this._lineTax(l) : base);
            }, 0);
        },

        get total() {
            return this.cart.reduce((s, l) => {
                const p = this.byId(l.pid);
                const base = l.price * l.qty;
                // Exclusive tax is added on top; inclusive is already in `base`.
                return s + (p && p.tax_taxable && !p.tax_inclusive ? base + this._lineTax(l) : base);
            }, 0);
        },

        // ── Lifecycle ────────────────────────────────────────────
        init() {
            setSoundEnabled(this.soundEnabled);
            this.loadCatalog();
            this._startIdle();
            this._startScanner();
            // Offline-first: the connectivity machine + sync engine keep the
            // kiosk usable during an outage — order-mode placements queue in
            // IndexedDB and drain (POST /kiosk/place) when the link returns.
            startConnectivity();
            startSyncEngine();

            // Attract slideshow — crossfade through the merchant's promo
            // images while idle (reuses the CFD slideshow idea).
            if (this.hasAttract && this.attract.media.length > 1) {
                const ms = Math.max(3, Number(this.attract.seconds) || 8) * 1000;
                setInterval(() => {
                    this.promoIx = (this.promoIx + 1) % this.attract.media.length;
                }, ms);
            }
        },

        money(n) { return posFormatMoney(Number(n) || 0, this.currencyCode); },
        qty(n) { return posFormatQty(Number(n) || 0); },

        byId(id) { return this.products.find((p) => p.id === id) || null; },

        /**
         * Boot from the warm IndexedDB catalog first (instant, offline-safe),
         * then refresh from the server in the background and repaint. Cold +
         * offline → empty grid with the "offline" note.
         */
        async loadCatalog() {
            try {
                const blob = await readCatalogBlob();
                if (blob && Array.isArray(blob.products) && blob.products.length) {
                    this._applyCatalog(blob);
                    this.loading = false;
                }
            } catch (e) { /* cold DB — fall through to network */ }

            await this._refresh();
            this.loading = false;
        },

        _applyCatalog(blob) {
            this.products = blob.products || [];
            this.categories = (blob.categories || [])
                .slice()
                .sort((a, b) => String(a.name).localeCompare(String(b.name)));
        },

        /** Paged pull of /cashier/sync into IDB, then re-read. Silent on
         *  failure — the page keeps whatever warm catalog it already had. */
        async _refresh() {
            try {
                const seen = new Set();
                let after = null, syncedAt = null, storeId = null;

                for (let page = 0; page < MAX_PAGES; page++) {
                    const params = after === null ? { limit: SYNC_PAGE } : { after, limit: SYNC_PAGE };
                    const { data: blob } = await posGet(this.syncUrl, params);
                    if (!blob || typeof blob !== 'object') return;

                    const pageData = blob.data ?? {};
                    const rows = Array.isArray(pageData.products) ? pageData.products : [];

                    if (after === null) {
                        await applyCatalogMeta(blob);
                        syncedAt = blob.synced_at ?? null;
                        storeId  = blob.store_id ?? null;
                    }
                    await putCatalogProducts(rows);
                    rows.forEach((p) => seen.add(p.id));

                    if (!pageData.has_more || pageData.next_after == null) break;
                    after = pageData.next_after;
                }

                await finalizeCatalogProducts(seen, syncedAt, storeId);
                const fresh = await readCatalogBlob();
                if (fresh) this._applyCatalog(fresh);
            } catch (e) { /* offline / transient — keep last-known-good */ }
        },

        // ── Navigation ───────────────────────────────────────────
        start() {
            this.state = 'browse';
            this.bump();
            this.$nextTick(() => this.$refs.searchInput?.focus());
        },

        // ── Tile tap → add simple products, or open a details sheet ──
        // Kit/combo products open a "what's included" sheet (McDonald's-meal
        // style); variant products open the option picker; plain products add
        // straight to the cart.
        tileTap(p) {
            if (!p) return;
            if (p.is_kit) { this.openKit(p); return; }
            if (p.has_variants) { this.openVariant(p); return; }
            this.add(p);
        },

        /** "from ₹X" display for a variant product; the plain price otherwise. */
        tilePrice(p) {
            const val = this._kioskPrice(p);
            return p?.has_variants
                ? (this.labels.from ? this.labels.from + ' ' : '') + this.money(val)
                : this.money(val);
        },

        // ── Variant picker ───────────────────────────────────────
        openVariant(p) { this.variantPicker = p; this.bump(); },
        closeVariant() { this.variantPicker = null; this.bump(); },
        pickVariant(p, v) {
            this.add(p, v);
            this.closeVariant();
            this._flash(p.name + ' — ' + (v.label || v.sku || ''));
        },

        // ── Kit / combo details ──────────────────────────────────
        // The kit is sold as one line at its own price; the component list is
        // informational only (the server expands the bundle), so this sheet
        // shows the customer what they're getting before they add it.
        openKit(p)  { this.kitDetail = p; this.bump(); },
        closeKit()  { this.kitDetail = null; this.bump(); },
        addKit(p) {
            this.add(p);
            this.closeKit();
            this._flash(p.name);
        },

        // ── Cart ops ─────────────────────────────────────────────
        /**
         * Add a product to the cart. `variant` is set when the customer picked
         * one from the variant sheet — its own price + id ride on the line, and
         * lines are keyed by (product, variant) so two variants of the same
         * product stack separately.
         */
        add(p, variant = null) {
            if (!p) return;
            const vid = variant ? variant.id : null;
            const ex  = this.cart.find((l) => l.pid === p.id && (l.vid || null) === vid);

            // Never let the cart exceed what's on the shelf.
            const available = this._availableFor(p, vid);
            if ((ex ? ex.qty : 0) + 1 > available) {
                this._flashStockLimit(available);
                this.bump();
                return;
            }

            if (ex) { ex.qty++; }
            else {
                this.cart.push({
                    lid:       this.nextLid++,
                    pid:       p.id,
                    vid,
                    name:      variant ? p.name + ' — ' + (variant.label || variant.sku || '') : p.name,
                    // charge_price (sale_price when active, else selling_price)
                    // so the cart preview never disagrees with what
                    // PlaceKioskOrder/KioskCheckoutController actually charge.
                    price:     Number(variant ? (variant.charge_price ?? variant.selling_price) : (p.charge_price ?? p.selling_price)) || 0,
                    qty:       1,
                    image_url: (variant && variant.image_url) || p.image_url || null,
                });
            }
            // The shopper's eyes are on the grid, not the cart bar — confirm
            // the add audibly. Fired here (not in tileTap) so a barcode scan
            // and the variant/kit sheets all get the same feedback.
            playAdd();
            this.bump();
        },
        inc(line) {
            const available = this._availableFor(this.byId(line.pid), line.vid || null);
            if (line.qty + 1 > available) { this._flashStockLimit(available); this.bump(); return; }
            line.qty++;
            playAdd();
            this.bump();
        },
        dec(line) { line.qty--; if (line.qty <= 0) this.remove(line); this.bump(); },
        remove(line) {
            this.cart = this.cart.filter((l) => l.lid !== line.lid);
            if (!this.cart.length && this.state === 'cart') this.state = 'browse';
            this.bump();
        },

        // ── checkout mode (Slice 3) — pay at the kiosk via QR ────
        get payItems() {
            return this.cart.map((l) => ({
                product_id: l.pid,
                variant_id: l.vid || null,
                quantity:   l.qty,
            }));
        },

        async startCheckout() {
            if (!this.cart.length || !this.checkoutStartUrl) return;
            if (!this.canSubmit) { this._flash(this.labels.cust_required || 'Please add your details.'); return; }
            this.bump();
            this.state = 'payment';
            this.payStatus = 'starting';
            this.payQr = '';

            const uuid = this._orderUuid();

            try {
                const { data } = await posPost(this.checkoutStartUrl, {
                    items: this.payItems, local_uuid: uuid,
                    customer_name: this.custName || null, customer_phone: this.custPhone || null,
                });
                this.paySession = data;
                this._payUuid   = data.uuid;
                this.payAmount  = data.amount;
                this.payQr      = await this._qr(data.pay_url);
                this.payStatus  = 'waiting';
                this._startPayPoll();
            } catch (e) {
                this.payStatus = 'failed';
                // Could be a declined card, an unreachable gateway, or a 500
                // from the server — `_fail` logs which before showing the
                // shopper-facing copy.
                this._fail(this.labels.pay_failed || 'Payment failed.', e);
                this.state = 'cart';
            }
        },

        _startPayPoll() {
            clearInterval(this._payPoll);
            const url = (this.sessionStatusUrl || '').replace('__UUID__', this._payUuid || '');
            if (!url) return;
            this._payPoll = setInterval(async () => {
                try {
                    const { data } = await posGet(url);
                    const s = data?.status || 'pending';
                    if (s === 'paid') {
                        this._stopPayPoll();
                        await this._finishCheckout();
                    } else if (['failed', 'expired', 'cancelled'].includes(s)) {
                        this._stopPayPoll();
                        this.payStatus = 'failed';
                        this._fail(this.labels.pay_failed || 'Payment failed.');
                        this.state = 'cart';
                    }
                } catch (e) { /* transient — next tick retries */ }
            }, 2000);
        },

        _stopPayPoll() { clearInterval(this._payPoll); this._payPoll = null; },

        /**
         * Session is paid — finalise the sale server-side and show the receipt
         * QR. The money is already taken, and `complete()` is idempotent by the
         * session (a repeat returns the same sale, never a second charge), so we
         * RETRY through transient hiccups — a slow/blipped response or a
         * split-second race between the paid-write and this read — instead of
         * ever telling the customer to pay again. Only after several failed
         * tries do we fall back to a "payment received, please see staff"
         * message; we never send them back to re-pay.
         */
        async _finishCheckout() {
            this.payStatus = 'paid';
            for (let attempt = 0; attempt < 6; attempt++) {
                try {
                    const { data } = await posPost(this.checkoutCompleteUrl, { uuid: this._payUuid });
                    this.receiptUrl = data?.receipt_url || '';
                    // The sale is paid but the goods are still behind the
                    // counter — the shopper needs a number to be called by.
                    this.pickupCode = data?.pickup_code || '';
                    await this._toThankyou(true);
                    return;
                } catch (e) {
                    // Keep the "payment received" screen up and try again shortly.
                    if (attempt < 5) await this._sleep(1500);
                }
            }
            // Exhausted — the charge succeeded but we couldn't finalise the
            // sale. Reassure the customer their money is safe and route them to
            // staff; do NOT flash "payment didn't go through / try again".
            this.payStatus = 'paid';
            this._flash(this.labels.pay_help || 'Payment received — please ask a staff member for your receipt.');
        },

        _sleep(ms) { return new Promise((resolve) => setTimeout(resolve, ms)); },

        async cancelCheckout() {
            this._stopPayPoll();
            const uuid = this._payUuid;
            this.state = 'cart';
            this.payStatus = 'idle';
            this.paySession = null;
            this.payQr = '';
            if (uuid && this.sessionCancelUrl) {
                try { await posPost(this.sessionCancelUrl.replace('__UUID__', uuid), {}); } catch (e) { /* best-effort */ }
            }
        },

        // ── Static UPI QR — pay at the machine, verified at the counter ──
        /**
         * Build the `upi://pay?…` deep link for the live cart total and show
         * it as a QR. Identical shape to the one the cashier and the counter
         * queue already render, so any UPI app resolves it the same way.
         *
         * Deliberately NOT a gateway: a static VPA QR has no webhook and no
         * return redirect, so nothing tells us the transfer happened. The
         * shopper taps "I've paid", we PLACE the order with a claim, and staff
         * verify it in their own UPI app before settling at the till.
         */
        async startUpi() {
            if (!this.showUpi || !this.cart.length) return;
            if (!this.canSubmit) { this._fail(this.labels.cust_required || 'Please add your details.'); return; }
            this.bump();

            const amount = this.total.toFixed(2);
            const params = new URLSearchParams({
                pa: this.upi.vpa,
                pn: this.upi.payee_name || '',
                am: amount,
                cu: this.currencyCode || 'INR',
                tn: this._orderUuid().slice(0, 12),   // our txn note — traceable on the statement
            });

            this.payAmount = amount;
            this.upiQr = await this._qr('upi://pay?' + params.toString());
            this.state = 'upi';
        },

        cancelUpi() {
            this.upiQr = '';
            this.state = 'cart';
            this.bump();
        },

        /** The shopper says the transfer went through. Place the order with a
         *  claim; staff confirm the money actually arrived. */
        async confirmUpiPaid() {
            if (this.upiClaiming) return;
            this.upiClaiming = true;
            try {
                await this.placeOrder(this.upi.method_id);
            } finally {
                this.upiClaiming = false;
            }
        },

        // ── order mode (Slice 2) — submit to the staff queue ─────
        /**
         * `claimMethodId` is set when the shopper reached here by scanning the
         * static UPI QR: the order is stamped with the method they say they
         * paid with. It is NOT a payment — `balance_due` stays at the full
         * total until staff settle it.
         */
        async placeOrder(claimMethodId = null) {
            if (this.placing || !this.cart.length || !this.placeUrl) return;
            if (!this.canSubmit) { this._fail(this.labels.cust_required || 'Please add your details.'); return; }
            this.placing = true;
            this.bump();

            const uuid = this._orderUuid();

            const payload = {
                local_uuid:     uuid,
                note:           this.note || null,
                customer_name:  this.custName || null,
                customer_phone: this.custPhone || null,
                payment_claim_method_id: claimMethodId || null,
                items:          this.cart.map((l) => ({
                    product_id: l.pid,
                    variant_id: l.vid || null,
                    quantity:   l.qty,
                })),
            };

            try {
                const { data } = await posPost(this.placeUrl, payload);
                this.pickupCode = data?.pickup_code || '';
                this.upiClaimed = !!claimMethodId;
                this._toThankyou();
            } catch (e) {
                // status 0 = no connection reached the server → save the
                // order locally and sync it when the link returns. Any real
                // server error (validation, 5xx) is surfaced instead.
                if (e?.status === 0) {
                    try {
                        await enqueueKioskOrder(payload);
                        drainQueue();               // kick a drain attempt (no-op while offline)
                        this.savedOffline = true;
                        this.upiClaimed = !!claimMethodId;
                        this._toThankyou();
                    } catch (err) {
                        this._fail(this.labels.place_failed || 'Could not place your order.', err);
                    }
                } else {
                    this._fail(this.labels.place_failed || 'Could not place your order.', e);
                }
            } finally {
                this.placing = false;
            }
        },

        /** Show the thank-you screen (pickup code for order mode, receipt QR
         *  for checkout), then auto-reset to attract so the next customer
         *  starts clean. */
        /**
         * The idempotency key for THIS cart. Minted once and reused for every
         * submit attempt, so a retry after a failed (or ambiguous) response
         * replays the SAME order instead of placing a second one — the server
         * short-circuits on `sales.local_uuid`. Cleared when the cart is done.
         */
        _orderUuid() {
            if (!this._cartUuid) {
                this._cartUuid = (window.crypto && typeof window.crypto.randomUUID === 'function')
                    ? window.crypto.randomUUID()
                    : 'kiosk-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10);
            }
            return this._cartUuid;
        },

        async _toThankyou(isCheckout = false) {
            playSuccess();
            this.cart = [];
            this.note = '';
            this.upiQr = '';
            this._cartUuid = null;   // next customer starts a fresh order key
            if (isCheckout && this.receiptUrl) {
                this.receiptQr = await this._qr(this.receiptUrl, 200);
            }
            this.state = 'thankyou';
            clearTimeout(this._tyTimer);
            // Merchant-configurable (terminal → `thankyou_seconds`). The screen
            // carries a pickup code and, after a card payment, a receipt QR —
            // it has to outlast the time it takes to dig out a phone and scan.
            this._tyTimer = setTimeout(() => this._resetSession(), this.thankyouSeconds * 1000);
        },

        /**
         * Tap-to-dismiss, but NOT while a receipt QR is on screen: a shopper
         * lining up their camera brushes the panel and the code they were about
         * to scan vanishes. With a QR up, only the explicit "Done" button (or
         * the dwell timer) ends the screen.
         */
        tapThankyou() {
            if (this.receiptQr) return;
            this.finishThankyou();
        },

        async _qr(url, width = 280) {
            if (!url) return '';
            try {
                return await QRCode.toDataURL(url, { width, margin: 1, errorCorrectionLevel: 'M' });
            } catch (e) { return ''; }
        },

        // ── Exit kiosk mode ──────────────────────────────────────
        /**
         * Press-and-hold the brand mark for EXIT_HOLD_MS to reveal the staff
         * exit prompt. There is deliberately no visible close button: a
         * customer standing at the kiosk must have no obvious route into the
         * back office. The hold is a gesture staff are trained on, and the
         * prompt it opens is still supervisor-authenticated when the terminal
         * requires it. See docs/features/kiosk-self-ordering.md §6.
         */
        startExitHold() {
            if (this.exitOpen) return;
            this.cancelExitHold();
            this.holding = true;
            this._holdTimer = setTimeout(() => {
                this.holding = false;
                this.exitOpen = true;
            }, EXIT_HOLD_MS);
        },

        cancelExitHold() {
            clearTimeout(this._holdTimer);
            this._holdTimer = null;
            this.holding = false;
        },

        async exitKiosk() {
            if (!this.exitUrl) { window.location.href = this.cashierUrl; return; }
            this.exitError = '';
            const body = this.pinRequired
                ? { email: this.exitEmail, password: this.exitPassword }
                : {};
            try {
                const { data } = await posPost(this.exitUrl, body);
                window.location.href = data?.redirect || this.cashierUrl;
            } catch (e) {
                // Bad supervisor credentials (422) stay on the prompt with a
                // message; a network error just falls through to the cashier.
                if (e?.status === 422) {
                    this.exitError = e?.message || this.labels.exit_denied || 'Not allowed.';
                } else if (!this.pinRequired) {
                    window.location.href = this.cashierUrl;
                } else {
                    this.exitError = this.labels.exit_denied || 'Not allowed.';
                }
            }
        },

        // ── Inactivity reset ─────────────────────────────────────
        bump() { this._lastActivity = Date.now(); },

        _startIdle() {
            this.bump();
            this._idleTimer = setInterval(() => {
                // Only browse/cart time out. Payment has the gateway session's
                // own TTL (a customer paying on their phone mustn't be reset);
                // thank-you + attract manage their own lifecycle.
                if (!['browse', 'cart'].includes(this.state)) return;
                if (this.exitOpen) { this.bump(); return; }
                if (Date.now() - this._lastActivity >= this.idleTimeout * 1000) {
                    this._resetSession();
                }
            }, 1000);
        },

        /** Dismiss the thank-you screen immediately (the "Done" button). */
        finishThankyou() {
            clearTimeout(this._tyTimer);
            this._resetSession();
        },

        /** Discard the in-progress cart and return to attract — so an
         *  abandoned order can never be paid by the next customer. */
        _resetSession() {
            clearTimeout(this._tyTimer);
            this._stopPayPoll();
            this.cancelExitHold();
            this.cart = [];
            this.note = '';
            this._cartUuid = null;
            this.search = '';
            this.selectedCat = null;
            this.variantPicker = null;
            this.kitDetail = null;
            this.custName = '';
            this.custPhone = '';
            this.exitEmail = '';
            this.exitPassword = '';
            this.exitError = '';
            this.pickupCode = '';
            this.receiptUrl = '';
            this.receiptQr = '';
            this.savedOffline = false;
            this.payQr = '';
            this.paySession = null;
            this.payStatus = 'idle';
            this.placing = false;
            this.upiQr = '';
            this.upiClaimed = false;
            this.upiClaiming = false;
            this.state = 'attract';
        },

        // ── Keyboard-wedge scanner (basic exact match) ───────────
        _startScanner() {
            window.addEventListener('keydown', (e) => {
                if (this.state === 'attract') return;
                // Ignore typing into the search box — that's a manual query.
                if (document.activeElement === this.$refs.searchInput) return;

                const now = Date.now();
                if (now - this._scanAt > 120) this._scanBuf = '';
                this._scanAt = now;

                if (e.key === 'Enter') {
                    const code = this._scanBuf.trim();
                    this._scanBuf = '';
                    if (code.length >= 3) this._scan(code);
                    return;
                }
                if (e.key.length === 1) this._scanBuf += e.key;
            });
        },

        _scan(code) {
            const c = code.toLowerCase();

            // A scanned variant barcode/sku resolves straight to that variant —
            // no picker needed, the customer already chose by scanning it.
            for (const p of this.products) {
                if (!Array.isArray(p.variants) || !p.variants.length) continue;
                const v = p.variants.find(
                    (v) => String(v.barcode || '').toLowerCase() === c
                        || String(v.sku || '').toLowerCase() === c,
                );
                if (v) {
                    if ((Number(v.charge_price ?? v.selling_price) || 0) > 0) {
                        this.add(p, v);
                        this._flash(p.name + ' — ' + (v.label || v.sku || ''));
                    }
                    this.bump();
                    return;
                }
            }

            const hit = this.products.find(
                (p) => String(p.barcode || '').toLowerCase() === c
                    || String(p.sku || '').toLowerCase() === c,
            );
            // Same rule as the grid: an unpriced item isn't sellable here. A
            // scanned kit opens its "what's included" sheet; a variant parent
            // opens the option picker; a plain product adds straight away.
            if (hit && this._kioskPrice(hit) > 0) {
                if (hit.is_kit) { this.openKit(hit); }
                else if (hit.has_variants) { this.openVariant(hit); }
                else { this.add(hit); this._flash(hit.name); }
            }
            this.bump();
        },

        /** `kind` drives the icon + colour: a failure must not show a tick. */
        _flash(msg, kind = 'success') {
            this.toast = msg;
            this.toastKind = kind;
            clearTimeout(this._toastTimer);
            this._toastTimer = setTimeout(() => { this.toast = ''; }, kind === 'error' ? 4000 : 2400);
        },

        /**
         * Something the shopper can't fix. Shows the friendly copy, but logs the
         * real cause: a 500 from a missing migration and a declined card both
         * end up here, and staff shouldn't have to guess which one they hit.
         */
        _fail(msg, err) {
            if (err) console.error('[kiosk]', msg, err.status ?? '', err.message ?? err);
            playReject();
            this._flash(msg, 'error');
        },
    };
}

document.addEventListener('alpine:init', () => {
    Alpine.data('kioskApp', kioskApp);
});

window.Alpine = Alpine;
Alpine.start();
