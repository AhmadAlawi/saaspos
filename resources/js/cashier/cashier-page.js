/**
 * Alpine factory for the cashier checkout screen (/cashier).
 *
 * Real-world POS layout: the product catalog renders as a grid of tiles
 * from the moment the screen loads. Category chips narrow it, search
 * narrows it further. Tap a tile → add to cart. Cart sits on the right
 * with a sticky PAY pill.
 *
 * Slice 1 ships:
 *   - product grid (client-side filtered against the upfront catalog)
 *   - category chips
 *   - barcode/name/SKU search (instant client-side narrow)
 *   - cart with qty steppers, totals, cash + card_manual payment
 *   - 4-second success overlay + receipt link
 *
 * Slice 2 polish targets (per the doc / NOT in this slice):
 *   - customer assign + walk-in toggle
 *   - hold/resume drawer + recents footer + reprint last
 *   - quick-tender preset tiles + split tender
 *   - variants picker, weighted items, kit lines
 *   - shift gate
 *   - Stripe / Razorpay / Flutterwave gateway tiles (Slice 5)
 */

import { posGet, posPost } from '../lib/http.js';
import QRCode from 'qrcode';
import { hasCatalog, readCatalogBlob, enqueueSale, searchCachedCustomers, readCachedBatches, enqueueCustomerCreate, nextLocalCustomerId } from '../offline/dexie-schema.js';
import { refreshCatalog } from '../offline/catalog-refresher.js';
import { startConnectivity, subscribe as subscribeConnectivity, forcePing } from '../offline/connectivity.js';
import { prefetchCatalogImages } from '../offline/image-prefetcher.js';
import { startSyncEngine, subscribeQueue, drainQueue } from '../offline/sync-engine.js';
import { startPwa, subscribePwa, promptInstall, applyUpdate } from '../offline/pwa-installer.js';
import { decodeScaleBarcode } from './scale-barcode.js';
import { playAddBeep } from './beep.js';
import { decodeGs128, gtinMatches } from './gs1-128.js';
import { printReceipt, printHtml, openDrawer, DrawerKickUnavailable } from '../hardware/print-bridge.js';
import { enqueueFailedPrint } from '../hardware/print-queue.js';
import { terminalPrinterConfig } from '../hardware/terminal-config.js';
import { renderOfflineReceiptHtml } from '../offline/receipt-renderer.js';
import { createCfdPublisher } from './cfd-broadcast.js';

export function cashierPage({
    completeUrl,
    customerSearchUrl,
    customerStoreUrl,
    batchesUrl,
    holdUrl,
    holdsListUrl,
    holdResumeUrlTemplate,
    holdVoidUrlTemplate,
    storeId,
    storeName,
    products = [],
    quickPickIds = [],
    categories = [],
    paymentMethods = [],
    returnReasons = [],
    successDetailUrlTemplate,
    receiptUrlTemplate,
    printPayloadUrlTemplate,
    refundPrintPayloadUrlTemplate,
    correctedCopyPrintPayloadUrlTemplate,
    printLogUrl,
    reprintLastUrl,
    recentsListUrl,
    drawerNoSaleUrl = null,
    refundLookupUrl,
    refundShowUrlTemplate,
    refundStoreUrlTemplate,
    refundStoreBlindUrl,
    refundApprovalUrl,
    gatewayStartUrl,
    gatewayStatusUrl,
    posSessionCreateUrl,
    posSessionStatusUrlTemplate,
    posSessionCancelUrlTemplate,
    stripeMethodId = null,
    settings = {},
    // Pre-translated toast/validation/confirm strings — see
    // SaleController::cashier() and lang/en/cashier.php's `js` section.
    // Left as raw bootstrap data (NOT merged with defaults) — every call
    // site below falls back to its own inline English string instead, so
    // a missing key here never blanks the UI. Mirrors kioskApp()'s
    // `labels` in resources/js/kiosk/kiosk-app.js.
    labels = {},
    receipt = null,
    // Customer-Facing Display (CFD). `cfdChannel` is the same-machine
    // BroadcastChannel name (scoped per terminal); `displayUrl` opens the
    // second-screen window. `cfdTransport` = 'same_machine' | 'separate_device';
    // when separate, snapshots are ALSO relayed to `cfdPushUrl` for a tablet
    // to poll. See docs/features/customer-display.md §5.
    cfdChannel = null,
    displayUrl = null,
    cfdTransport = 'same_machine',
    cfdPushUrl = null,
} = {}) {
    // Defaults mirror Company::cashier() so a fresh install (or a missing
    // key after a code upgrade) still renders a sensible UI.
    settings = Object.assign({
        layout:           'focus',
        tile_size:        'comfortable',
        theme_default:    'auto',
        show_tax_line:    true,
        show_from_prefix: true,
        show_quick_picks: false,
        default_category: 'all',
    }, settings);
    // Scale-barcode template (Settings → Scale). Disabled by default so a
    // fresh install never mis-reads a normal barcode as an embedded-weight one.
    settings.scale = Object.assign({
        enabled:        false,
        prefix:         '2',
        plu_length:     5,
        value_offset:   0,
        value_length:   5,
        value_decimals: 3,
        embed_type:     'weight',
    }, settings.scale || {});

    // Monotonic CFD frame counter — kept in the factory closure (not on the
    // reactive object) so publishCfd() can bump it inside an x-effect without
    // re-triggering that effect. See publishCfd().
    let cfdSeq = 0;

    // While a "thank you" frame is on screen, block the reactive
    // cart-cleared → idle frame from stomping it until this timestamp
    // passes. A genuinely new sale (non-idle) still gets through. This is
    // what makes the thank-you screen + receipt QR show reliably.
    let cfdSuppressIdleUntil = 0;

    return {
        /* ── Catalog state ─────────────────────────────────────── */
        products,
        categories,
        settings,
        quickPickIds,
        searchQuery:      '',
        scanQuery:        '',
        selectedCategory: settings.default_category || 'all',   // 'all' | <id-string>

        /* ── Camera scanner (ZXing) — modal state ──────────────── */
        // ZXing is heavy, so the scanner module is dynamically imported in
        // openCamera() — it stays out of the main bundle until a cashier
        // actually scans with the camera. Support is a cheap inline check.
        cameraOpen:       false,
        cameraSupported:  !!(typeof navigator !== 'undefined'
                            && navigator.mediaDevices
                            && navigator.mediaDevices.getUserMedia),
        cameraError:      '',
        _cameraScanner:   null,

        // Receipt printing (success overlay) — drives the print bridge.
        printingReceipt:  false,
        // Company/store receipt settings + labels for the client-side
        // offline receipt renderer (offline-sync doc §13). Null on a
        // paging/sync response, present on the full page render.
        receiptConfig:    receipt,

        /* ── Customer-Facing Display (CFD) ─────────────────────── */
        // Same-machine second screen. `_cfdPublisher` is lazily created in
        // init() when a channel is configured. The monotonic frame counter
        // lives in a closure (`cfdSeq`), NOT reactive state — an x-effect
        // that both reads and writes it would loop.
        cfdChannel,
        displayUrl,
        cfdTransport,
        cfdPushUrl,
        _cfdPublisher: null,
        _cfdPushTimer: null,
        _cfdPending:   null,

        /* ── Live layout switcher (toolbar segmented control) ─── */
        // The admin setting is always the authoritative starting point on
        // page load. The toolbar buttons switch layout for the session only;
        // changes are not persisted so the admin setting applies every time.
        currentLayout: (['lane', 'beam', 'counter', 'focus'].includes(settings.layout)
            ? settings.layout : 'focus'),

        // Grid pagination. The full catalog lives in memory (synced into
        // IDB in background pages), but we only render `visibleLimit` tiles
        // so the DOM stays light even for 1000s of SKUs. Scrolling near the
        // bottom auto-loads the next `gridChunk` (infinite scroll); the
        // "Load more" button is the manual fallback. Category / search
        // changes reset the limit so users always see the start.
        visibleLimit: 25,
        gridChunk:    25,

        /* ── Cart state ────────────────────────────────────────── */
        cart:        [],
        _nextLineId: 1,
        clientUuid:  null,

        /* ── Mobile Navigation state ───────────────────────────── */
        mobileTab:   'catalog',   // 'catalog' | 'cart'

        /* ── Customer assign ───────────────────────────────────── */
        customer:               null,   // null = walk-in
        customerPickerOpen:     false,
        customerPickerQuery:    '',
        customerPickerResults:  [],
        customerPickerLoading:  false,
        _customerSearchTimer:   null,
        customerAddOpen:        false,
        customerAddForm:        { name: '', phone: '', email: '' },
        customerAddSubmitting:  false,

        /* ── Variant picker ────────────────────────────────────── */
        variantPickerOpen:    false,
        variantPickerProduct: null,   // the parent product being picked

        /* ── Batch picker (pharmacy / FEFO) ────────────────────── */
        // For batch-tracked products: when added to cart, fetch the
        // live batches (FEFO-sorted) and let the cashier pick which
        // physical stock goes out. Picked batch_id rides on the line.
        batchPickerOpen:     false,
        batchPickerProduct:  null,   // parent product being batch-picked
        batchPickerVariant:  null,   // optional variant if the parent is variant-tracked
        batchPickerLoading:  false,
        batchPickerResults:  [],

        /* ── Weight prompt (sold-by-weight items) ──────────────── */
        // Loose items priced per unit-of-weight (produce, deli, bulk).
        // Instead of landing in the cart at qty 1, the cashier enters the
        // measured weight and the line quantity becomes that weight. The
        // already-resolved line is parked in `weightPromptLine` until the
        // cashier confirms or cancels.
        weightPromptOpen:    false,
        weightPromptLine:    null,   // resolved line data awaiting a weight
        weightPromptName:    '',     // product label shown in the modal
        weightPromptUnit:    '',     // unit-of-weight (kg / g / lb …)
        weightPromptPrice:   '0',    // unit price, for the live line-total preview
        weightInput:         '',     // the cashier's typed weight

        /* ── Discount (order-level) ────────────────────────────── */
        // Shape matches the canonical mockup's `discount` object:
        //   type: null  | 'pct' | 'amt'
        //   value: number (percent 0–100 OR currency amount)
        // `source: 'customer_default'` marks a discount auto-applied from the
        // selected customer's default — see `_applyCustomerDefaultDiscount`.
        // Every reset replaces this object wholesale, so the marker clears
        // itself; no separate flag to keep in sync.
        discount: { type: null, value: 0 },
        showDiscount: false,
        _discountForm: { kind: 'pct', val: 10 },

        /* ── Manager approval for over-threshold discounts (Slice 2) ── */
        approvalOpen:       false,
        approvalSubmitting: false,
        approvalForm:       { pin: '' },
        // Set once a manager approves: { token, approver, percent }. The
        // token rides along in complete()'s payload; the server re-verifies.
        discountApproval:   null,
        // The pending approval: { revert, effectivePct }. `revert` undoes the
        // just-applied discount if the manager cancels / fails.
        _pendingApproval:   null,

        /* ── Per-line discount (Slice 4) ───────────────────────────── */
        lineDiscOpen:   false,
        _lineDiscLineId: null,
        _lineDiscForm:  { kind: 'pct', val: 10 },

        /* ── Per-line note ─────────────────────────────────────── */
        noteLineId:  null,     // id of the line whose note modal is open
        noteText:    '',

        /* ── Hold / Resume ─────────────────────────────────────── */
        holdNamePromptOpen: false,
        holdLabel:          '',
        holdSubmitting:     false,
        heldDrawerOpen:     false,
        heldList:           [],
        heldLoading:        false,
        heldVoidingId:      null,

        /* ── Refund / Return ──────────────────────────────────── */
        refundLookupOpen:     false,
        refundLookupQuery:    '',
        refundLookupResults:  [],
        refundLookupLoading:  false,
        _refundLookupTimer:   null,
        refundOpen:           false,
        refundSale:           null,
        refundRows:           [],   // [{id, name, sku, variant, unit, unit_price, quantity, returned, remaining, tax_amount, discount_amount, refundQty}]
        refundReasons:        [],
        refundMethods:        [],
        refundReasonId:       null,
        refundMethodId:       null,
        refundRestock:        true,
        refundNotes:          '',
        refundClientUuid:     null,
        refundSubmitting:     false,
        refundSuccessOpen:    false,
        refundSuccess:        null,
        // Set by refund/{sale} — whether the LOGGED-IN cashier already
        // holds sales.refund directly (skip the PIN step) or needs a
        // manager's approval before submit().
        refundCanRefund:      false,

        /* ── Blind refund (scan, no invoice) ───────────────────── */
        blindRefundOpen:       false,
        blindScanQuery:        '',
        blindRefundRows:       [],   // [{code, name, sku, qty, unit_price}]
        blindRefundReasonId:   null,
        blindRefundMethodId:   null,
        blindRefundRestock:    true,
        blindRefundNotes:      '',
        blindRefundSubmitting: false,
        blindRefundSuccessOpen: false,
        blindRefundSuccess:    null,

        /* ── Manager PIN approval for a refund (either flow above) ── */
        refundApprovalOpen:       false,
        refundApprovalSubmitting: false,
        refundApprovalForm:       { pin: '' },
        // Banked once approved: { token, approver, amount }. Cleared the
        // moment a refund actually goes through — a fresh refund always
        // needs a fresh approval, never reuses a spent token.
        refundApproval:           null,
        // What happens once approved — set right before opening the
        // modal so submitApproval() knows which flow to continue.
        _pendingRefundApproval:   null, // { kind: 'invoice'|'blind', amount }

        /* ── Focus layout — price-check modal ── */
        // Focus hides the product catalog and category chips entirely —
        // it's scan/search-only, with the cart as the sole content pane
        // next to the action rail (see cashier.css).
        priceCheckOpen:   false,
        priceCheckQuery:  '',
        priceCheckResult: null, // { name, sku, variant_label, selling_price, sale_price, charge_price } | 'not_found' | null

        /* ── Overflow menu ─────────────────────────────────────── */
        overflowOpen: false,

        /* ── Open drawer (no sale) ─────────────────────────────── */
        drawerNoSaleOpen:   false,
        drawerNoSaleReason: '',
        drawerNoSaleBusy:   false,

        /* ── Recent sales drawer ─────────────────────────────── */
        recentsDrawerOpen: false,
        recentsLoading:    false,
        recentsList:       [],

        /* ── Keyboard shortcuts help modal ────────────────────── */
        shortcutsHelpOpen: false,

        /* ── Payment modal ─────────────────────────────────────── */
        // `payments` is the list of rows the cashier has ALREADY committed
        // for this ring-up (split tender). The fields on the right pane
        // (payMethodId/payTendered/payReference) describe the row that's
        // currently being entered — it's pushed onto `payments` when the
        // cashier hits "Add payment" (split) or "Complete sale" (final).
        payOpen:      false,
        payments:     [],   // [{ payment_method_id, method_name, method_type, amount, tendered_amount, reference }]
        payMethodId:  null,
        payTendered:  '',
        payReference: '',
        submitting:   false,

        // Set only while a wallet-button session (Apple Pay / Google Pay)
        // is open — same Stripe Checkout Session either way, this just
        // carries which button the cashier tapped through to the CFD
        // label (see startPaymentSession() and _cfdSnapshot()).
        walletBrand: null,
        // Re-exposed here (not just the closure param) so the blade's
        // `x-if="stripeMethodId"` can actually see it — Alpine template
        // expressions only resolve against the returned reactive object,
        // not the factory function's outer closure scope.
        stripeMethodId,

        /* ── POS payment session (QR-chooser flow) ──────────────
         * The cashier picks "Charge via QR" → backend creates a
         * pos_payment_sessions row + returns its public URL on our
         * own domain. The customer scans that URL → picks any
         * configured gateway → pays. The cashier polls status here
         * until paid; on paid the sale auto-completes carrying the
         * session's gateway info. */
        paySession:       null,      // { uuid, pay_url, amount, currency, expires_at }
        payStatus:        'idle',    // 'idle' | 'starting' | 'pending' | 'selected' | 'paid' | 'failed' | 'expired'
        payQrDataUrl:     '',
        _payPollTimer:    null,
        _payCountdownTimer: null,
        payCountdown:     '',        // "13:42" — live mm:ss until expires_at

        /* UPI VPA — manual-confirm path; we render a `upi://pay?…` QR
         * customer scans, pays in their UPI app, cashier types the UTR
         * into the reference field. No webhook, no polling, no API. */
        upiQrDataUrl: '',
        upiPayUrl:    '',            // raw deep-link, exposed for "open" links on mobile
        upiTr:        '',            // our transaction ref (POS-…); stable across QR re-renders
                                     // until the modal closes, so a slow scanner doesn't get a
                                     // different ref than what landed in the customer's bank statement

        /* ── Success overlay ───────────────────────────────────── */
        successOpen: false,
        successSale: null,

        storeName,
        paymentMethods,
        returnReasons,
        labels,

        /* ── Offline-first (Slice 1+2) ───────────────────────────
         * Connectivity is reactive so the topbar dot + the
         * "Refresh data" popover can read it. queueDepth reflects
         * the live count of offline-queued sales waiting to sync —
         * Slice 2's sync engine pushes updates on every queue mutation. */
        connectivity: { status: 'unknown', lastOk: null, consecutiveFails: 0 },
        refreshing:   false,
        queueDepth:   0,

        /* ── Offline-first (Slice 3) — PWA + Service Worker ──────
         * `pwa.installable` flips true when the browser fires the
         * `beforeinstallprompt` event (PWA criteria met). The
         * connectivity popover surfaces an Install button.
         * `pwa.updateAvailable` flips true when a new SW version
         * has installed and is waiting — we toast the cashier so
         * they can refresh on demand instead of being interrupted. */
        pwa: { installable: false, updateAvailable: false },

        /* ── Lifecycle ─────────────────────────────────────────── */

        async init() {
            this._resetClientUuid();
            // Customer-facing display bridge — opens the same-machine
            // BroadcastChannel the second screen listens on. The publish
            // itself is driven reactively by an `x-effect` in the Blade
            // (fires whenever the cart / totals / customer change).
            if (this.cfdChannel) {
                try { this._cfdPublisher = createCfdPublisher(this.cfdChannel); } catch (e) { /* no-op */ }
                // Sale completion → brief "thank you" frame on the display.
                this.$watch('successOpen', (v) => { if (v) this._cfdThankyou(); });
            }
            // Inline seed arrives in id order from the server; sort by name
            // so the first paint matches the post-sync ordering.
            this.products = this._sortProducts(this.products);
            // Cashier-specific theme default is applied by CashierLayout
            // via the `pos-theme-default` meta tag — the global `ui` store
            // reads it as the fallback when the user has no saved choice.
            this.$nextTick(() => this.focusSearch());

            // ── Offline boot order ──────────────────────────────
            //
            // 1. Inline payload from the server-rendered page already
            //    populated `this.products / .categories / .paymentMethods`
            //    above. The screen is ALREADY interactive at this point.
            //
            // 2. If IDB has a fresher catalog (we've been online once
            //    in the past), swap PRODUCTS/CATEGORIES to that — it's
            //    the same shape, and on a stale cold-load page the IDB
            //    read MAY have newer stock numbers than what the HTML
            //    render saw. Payment methods are deliberately NOT
            //    swapped here: unlike stock counts, they're never
            //    "fresher" in a stale IDB snapshot than in the page
            //    that was just rendered — an admin disabling a method
            //    server-side needs that to take effect on the very next
            //    load, not get silently overwritten by whatever this
            //    terminal happened to cache before the change (confirmed
            //    bug: a terminal that synced once before an admin edit
            //    kept showing the disabled methods at checkout even
            //    after a hard reload, since this exact line clobbered
            //    the correct freshly-rendered list every time).
            //
            // 3. After paint, fire a background catalog refresh. If
            //    we're online, IDB catches up with the server (payment
            //    methods included — that path is a genuine fresh pull,
            //    not a stale replay, so it's fine to apply there). If
            //    we're offline, the refresher silently no-ops and the
            //    cashier keeps using whatever's already in memory.
            try {
                if (await hasCatalog()) {
                    const blob = await readCatalogBlob();
                    if (Array.isArray(blob.products) && blob.products.length > 0) {
                        this.products   = this._sortProducts(blob.products);
                        this.categories = blob.categories;
                    }
                }
            } catch (e) {
                // IDB unavailable (private mode? quota?) — silently
                // fall back to the inline payload. Logged for the
                // diagnostics screen we'll add later.
                console.warn('[cashier] IDB read failed; using inline payload', e);
            }


            // Background catalog refresh — never awaited; never throws.
            refreshCatalog().then(async (result) => {
                if (!result) return;
                try {
                    const blob = await readCatalogBlob();
                    if (Array.isArray(blob.products) && blob.products.length > 0) {
                        this.products       = this._sortProducts(blob.products);
                        this.categories     = blob.categories;
                        this.paymentMethods = blob.payment_methods;
                    }
                } catch (_) { /* no-op */ }
            });

            // Pre-warm the browser cache for every product image so
            // the cart line / barcode-scan flow paints from cache when
            // the cashier later goes offline. `loading="lazy"` on the
            // tile <img> means below-the-fold images would otherwise
            // never download until scrolled — and then they're gone
            // the moment the network drops.
            prefetchCatalogImages(this.products);

            // Connectivity machine — single global subscription. The
            // unsubscribe handle lives on the Alpine instance so it's
            // cleaned up when the page navigates away.
            this._unsubConn = subscribeConnectivity((next) => {
                this.connectivity = next;
            });
            startConnectivity();

            // Slice 2: subscribe to queue depth + boot the sync engine.
            // The engine drains automatically on `online` events from
            // the connectivity machine — so any sales queued during
            // an outage flow back to the server as soon as the dot
            // turns green.
            this._unsubQueue = subscribeQueue((depth) => {
                this.queueDepth = depth;
            });
            startSyncEngine();

            // Slice 3: register the service worker + listen for the
            // PWA install prompt and update-available signal. SW is
            // gated to /cashier paths inside pwa-installer (HTTPS
            // only; localhost dev gets a pass).
            this._unsubPwa = subscribePwa((next) => {
                const wasUpdateAvailable = this.pwa.updateAvailable;
                this.pwa = next;
                // First-time update-available transition → toast with
                // an action to apply the new SW immediately.
                if (!wasUpdateAvailable && next.updateAvailable) {
                    this.$store.toasts?.push({
                        type:     'info',
                        message:  this.labels.pwa_update_ready || 'A new version is ready. Tap the connectivity pill → Update.',
                        duration: 8000,
                    });
                }
            });
            startPwa();

            // Mouse-wheel → horizontal scroll on the category chip strip.
            // The strip is overflow-x:auto and mouse wheels only emit
            // deltaY by default — translate it here so a cashier with a
            // desk mouse can scrub categories without holding shift.
            this.$nextTick(() => {
                const strip = document.querySelector('.cashier-cats');
                if (!strip) return;
                strip.addEventListener('wheel', (e) => {
                    // Only intercept when the user has scrolling room and
                    // they're not already moving horizontally (touchpads
                    // generate deltaX themselves).
                    if (e.deltaY === 0 || Math.abs(e.deltaX) > Math.abs(e.deltaY)) return;
                    const max = strip.scrollWidth - strip.clientWidth;
                    if (max <= 0) return;
                    e.preventDefault();
                    strip.scrollLeft += e.deltaY;
                }, { passive: false });
            });

            // Watch payment/success modal close → re-focus the search bar
            // so the cashier can immediately start the next ring-up.
            this.$watch('payOpen',     (v) => { if (!v) this.$nextTick(() => this.focusSearch()); });
            this.$watch('successOpen', (v) => { if (!v) this.$nextTick(() => this.focusSearch()); });

            // Re-render the UPI QR whenever the amount on the wire
            // changes — split-tender edits, or when the cashier
            // re-picks the same method with a different remaining.
            this.$watch('payTendered', () => { this._refreshUpiQr?.(); });

            // Typing in the search box should reset pagination — otherwise
            // the cashier sees stale "more available" hints from the prior
            // (broader) result set.
            this.$watch('searchQuery', () => { this.visibleLimit = this.gridChunk; });

            // Keyboard shortcuts matching the canonical mockup:
            //   F2 → customer picker
            //   F3 → discount
            //   F4 → hold
            //   F8 → focus scan-barcode input
            //   F9 → checkout
            //   `/` → focus search
            this._onKeyDown = (e) => {
                if (e.altKey || e.ctrlKey || e.metaKey) return;
                const tag = (e.target?.tagName || '').toLowerCase();
                const inField = tag === 'input' || tag === 'textarea' || tag === 'select' || e.target?.isContentEditable;
                if (e.key === 'F2') {
                    e.preventDefault();
                    this.openCustomerPicker();
                } else if (e.key === 'F3') {
                    e.preventDefault();
                    if (this.cart.length > 0) this.openDiscount();
                } else if (e.key === 'F4') {
                    e.preventDefault();
                    this.onHoldClick();
                } else if (e.key === 'F8') {
                    e.preventDefault();
                    this.focusScan();
                } else if (e.key === 'F9') {
                    e.preventDefault();
                    if (this.cart.length > 0) this.openPay();
                } else if (e.key === '/' && !inField) {
                    e.preventDefault();
                    this.focusSearch();
                } else if (!inField && e.key === 'Enter') {
                    // A hardware barcode scanner types the code as fast
                    // keystrokes then sends Enter — same shape whether or
                    // not the cashier's cursor happens to be sitting in
                    // the scan field. When it isn't, this buffer (built up
                    // below) catches the burst and completes the scan
                    // exactly like typing it into #data-cashier-scan would.
                    // A lone Enter with nothing buffered (or a stale,
                    // slow-arriving buffer — a human pressing Enter alone)
                    // falls through and does nothing, same as before.
                    const buffered = this._scanBuffer.trim();
                    this._scanBuffer = '';
                    if (buffered.length >= 3 && Date.now() - this._scanBufferAt < 500) {
                        e.preventDefault();
                        this.scanQuery = buffered;
                        this.onScanEnter();
                    }
                } else if (!inField && e.key.length === 1 && !e.ctrlKey && !e.metaKey) {
                    // Long pause since the last keystroke → this is a new
                    // burst, not a continuation of unrelated stray keys.
                    const now = Date.now();
                    if (now - this._scanBufferAt > 150) this._scanBuffer = '';
                    this._scanBuffer += e.key;
                    this._scanBufferAt = now;
                }
            };
            this._scanBuffer = '';
            this._scanBufferAt = 0;
            window.addEventListener('keydown', this._onKeyDown);

            // Infinite scroll — when a bottom sentinel scrolls into view and
            // there are more matches, reveal the next chunk. The sentinels
            // sit inside the `x-show="hasMoreProducts"` footers, so they only
            // have layout (and thus only intersect) when there's more to load.
            this.$nextTick(() => this._initInfiniteScroll());
        },

        destroy() {
            if (this._onKeyDown)  window.removeEventListener('keydown', this._onKeyDown);
            if (this._unsubConn)  this._unsubConn();
            if (this._unsubQueue) this._unsubQueue();
            if (this._unsubPwa)   this._unsubPwa();
            this._gridObserver?.disconnect();
            this._stopPaymentTimers?.();
        },

        /** Name-sorted copy — transport order from /sync is by id, but the
         *  grid reads best alphabetically. Stable + cheap for a few k rows. */
        _sortProducts(arr) {
            return [...(arr ?? [])].sort((a, b) =>
                String(a.name || '').localeCompare(String(b.name || '')));
        },

        _initInfiniteScroll() {
            if (!('IntersectionObserver' in window)) return;
            const sentinels = this.$root.querySelectorAll('.js-grid-sentinel');
            if (!sentinels.length) return;
            this._gridObserver = new IntersectionObserver((entries) => {
                if (entries.some((e) => e.isIntersecting) && this.hasMoreProducts) {
                    this.loadMoreProducts();
                }
            }, { rootMargin: '300px' });
            sentinels.forEach((el) => this._gridObserver.observe(el));
        },

        /* ── Offline-first helpers (Slice 1) ──────────────────── */

        /**
         * "Refresh data now" — wired to the connectivity popover.
         * Re-fetches the catalog and pings the heartbeat so the dot
         * flips to green immediately instead of waiting for the 30s
         * poll. Surfaces a toast on success/failure so the cashier
         * gets feedback for the click.
         */
        async refreshNow() {
            if (this.refreshing) return;
            this.refreshing = true;
            try {
                const [result] = await Promise.all([refreshCatalog(), forcePing()]);
                if (result) {
                    const blob = await readCatalogBlob();
                    if (Array.isArray(blob.products) && blob.products.length > 0) {
                        this.products       = this._sortProducts(blob.products);
                        this.categories     = blob.categories;
                        this.paymentMethods = blob.payment_methods;
                    }
                    this.$store.toasts?.push({ type: 'success', message: this.labels.catalog_refreshed || 'Catalog refreshed.' });
                } else {
                    this.$store.toasts?.push({ type: 'warning', message: this.labels.catalog_refresh_failed || 'Couldn\'t reach the server — using cached data.' });
                }
            } finally {
                this.refreshing = false;
            }
        },

        /**
         * Trigger the deferred PWA install prompt. The browser fires
         * `beforeinstallprompt` exactly once per session — we cached
         * it in pwa-installer; this just kicks it off.
         */
        async installNow() {
            await promptInstall();
        },

        /**
         * Tell the waiting service worker to take over now. The
         * pwa-installer listens for `controllerchange` and reloads
         * the page so the new bundles activate.
         */
        updateNow() {
            applyUpdate();
        },

        /**
         * Human "last synced" relative time for the popover. Cheap
         * computation; Alpine re-runs it whenever connectivity emits.
         */
        get lastSyncedLabel() {
            const t = this.connectivity?.lastOk;
            if (!t) return this.labels.sync_never || 'never';
            const ago = Math.max(0, Math.floor((Date.now() - new Date(t).getTime()) / 1000));
            if (ago < 60)   return (this.labels.sync_seconds_ago || ':ns ago').replace(':n', ago);
            if (ago < 3600) return (this.labels.sync_minutes_ago || ':nm ago').replace(':n', Math.floor(ago / 60));
            return (this.labels.sync_hours_ago || ':nh ago').replace(':n', Math.floor(ago / 3600));
        },

        _resetClientUuid() {
            this.clientUuid = (window.crypto && typeof window.crypto.randomUUID === 'function')
                ? window.crypto.randomUUID()
                : 'fb-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10);
        },

        focusSearch() {
            document.querySelector('[data-cashier-search]')?.focus();
        },

        focusScan() {
            document.querySelector('[data-cashier-scan]')?.focus();
        },

        /* ── Filtering ─────────────────────────────────────────── */

        /**
         * Filtered set — matches selected category AND search query.
         * Search matches barcode (exact), SKU (exact), or name (substring).
         * This is the FULL match list; `visibleProducts` is the slice we
         * actually render.
         */
        /**
         * Quick picks — the store's most-sold products as chips above the
         * grid (gated by the `show_quick_picks` cashier setting). The server
         * sends only the ids; we map them to the loaded catalog so a chip tap
         * reuses the normal `addToCart()` path. Ids not yet in the catalog
         * (large stores still background-syncing) are simply skipped.
         */
        get quickPicks() {
            if (!this.settings.show_quick_picks || !this.quickPickIds?.length) return [];
            const byId = new Map(this.products.map((p) => [p.id, p]));
            return this.quickPickIds.map((id) => byId.get(id)).filter(Boolean);
        },

        get filteredProducts() {
            const q = this.searchQuery.trim().toLowerCase();
            const cat = this.selectedCategory;

            return this.products.filter((p) => {
                if (cat !== 'all' && String(p.category_id ?? '') !== String(cat)) return false;
                if (q === '') return true;
                // Substring match on every field so partial SKUs ("AMX"
                // for "AMX-500-CAP") and partial barcodes work in the
                // search box. Exact scans still resolve to a single
                // tile via the prefix-match ranking.
                if ((p.barcode || '').toLowerCase().includes(q)) return true;
                if ((p.sku || '').toLowerCase().includes(q)) return true;
                if ((p.name || '').toLowerCase().includes(q)) return true;
                // Variant SKU + barcode + label — cashier typing a
                // per-variant SKU ("SHIRT-RED-L") expects the parent
                // tile to surface so they can pick the right variant.
                if (Array.isArray(p.variants)) {
                    for (const v of p.variants) {
                        if ((v.sku     || '').toLowerCase().includes(q)) return true;
                        if ((v.barcode || '').toLowerCase().includes(q)) return true;
                        if ((v.label   || '').toLowerCase().includes(q)) return true;
                    }
                }
                return false;
            });
        },

        get visibleProducts() {
            return this.filteredProducts.slice(0, this.visibleLimit);
        },

        get hasMoreProducts() {
            return this.filteredProducts.length > this.visibleLimit;
        },

        get hiddenCount() {
            return Math.max(0, this.filteredProducts.length - this.visibleLimit);
        },

        /**
         * Human label for whatever category tab is currently active —
         * used in the Counter sub-header ("Beverages · 12 items").
         */
        get currentCategoryName() {
            if (this.selectedCategory === 'all') return null;
            const cat = this.categories.find((c) => String(c.id) === String(this.selectedCategory));
            return cat?.name ?? null;
        },

        loadMoreProducts() {
            this.visibleLimit += this.gridChunk;
        },

        pickCategory(id) {
            this.selectedCategory = id;
            this.visibleLimit     = this.gridChunk;  // start back at the top
        },

        /**
         * Enter on the dedicated scan input — adds an exact barcode/SKU
         * match and clears the field so the next scan goes straight in.
         * If the scanner sends keystrokes without trailing Enter, the
         * `x-model` debounce on input won't fire this — most USB
         * scanners are configured with a CR suffix by default.
         */
        onScanEnter() {
            const q = this.scanQuery.trim().toLowerCase();
            if (q === '') return;
            // Match a variant's SKU/barcode FIRST — when a scanner hits
            // a per-variant label, we want to land directly on that
            // variant, not open the parent picker for the cashier to
            // re-pick the row they just scanned.
            const variantHit = this._findVariantExact(q);
            if (variantHit) {
                this._pushVariantLine(variantHit.product, variantHit.variant);
                this.scanQuery = '';
                return;
            }
            const exact = this.products.find((p) =>
                (p.barcode || '').toLowerCase() === q ||
                (p.sku || '').toLowerCase()     === q ||
                (p.extra_barcodes || []).some((b) => (b || '').toLowerCase() === q)
            );
            if (exact) {
                // addToCart already toasts if the product is out of stock
                // and bails — scan path piggybacks on the same guard.
                this.addToCart(exact);
                this.scanQuery = '';
                return;
            }
            // No exact product match — try a GS1-128 scale label (GTIN +
            // embedded weight), then a plain EAN/UPC scale barcode. Real
            // product barcodes win above, so these only fire for genuine
            // scale labels.
            if (this._tryGs128Barcode(this.scanQuery.trim())) {
                this.scanQuery = '';
                return;
            }
            if (this._tryScaleBarcode(this.scanQuery.trim())) {
                this.scanQuery = '';
                return;
            }
            // No exact hit — surface a toast so the cashier knows the
            // scan was received but the product isn't recognised. (The
            // global `posToast` helper is set up by admin.js.)
            this.$store.toasts?.push({ type: 'warning', message: (this.labels.no_product_for_query || 'No product for ":query"').replace(':query', this.scanQuery.trim()) });
            this.scanQuery = '';
        },

        /**
         * Enter on the search bar. If the query exactly matches a barcode
         * or SKU, add immediately (the scan-and-go path). Otherwise — if
         * exactly one tile is visible — add that one.
         */
        onSearchEnter() {
            const q = this.searchQuery.trim().toLowerCase();
            if (q === '') return;

            // Variant-first, same reasoning as the scan path above.
            const variantHit = this._findVariantExact(q);
            if (variantHit) {
                this._pushVariantLine(variantHit.product, variantHit.variant);
                this.searchQuery = '';
                return;
            }

            const exact = this.products.find((p) =>
                (p.barcode || '').toLowerCase() === q ||
                (p.sku || '').toLowerCase()     === q ||
                (p.extra_barcodes || []).some((b) => (b || '').toLowerCase() === q)
            );
            if (exact) {
                this.addToCart(exact);
                this.searchQuery = '';
                return;
            }

            // A GS1-128 or scale barcode typed/scanned into the search bar.
            if (this._tryGs128Barcode(this.searchQuery.trim())) {
                this.searchQuery = '';
                return;
            }
            if (this._tryScaleBarcode(this.searchQuery.trim())) {
                this.searchQuery = '';
                return;
            }

            const visible = this.visibleProducts;
            if (visible.length === 1) {
                this.addToCart(visible[0]);
                this.searchQuery = '';
            }
        },

        /* ── Cart ─────────────────────────────────────────────── */

        /**
         * Returns true when the product can't be added to the cart
         * because of stock state. Three cases:
         *   - `track_stock=false` → always sellable (services, kits
         *     with non-stocked components, etc.).
         *   - Variant parent (`has_variants=true`) → stock is tracked
         *     PER VARIANT, not on the parent; the parent's `on_hand`
         *     is null. Out of stock only when EVERY variant is out.
         *     Variants with `on_hand=null` (no stock row yet) count
         *     as untracked and keep the parent sellable so the user
         *     can pick one and let the server decide.
         *   - Simple product → null means "never tracked yet" (reject
         *     because the server would too); <= 0 is genuinely sold out.
         */
        isOutOfStock(product) {
            if (!product?.track_stock) return false;
            if (product.has_variants) {
                const vs = product.variants || [];
                if (vs.length === 0) return false;
                return vs.every((v) => {
                    if (v.on_hand === null || v.on_hand === undefined) return false;
                    return (parseFloat(v.on_hand) || 0) <= 0;
                });
            }
            if (product.on_hand === null || product.on_hand === undefined) return false;
            return (parseFloat(product.on_hand) || 0) <= 0;
        },

        /**
         * True when the product tracks stock but has no `on_hand` row
         * yet (never received). Distinct from `isOutOfStock` so the UI
         * can paint "Stock not listed yet" vs "Out of stock" — and the
         * cashier knows to go receive stock vs wait for a restock.
         *
         * For variant parents we wait until EVERY variant is in the
         * not-listed state before flagging the parent — if any variant
         * has a row, the parent can still resolve to that one.
         */
        isStockNotListed(product) {
            if (!product?.track_stock) return false;
            if (product.has_variants) {
                const vs = product.variants || [];
                if (vs.length === 0) return false;
                return vs.every((v) => v.on_hand === null || v.on_hand === undefined);
            }
            return product.on_hand === null || product.on_hand === undefined;
        },

        /**
         * Click handler for a product tile. Two flows:
         *   1. Simple product (no variants) → add the parent SKU.
         *   2. Variant product → open the variant picker so the cashier
         *      picks which size/colour/etc. before it lands in the cart.
         *
         * `addToCart` is also called by the scan/search path with an
         * already-resolved product (which may already be a variant if
         * the scan matched a variant barcode in a later slice).
         */
        /**
         * True when the store's overselling policy is on FOR THIS USER and
         * `product` may go negative. Batch/expiry-tracked items are excluded
         * (the batch picker needs a real batch), matching the server guard in
         * CompleteSale. Reads the combined `settings.can_oversell` flag that
         * SaleController computes (policy AND `sales.oversell` permission).
         */
        oversellAllowedFor(product) {
            return !!this.settings?.can_oversell && !product?.track_batches;
        },

        addToCart(product) {
            const stockBlocked = this.isStockNotListed(product) || this.isOutOfStock(product);
            if (stockBlocked && !this.oversellAllowedFor(product)) {
                this.$store.toasts?.push({
                    type:    'warning',
                    message: this.isStockNotListed(product)
                        ? (this.labels.stock_not_listed || '":name" — stock not listed yet. Receive stock first.').replace(':name', product.name)
                        : (this.labels.out_of_stock_cant_add || '":name" is out of stock — can\'t add to cart.').replace(':name', product.name),
                });
                return;
            }
            if (stockBlocked) {
                // Overselling permitted → let it through, but flag that stock
                // will go negative so it's never a silent mistake.
                this.$store.toasts?.push({
                    type:    'warning',
                    message: (this.labels.out_of_stock_oversell || '":name" is out of stock — selling below stock (inventory will go negative).').replace(':name', product.name),
                });
            }
            if (product.has_variants) {
                this.openVariantPicker(product);
                return;
            }
            // Batch-tracked + simple (no variants): open the batch
            // picker so the cashier picks which physical batch goes
            // out. Variant-tracked + batch-tracked falls through to
            // the variant picker first, then chains into batch.
            if (product.track_batches) {
                this.openBatchPicker(product, null);
                return;
            }
            const line = this._lineFromProduct(product);
            // Sold-by-weight items can't land at qty 1 — ask for the
            // measured weight first; the entered weight becomes the qty.
            if (product.sold_by_weight) {
                this.openWeightPrompt(line);
                return;
            }
            this._pushLine(line);
        },

        /**
         * Build the base cart-line view-model for a SIMPLE product (no
         * variant / batch). Shared by the tile-tap path and the scale-
         * barcode path so the two never drift.
         */
        _lineFromProduct(product) {
            return {
                product_id:    product.id,
                variant_id:    null,
                name:          product.name,
                sku:           product.sku,
                unit:          product.unit,
                // charge_price is sale_price when one's active, else
                // selling_price — server-computed so the cart never
                // disagrees with what the tile shows (see tilePriceLabel).
                unit_price:    product.charge_price ?? product.selling_price,
                tax_group_id:  product.tax_group_id,
                tax_rate_total: parseFloat(product.tax_rate_total ?? 0) || 0,
                tax_inclusive:  !!product.tax_inclusive,
                tax_taxable:    product.tax_taxable !== false,
                track_stock:   !!product.track_stock,
                on_hand:       product.on_hand === null || product.on_hand === undefined
                    ? null
                    : parseFloat(product.on_hand) || 0,
                image_url:     product.image_url,
                // Snapshot the kit components so the cart line keeps
                // showing them even if the product catalog refreshes.
                kit_items:     product.is_kit ? (product.kit_items ?? []) : null,
            };
        },

        /**
         * Try to handle a scan as a weighing-scale barcode. Decodes the
         * embedded PLU + value against the Settings → Scale template,
         * resolves the product by `scale_plu`, and rings it up at the
         * measured weight (no weight prompt — the scale already weighed it).
         *
         * Returns `true` only when a line was added, so the caller falls
         * through to its normal product lookup otherwise (a non-scale
         * barcode, or a prefix collision with a regular SKU).
         */
        /**
         * Try to handle a scan as a GS1-128 barcode (supermarket scale
         * labels: a GTIN plus an embedded net weight and/or total price).
         * Resolves the product by GTIN against `barcode` (leading-zero
         * tolerant) and rings it up at the measured weight. Returns false
         * when it isn't GS1-128 or no product matches, so the caller falls
         * through to the EAN scale-barcode / not-found paths.
         */
        _tryGs128Barcode(raw) {
            const decoded = decodeGs128(raw);
            if (!decoded || !decoded.gtin) return false;

            const product = this.products.find((p) => gtinMatches(decoded.gtin, p.barcode));
            if (!product) return false;

            // Variant/batch-tracked products can't have the weight pinned
            // here — defer to the normal picker.
            if (product.has_variants || product.track_batches) {
                this.addToCart(product);
                return true;
            }
            if (this.isOutOfStock(product) && !this.oversellAllowedFor(product)) {
                this.$store.toasts?.push({
                    type:    'warning',
                    message: (this.labels.out_of_stock_cant_add || '":name" is out of stock — can\'t add to cart.').replace(':name', product.name),
                });
                return true;
            }

            // Prefer the embedded weight. If only a total price is present,
            // back out the implied weight from the unit price. With neither,
            // it's a plain unit scan → qty 1.
            let qty = 1;
            let weighed = false;
            if (decoded.weight && decoded.weight > 0) {
                qty = decoded.weight;
                weighed = true;
            } else if (decoded.price && decoded.price > 0) {
                const unit = parseFloat(product.selling_price) || 0;
                if (unit > 0) { qty = decoded.price / unit; weighed = true; }
            }
            qty = Math.round((parseFloat(qty) || 0) * 10000) / 10000;
            if (!(qty > 0)) return false;

            if (weighed) {
                this._pushLine({ ...this._lineFromProduct(product), weighed: true, quantity: qty });
            } else {
                this.addToCart(product);
            }
            return true;
        },

        /* ── Camera scanner ───────────────────────────────────── */

        async openCamera() {
            this.cameraError = '';
            if (!this.cameraSupported) {
                this.$store.toasts?.push({ type: 'warning', message: this.labels.no_camera || 'No camera available on this device.' });
                return;
            }
            this.cameraOpen = true;
            await this.$nextTick();
            const videoEl = this.$refs.cameraVideo;
            if (!videoEl) return;
            try {
                // Lazy-load ZXing only now — keeps it out of the main bundle.
                const { CameraBarcodeScanner } = await import('../hardware/camera-scanner.js');
                this._cameraScanner = new CameraBarcodeScanner(videoEl);
                await this._cameraScanner.start((text) => this.onCameraScan(text));
            } catch (e) {
                this.cameraError = (e && /permission|denied|NotAllowed/i.test(e.name + e.message))
                    ? (this.labels.camera_permission_denied || 'Camera permission denied. Use a USB scanner or allow camera access.')
                    : (this.labels.camera_start_failed || 'Could not start the camera.');
            }
        },

        closeCamera() {
            try { this._cameraScanner?.stop(); } catch (_) { /* noop */ }
            this._cameraScanner = null;
            this.cameraOpen = false;
        },

        /** A barcode decoded from the camera — route it through the same
         *  resolution as a typed/USB scan, then close the modal. */
        onCameraScan(text) {
            const code = String(text || '').trim();
            if (!code) return;
            this.closeCamera();
            this.scanQuery = code;
            this.onScanEnter();
        },

        _tryScaleBarcode(raw) {
            const decoded = decodeScaleBarcode(raw, settings.scale);
            if (!decoded) return false;

            const product = this.products.find((p) =>
                p.scale_plu !== null && p.scale_plu !== undefined
                && String(p.scale_plu) === String(decoded.plu));
            if (!product) return false;

            // Scale items are simple weighed SKUs. If the matched product is
            // variant/batch-tracked, fall back to the normal picker flow
            // (we can't safely pin the weight to a sub-line here).
            if (product.has_variants || product.track_batches) {
                this.addToCart(product);
                return true;
            }
            if (this.isOutOfStock(product) && !this.oversellAllowedFor(product)) {
                this.$store.toasts?.push({
                    type:    'warning',
                    message: (this.labels.out_of_stock_cant_add || '":name" is out of stock — can\'t add to cart.').replace(':name', product.name),
                });
                return true;
            }

            // weight embed → qty is the measured weight.
            // price embed → the barcode carries the TOTAL price; back out the
            //   implied weight so the line rings up to that price.
            let qty = decoded.value;
            if (decoded.embed === 'price') {
                const unit = parseFloat(product.selling_price) || 0;
                qty = unit > 0 ? decoded.value / unit : 0;
            }
            qty = Math.round((parseFloat(qty) || 0) * 10000) / 10000;
            if (!(qty > 0)) {
                this.$store.toasts?.push({
                    type:    'warning',
                    message: (this.labels.weight_read_failed || 'Couldn\'t read a weight from ":code".').replace(':code', String(raw).trim()),
                });
                return true;
            }

            this._pushLine({ ...this._lineFromProduct(product), weighed: true, quantity: qty });
            return true;
        },

        /** Push a line — collapses to an existing matching line by +1.
         *  Matches on (product, variant, batch) so different batches of
         *  the same product stay as separate cart lines (different
         *  expiry / prices). */
        _pushLine(line, silent = false) {
            // Single funnel for every add path — play the "item added" sound
            // here so tile taps, scans, quick-picks, variant/batch picks all
            // beep (unless muted in Settings → Cashier, or `silent` for bulk
            // adds like resuming a held sale).
            if (!silent && this.settings.sound_on_add !== false) playAddBeep();

            // Weighed lines never collapse: each weigh-in is a distinct
            // measurement (different weights), and the line carries its
            // own `quantity` (the measured weight) rather than +1.
            if (!line.weighed) {
                const existing = this.cart.find((l) =>
                    l.product_id === line.product_id
                    && (l.variant_id ?? null) === (line.variant_id ?? null)
                    && (l.batch_id   ?? null) === (line.batch_id   ?? null)
                );
                if (existing) {
                    existing.quantity = (parseFloat(existing.quantity) || 0) + 1;
                    return existing;
                }
            }
            // `quantity: 1` is the default; a `quantity` on `line` (weighed
            // lines) overrides it via the spread.
            const row = { id: this._nextLineId++, quantity: 1, ...line };
            this.cart.push(row);
            return row;
        },

        /* ── Variant picker ───────────────────────────────────── */

        openVariantPicker(product) {
            this.variantPickerProduct = product;
            this.variantPickerOpen    = true;
        },

        closeVariantPicker() {
            this.variantPickerOpen    = false;
            this.variantPickerProduct = null;
        },

        pickVariant(variant) {
            const product = this.variantPickerProduct;
            if (!product) return;
            // Per-variant stock guard mirroring `isOutOfStock`: only
            // applies when the parent product tracks stock; variants
            // with no stock row are treated as untracked-yet (block).
            // Skipped when overselling is permitted for this product.
            if (product.track_stock && !this.oversellAllowedFor(product)) {
                if (variant.on_hand === null || variant.on_hand === undefined
                    || (parseFloat(variant.on_hand) || 0) <= 0) {
                    this.$store.toasts?.push({
                        type:    'warning',
                        message: (this.labels.variant_out_of_stock || '":name" is out of stock.').replace(':name', `${product.name} · ${variant.label}`),
                    });
                    return;
                }
            }
            // Variant + batch-tracked → chain into the batch picker
            // with the variant already pinned.
            if (product.track_batches) {
                this.closeVariantPicker();
                this.openBatchPicker(product, variant);
                return;
            }
            this._pushVariantLine(product, variant);
            this.closeVariantPicker();
        },

        /**
         * Find a (product, variant) pair whose variant SKU OR barcode
         * matches `q` exactly (case-insensitive). Used by the scan/Enter
         * paths so typing a per-variant SKU lands directly on that
         * variant instead of opening the parent's picker for the same
         * row the cashier just typed.
         */
        _findVariantExact(q) {
            for (const p of this.products) {
                if (!Array.isArray(p.variants)) continue;
                for (const v of p.variants) {
                    if ((v.sku     || '').toLowerCase() === q) return { product: p, variant: v };
                    if ((v.barcode || '').toLowerCase() === q) return { product: p, variant: v };
                }
            }
            return null;
        },

        /**
         * Push a cart line for an explicit (product, variant) pair —
         * shared by `pickVariant` and the variant scan/Enter paths so
         * the line shape stays identical across both. Batch-tracked
         * variants chain into the batch picker, matching `pickVariant`.
         */
        _pushVariantLine(product, variant) {
            if (product.track_stock && !this.oversellAllowedFor(product)) {
                if (variant.on_hand === null || variant.on_hand === undefined) {
                    // Distinguish "never tracked yet" (no stock_level row)
                    // from "tracked + sold out" so the cashier knows
                    // whether to receive stock vs wait for a restock.
                    this.$store.toasts?.push({
                        type:    'warning',
                        message: (this.labels.stock_not_listed || '":name" — stock not listed yet. Receive stock first.').replace(':name', `${product.name} · ${variant.label}`),
                    });
                    return;
                }
                if ((parseFloat(variant.on_hand) || 0) <= 0) {
                    this.$store.toasts?.push({
                        type:    'warning',
                        message: (this.labels.variant_out_of_stock || '":name" is out of stock.').replace(':name', `${product.name} · ${variant.label}`),
                    });
                    return;
                }
            }
            if (product.track_batches) {
                this.openBatchPicker(product, variant);
                return;
            }
            this._pushLine({
                product_id:    product.id,
                variant_id:    variant.id,
                name:          product.name,
                sku:           variant.sku,
                unit:          product.unit,
                unit_price:    variant.charge_price ?? variant.selling_price,
                tax_group_id:  product.tax_group_id,
                tax_rate_total: parseFloat(product.tax_rate_total ?? 0) || 0,
                tax_inclusive:  !!product.tax_inclusive,
                tax_taxable:    product.tax_taxable !== false,
                track_stock:   !!product.track_stock,
                on_hand:       variant.on_hand === null || variant.on_hand === undefined
                    ? null
                    : parseFloat(variant.on_hand) || 0,
                image_url:     variant.image_url || product.image_url,
                variant_label: variant.label,
            });
        },

        /* ── Batch picker ─────────────────────────────────────── */

        /**
         * Open the batch picker for a (product, variant?). Fires a GET
         * to the cashier.batches endpoint to pull live FEFO-sorted
         * batches for this store. Cashier picks one → cart line carries
         * `batch_id` and the picker's selling_price as the line price.
         */
        async openBatchPicker(product, variant) {
            this.batchPickerProduct = product;
            this.batchPickerVariant = variant;
            this.batchPickerOpen    = true;
            this.batchPickerResults = [];
            this.batchPickerLoading = true;

            try {
                // Offline-first: read from IDB. The catalog sync stamps
                // every live (qty>0) batch for the active store, so the
                // picker works without network. Server fallback only
                // kicks in if IDB is empty (cold first load) AND we're
                // online — IDB is otherwise the truth.
                let cached = [];
                try {
                    cached = await readCachedBatches(product.id, variant ? variant.id : null);
                } catch (_) { /* IDB cold or unavailable */ }

                if (cached.length > 0) {
                    this.batchPickerResults = cached;
                    return;
                }

                if (this.connectivity.status === 'offline' || !batchesUrl) {
                    this.batchPickerResults = [];
                    return;
                }

                try {
                    const params = { product_id: product.id };
                    if (variant) params.variant_id = variant.id;
                    const { data } = await posGet(batchesUrl, params);
                    this.batchPickerResults = Array.isArray(data) ? data : [];
                } catch (e) {
                    this.batchPickerResults = [];
                }
            } finally {
                this.batchPickerLoading = false;
            }
        },

        closeBatchPicker() {
            this.batchPickerOpen    = false;
            this.batchPickerProduct = null;
            this.batchPickerVariant = null;
        },

        /**
         * Is this batch locked out of sale? Only true when the company
         * has `block_expired_batch_sale=true`, the batch is expired,
         * and the cashier lacks the `inventory.sell_expired` override
         * permission. Used to dim the tile + block `pickBatch`.
         */
        isBatchBlocked(batch) {
            if (!settings.block_expired_batch_sale) return false;
            if (settings.can_sell_expired) return false;
            const d = this.batchDaysToExpiry(batch);
            return d !== null && d < 0;
        },

        /**
         * Commit a picked batch to the cart. Uses the BATCH's
         * selling_price when set (pharmacy MRP varies per batch),
         * otherwise falls back to the variant's, then the product's.
         */
        pickBatch(batch) {
            const product = this.batchPickerProduct;
            const variant = this.batchPickerVariant;
            if (!product) return;

            // Server-side guard exists too, but bouncing here saves the
            // round-trip and surfaces the reason inline.
            if (this.isBatchBlocked(batch)) {
                this.$store.toasts?.push({
                    type:    'warning',
                    message: (this.labels.batch_expired || '":name" expired on :date — cannot sell.')
                        .replace(':name', `${product.name} · ${batch.batch_number}`)
                        .replace(':date', batch.expiry_date),
                });
                return;
            }

            const price = (batch.selling_price && parseFloat(batch.selling_price) > 0)
                ? batch.selling_price
                : (variant?.charge_price ?? variant?.selling_price ?? product.charge_price ?? product.selling_price);

            this._pushLine({
                product_id:    product.id,
                variant_id:    variant ? variant.id : null,
                batch_id:      batch.id,
                batch_number:  batch.batch_number,
                batch_expiry:  batch.expiry_date,
                name:          product.name,
                sku:           variant ? variant.sku : product.sku,
                unit:          product.unit,
                unit_price:    price,
                mrp:           batch.mrp,
                tax_group_id:  product.tax_group_id,
                tax_rate_total: parseFloat(product.tax_rate_total ?? 0) || 0,
                tax_inclusive:  !!product.tax_inclusive,
                tax_taxable:    product.tax_taxable !== false,
                track_stock:   !!product.track_stock,
                // Stock cap is now the BATCH on-hand, not the product
                // total. Picking 5 of a batch of 3 should warn.
                on_hand:       parseFloat(batch.on_hand) || 0,
                image_url:     variant?.image_url || product.image_url,
                variant_label: variant?.label ?? null,
            });
            this.closeBatchPicker();
        },

        /**
         * Days until expiry — positive = future, zero = today, negative
         * = expired. Returns null when the batch has no expiry date.
         */
        batchDaysToExpiry(batch) {
            if (!batch?.expiry_date) return null;
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const exp = new Date(batch.expiry_date);
            exp.setHours(0, 0, 0, 0);
            return Math.round((exp - today) / 86400000);
        },

        /* ── Weight prompt (sold-by-weight items) ──────────────── */

        /**
         * Open the weight prompt for an already-resolved line. The line
         * is parked until the cashier confirms; `weightInput` starts
         * empty and the modal autofocuses its field.
         */
        openWeightPrompt(line) {
            this.weightPromptLine  = line;
            this.weightPromptName  = line.name;
            this.weightPromptUnit  = line.unit || '';
            this.weightPromptPrice = String(line.unit_price ?? '0');
            this.weightInput       = '';
            this.weightPromptOpen  = true;
            this.$nextTick(() => {
                this.$refs.weightField?.focus();
            });
        },

        cancelWeightPrompt() {
            this.weightPromptOpen = false;
            this.weightPromptLine = null;
            this.weightInput      = '';
        },

        /** Live line-total preview = entered weight × unit price. */
        get weightPromptTotal() {
            const w = parseFloat(this.weightInput);
            const p = parseFloat(this.weightPromptPrice);
            if (!isFinite(w) || w <= 0 || !isFinite(p)) return 0;
            return w * p;
        },

        /**
         * Commit the weighed line. The entered weight becomes the line
         * quantity; `weighed:true` keeps it from collapsing into another
         * line of the same product.
         */
        confirmWeight() {
            const weight = parseFloat(this.weightInput);
            if (!isFinite(weight) || weight <= 0) {
                this.$store.toasts?.push({
                    type:    'warning',
                    message: this.labels.weight_required || 'Enter a weight greater than zero.',
                });
                return;
            }
            const line = this.weightPromptLine;
            if (!line) { this.cancelWeightPrompt(); return; }
            this._pushLine({ ...line, weighed: true, quantity: weight });
            this.cancelWeightPrompt();
        },

        increment(line) {
            line.quantity = (parseFloat(line.quantity) || 0) + 1;
        },

        decrement(line) {
            const q = (parseFloat(line.quantity) || 0) - 1;
            if (q <= 0) this.removeLine(line);
            else line.quantity = q;
        },

        /**
         * Does this line's quantity exceed the on-hand stock snapshot
         * captured when it was added? Lines flagged `track_stock=false`
         * (services, kits with untracked components) are never over.
         * Lines whose on_hand was unknown (null) at add-time are
         * treated as untracked-yet — also never over.
         */
        isLineOverStock(line) {
            if (!line?.track_stock) return false;
            if (line.on_hand === null || line.on_hand === undefined) return false;
            const onHand = parseFloat(line.on_hand) || 0;
            const qty    = parseFloat(line.quantity) || 0;
            return qty > onHand;
        },

        lineOverBy(line) {
            const onHand = parseFloat(line.on_hand) || 0;
            const qty    = parseFloat(line.quantity) || 0;
            return Math.max(0, qty - onHand);
        },

        get hasAnyOverStockLine() {
            return this.cart.some((l) => this.isLineOverStock(l));
        },

        /**
         * An over-stock line that still BLOCKS checkout — i.e. one we may not
         * oversell (policy off for this user, or a batch-tracked line, which
         * the server refuses to take negative). Over-stock lines that ARE
         * oversellable stay in the cart and keep their "over by N" badge as a
         * visible warning, but no longer block payment.
         */
        lineOverStockBlocks(line) {
            if (!this.isLineOverStock(line)) return false;
            return !(this.settings?.can_oversell && !line.batch_id);
        },

        get hasBlockingOverStock() {
            return this.cart.some((l) => this.lineOverStockBlocks(l));
        },

        /**
         * Direct quantity edit — guards against 0/negative input (which
         * would remove the line silently — that's surprising while
         * typing). Empty or junk reverts to 1; valid positive values
         * commit normally.
         */
        setQty(line, value) {
            const n = parseFloat(value);
            if (!Number.isFinite(n) || n <= 0) {
                line.quantity = 1;
                return;
            }
            line.quantity = n;
        },

        removeLine(line) {
            this.cart = this.cart.filter((l) => l.id !== line.id);
        },

        clearCart() {
            this.cart = [];
            this.customer = null;
            this.discount = { type: null, value: 0 };
            this.discountApproval = null;
            this._pendingApproval = null;
            this._resetClientUuid();
        },

        /**
         * Clear button click — confirms before nuking a non-empty cart.
         * If the cart is already empty (no items, no customer, no
         * discount) we just no-op so the cashier doesn't get a
         * confirmation dialog for nothing.
         */
        onClearClick() {
            const hasState = this.cart.length > 0 || this.customer || this.discount.type;
            if (!hasState) return;
            this.$store.confirm?.show({
                title:        this.labels.clear_order_title || 'Clear the current order?',
                message:      this.labels.clear_order_message || 'Every item, the assigned customer, and any discount will be removed. This cannot be undone.',
                intent:       'danger',
                confirmLabel: this.labels.clear_order_confirm || 'Clear order',
                cancelLabel:  this.labels.confirm_keep || 'Keep',
                onConfirm:    () => { this.clearCart(); },
            });
        },

        /* ── Customer picker / quick-add ──────────────────────── */

        openCustomerPicker() {
            this.customerPickerOpen    = true;
            this.customerPickerQuery   = '';
            this.customerPickerResults = [];
            this._searchCustomersDebounced();
            this.$nextTick(() => document.querySelector('[data-customer-picker-input]')?.focus());
        },

        closeCustomerPicker() {
            this.customerPickerOpen = false;
        },

        _searchCustomersDebounced() {
            clearTimeout(this._customerSearchTimer);
            this._customerSearchTimer = setTimeout(() => this.searchCustomers(), 200);
        },

        async searchCustomers() {
            this.customerPickerLoading = true;
            try {
                // Offline-first: always try IDB first. When online, also
                // hit the server and merge so customers outside the
                // recent-500 cache still surface. When offline, IDB is
                // the only source — empty query → 25 most recent.
                let cached = [];
                try {
                    cached = await searchCachedCustomers(this.customerPickerQuery, 25);
                } catch (_) { /* IDB cold or unavailable */ }

                if (this.connectivity.status === 'offline' || !customerSearchUrl) {
                    this.customerPickerResults = cached;
                    return;
                }

                try {
                    const { data } = await posGet(customerSearchUrl, { q: this.customerPickerQuery });
                    const server = Array.isArray(data) ? data : [];
                    // Merge by id — server results take precedence (fresher).
                    const seen = new Set(server.map(c => c.id));
                    const merged = server.concat(cached.filter(c => !seen.has(c.id))).slice(0, 25);
                    this.customerPickerResults = merged;
                } catch (e) {
                    // Server hiccupped — fall back to cached results so
                    // the picker doesn't go empty.
                    this.customerPickerResults = cached;
                }
            } finally {
                this.customerPickerLoading = false;
            }
        },

        selectCustomer(c) {
            this.customer = c;
            this.customerPickerOpen = false;
            this._applyCustomerDefaultDiscount();
        },

        clearCustomer() {
            this.customer = null;
            // Drop the auto discount when the customer is removed; leave a
            // hand-entered one untouched.
            if (this.discount.source === 'customer_default') this._clearAutoDiscount();
        },

        /**
         * Auto-apply the selected customer's `default_discount_percent` as the
         * order discount (sales-checkout doc §"Assigning a customer"). This is
         * an admin-configured, pre-authorized discount — the server exempts it
         * from the manager-approval threshold — so we set it directly rather
         * than routing through the manual-discount approval gate.
         *
         * A discount the cashier applied BY HAND always wins: we only touch
         * the order discount when it's empty or was itself auto-applied.
         */
        _applyCustomerDefaultDiscount() {
            const ours = this.discount.source === 'customer_default';
            if (this.discount.type && ! ours) return;

            const pct = parseFloat(this.customer?.default_discount_percent) || 0;
            if (pct > 0) {
                this.discount = {
                    type:            'pct',
                    value:           pct,
                    reason:          'Customer default discount',
                    reason_category: 'customer_default',
                    source:          'customer_default',
                };
                // Never carries a manager token — it's pre-authorized.
                this.discountApproval = null;
                this._pendingApproval = null;
            } else if (ours) {
                // Switched to a customer with no default → clear the old auto one.
                this._clearAutoDiscount();
            }
        },

        _clearAutoDiscount() {
            this.discount = { type: null, value: 0 };
            this.discountApproval = null;
            this._pendingApproval = null;
        },

        openCustomerAdd() {
            this.customerAddForm    = { name: this.customerPickerQuery.trim(), phone: '', email: '' };
            this.customerPickerOpen = false;
            this.customerAddOpen    = true;
            this.$nextTick(() => {
                document.querySelector('[data-customer-add-name]')?.focus();
                // The phone field isn't x-modeled (see submitCustomerAdd), so
                // clear it by hand on reopen — otherwise the previous number
                // (and its intl-tel-input state) lingers.
                const phoneEl = document.querySelector('[data-cashier-customer-add] input.js-phone, .cashier-customer-add-card input.js-phone');
                if (phoneEl) {
                    try { phoneEl._iti ? phoneEl._iti.setNumber('') : (phoneEl.value = ''); }
                    catch (_) { phoneEl.value = ''; }
                }
            });
        },

        closeCustomerAdd() {
            if (this.customerAddSubmitting) return;
            this.customerAddOpen = false;
        },

        async submitCustomerAdd() {
            if (!customerStoreUrl) return;
            const name  = (this.customerAddForm.name  || '').trim();
            const email = (this.customerAddForm.email || '').trim();

            // Phone is read off the intl-tel-input instance (the canonical
            // E.164), NOT an x-model — the lib and x-model fight and render
            // the field as "[object Object]". Sync it back onto the form
            // model so both the online and offline payloads carry it.
            const phoneEl = document.querySelector('[data-cashier-customer-add] input.js-phone, .cashier-customer-add-card input.js-phone');
            const iti = phoneEl?._iti;
            let phone = '';
            if (iti) {
                // Prefer libphonenumber's E.164 (carries the country code).
                const e164 = (iti.getNumber() || '').trim();
                if (e164.startsWith('+')) {
                    phone = e164;
                } else {
                    // Fallback: prepend the selected country's dial code to the
                    // typed digits so the stored number is never country-code-less.
                    const dial   = iti.getSelectedCountryData?.()?.dialCode;
                    const digits = (phoneEl?.value || '').replace(/\D/g, '');
                    phone = (dial && digits) ? `+${dial}${digits}` : (phoneEl?.value || '').trim();
                }
            } else {
                phone = (phoneEl?.value || '').trim();
            }
            this.customerAddForm.phone = phone;

            // The modal isn't a real <form>, so the system-wide form-
            // validators don't fire here. Mirror their rules inline:
            // single multi-line toast, no inline field errors.
            const errs = [];
            if (name === '') errs.push(this.labels.customer_name_required || 'Customer name is required.');
            if (email !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                errs.push(this.labels.customer_email_invalid || 'Email address looks invalid.');
            }
            if (phone !== '') {
                // When present, intl-tel-input is the authority (libphonenumber
                // per-country rules). Falls back to a 7-15 digit check when the
                // upgrade hasn't fired (e.g. JS bundle still old).
                const looksValid = iti
                    ? iti.isValidNumber()
                    : /^\+?\d{7,15}$/.test(phone.replace(/[\s\-().]/g, ''));
                if (!looksValid) errs.push(this.labels.customer_phone_invalid || 'Phone number looks invalid.');
            }
            if (errs.length) {
                this.$store.toasts?.push({
                    type: 'error',
                    message: errs.join('\n'),
                    duration: 6000,
                });
                return;
            }
            // Slice 4: offline customer create. Allocate a negative
            // local id, queue a `customer_create` entry, attach the
            // synthetic customer to the cart so the sale references
            // it. The sync engine drains customer_create entries
            // before sale_complete and rewrites `customer_id` from
            // the local→server remap.
            if (this.connectivity.status === 'offline') {
                this.customerAddSubmitting = true;
                try {
                    const localId = await nextLocalCustomerId();
                    const synthetic = {
                        id:    localId,
                        name:  name,
                        phone: this.customerAddForm.phone || null,
                        email: this.customerAddForm.email || null,
                        outstanding_balance: '0',
                        _offline_pending: true,
                    };
                    await enqueueCustomerCreate({
                        local_id: localId,
                        name:     synthetic.name,
                        phone:    synthetic.phone,
                        email:    synthetic.email,
                    });
                    this.customer = synthetic;
                    this.customerAddOpen = false;
                    this.$store.toasts?.push({
                        type:    'success',
                        message: this.labels.customer_queued_offline || 'Customer queued — will sync when online.',
                        duration: 4000,
                    });
                } catch (e) {
                    this.$store.toasts?.push({
                        type:    'error',
                        message: (this.labels.customer_queue_failed || 'Couldn\'t queue customer: ') + (e?.message || (this.labels.unknown_error || 'unknown error')),
                    });
                } finally {
                    this.customerAddSubmitting = false;
                }
                return;
            }
            this.customerAddSubmitting = true;
            try {
                const { data } = await posPost(customerStoreUrl, this.customerAddForm);
                if (data && data.id) {
                    this.customer        = data;
                    this.customerAddOpen = false;
                }
            } catch (e) {
                // lib/http.js auto-toasts 5xx but NOT 422 — surface the
                // validation errors (e.g. duplicate phone/email) here so the
                // cashier sees why the save was rejected. Modal stays open.
                if (e?.status === 422 && e.errors && Object.keys(e.errors).length) {
                    this.$store.toasts?.push({
                        type:     'error',
                        message:  Object.values(e.errors).flat().join('\n'),
                        duration: 6000,
                    });
                }
            } finally {
                this.customerAddSubmitting = false;
            }
        },

        /* ── Live layout switcher ─────────────────────────────── */

        setLayout(name) {
            if (!['lane', 'beam', 'counter', 'focus'].includes(name)) return;
            this.currentLayout = name;
        },

        /* ── Overflow menu ────────────────────────────────────── */

        toggleOverflow() {
            this.overflowOpen = !this.overflowOpen;
        },

        /* ── Recent sales drawer ─────────────────────────────── */

        async openRecentsDrawer() {
            this.recentsDrawerOpen = true;
            this.recentsLoading    = true;
            this.recentsList       = [];
            if (!recentsListUrl) { this.recentsLoading = false; return; }
            try {
                const { data } = await posGet(recentsListUrl);
                this.recentsList = Array.isArray(data) ? data : [];
            } catch (_) {
                this.recentsList = [];
            } finally {
                this.recentsLoading = false;
            }
        },

        closeRecentsDrawer() {
            this.recentsDrawerOpen = false;
        },

        /** Open a completed sale's receipt in a new tab. */
        viewRecentReceipt(sale) {
            if (!receiptUrlTemplate || !sale?.id) return;
            const url = receiptUrlTemplate.replace('__ID__', sale.id);
            window.open(url, '_blank', 'noopener');
        },

        /** Short date formatter for the drawer list rows. */
        _fmtRecentDate(iso) {
            if (!iso) return '';
            try {
                const d = new Date(iso);
                return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
                    + ' · ' + d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
            } catch (_) { return ''; }
        },

        /* ── Keyboard shortcuts help ──────────────────────────── */

        openShortcutsHelp() {
            this.shortcutsHelpOpen = true;
        },

        closeShortcutsHelp() {
            this.shortcutsHelpOpen = false;
        },

        closeOverflow() {
            this.overflowOpen = false;
        },

        /* ── Open drawer (no sale) ─────────────────────────────── */

        openDrawerNoSale() {
            if (!drawerNoSaleUrl) return;
            this.drawerNoSaleReason = '';
            this.drawerNoSaleOpen   = true;
            this.$nextTick(() => document.querySelector('[data-drawer-no-sale-reason]')?.focus());
        },

        closeDrawerNoSale() {
            this.drawerNoSaleOpen = false;
        },

        /**
         * Same two-step shape as the admin shift page's drawer-open-no-sale
         * form (resources/js/admin/drawer-opener.js): kick the physical
         * drawer on WebUSB terminals, then record the audit entry. Unlike
         * that page, this one does NOT reload afterwards — the cashier is
         * mid-shift on the live checkout screen, not a standalone admin page.
         */
        async submitDrawerNoSale() {
            const reason = this.drawerNoSaleReason.trim();
            if (!reason || this.drawerNoSaleBusy || !drawerNoSaleUrl) return;
            this.drawerNoSaleBusy = true;

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
                await posPost(drawerNoSaleUrl, { type: 'drawer_open_no_sale', reason });
                this.drawerNoSaleOpen = false;
                this.$store.toasts.push({ type: 'success', message: this.labels.drawer_no_sale_recorded || 'Drawer open recorded.' });
                if (cfg.mode === 'webusb' && !kicked) {
                    this.$store.toasts.push({ type: 'info', message: this.labels.drawer_no_sale_open_manually || "Couldn't reach the printer — open the drawer manually." });
                }
            } catch (e) {
                this.$store.toasts.push({
                    type:    'error',
                    message: e?.message ?? (this.labels.drawer_no_sale_failed || 'Could not record the drawer open.'),
                });
            } finally {
                this.drawerNoSaleBusy = false;
            }
        },

        /* ── Hold / Resume ────────────────────────────────────── */

        onHoldClick() {
            if (this.cart.length === 0 || this.holdSubmitting) return;
            this.holdLabel          = this.customer?.name ?? '';
            this.holdNamePromptOpen = true;
            this.$nextTick(() => document.querySelector('[data-hold-label]')?.focus());
        },

        /**
         * Adjust the local `on_hand` snapshot on `this.products` (and
         * variant rows) by `sign × quantity` for each affected line.
         * Sign is -1 when stock is moving INTO a reservation (hold) and
         * +1 when a reservation is released (resume/void). Without this
         * the tile keeps painting the pre-hold availability and the
         * next ring-up over-commits a SKU that's already promised.
         * GREATEST(…,0) on the server side caps display at zero; we
         * mirror that here.
         */
        _applyReservation(items, sign) {
            if (!Array.isArray(items) || items.length === 0) return;
            for (const it of items) {
                const product = this.products.find((p) => p.id === Number(it.product_id) || p.id === it.product_id);
                if (!product) continue;
                const delta = (parseFloat(it.quantity) || 0) * sign;
                if (delta === 0) continue;
                const variantId = it.variant_id ? Number(it.variant_id) : null;
                if (variantId && Array.isArray(product.variants)) {
                    const v = product.variants.find((x) => x.id === variantId);
                    if (v && v.on_hand !== null && v.on_hand !== undefined) {
                        const next = (parseFloat(v.on_hand) || 0) + delta;
                        v.on_hand = String(Math.max(0, next));
                    }
                    continue;
                }
                if (product.on_hand === null || product.on_hand === undefined) continue;
                const next = (parseFloat(product.on_hand) || 0) + delta;
                product.on_hand = String(Math.max(0, next));
            }
        },

        /* ── Per-line note modal ──────────────────────────────── */

        openNote(line) {
            this.noteLineId = line.id;
            this.noteText   = line.note ?? '';
            this.$nextTick(() => document.querySelector('[data-cashier-note]')?.focus());
        },

        closeNote() {
            this.noteLineId = null;
            this.noteText   = '';
        },

        saveNote() {
            const id = this.noteLineId;
            if (id == null) return;
            const line = this.cart.find((l) => l.id === id);
            if (line) line.note = (this.noteText || '').trim() || null;
            this.closeNote();
        },

        get noteLine() {
            return this.cart.find((l) => l.id === this.noteLineId) ?? null;
        },

        /* ── Discount modal ───────────────────────────────────── */

        onDiscountClick() {
            if (this.cart.length === 0) return;
            this.openDiscount();
        },

        openDiscount() {
            // Seed the form from the currently-applied discount so the
            // cashier can tweak it instead of starting from zero.
            this._discountForm = {
                kind: this.discount.type || 'pct',
                val:  this.discount.type ? Number(this.discount.value) : 10,
                reason_category: this.discount.reason_category || '',
                reason:          this.discount.reason || '',
            };
            this.showDiscount = true;
        },

        closeDiscount() {
            this.showDiscount = false;
        },

        applyDiscount() {
            const v = Math.max(0, parseFloat(this._discountForm.val) || 0);
            const kind = this._discountForm.kind === 'amt' ? 'amt' : 'pct';
            // The order-level discount sits ON TOP of any line discounts, so
            // an amount discount is capped at the already-discounted subtotal.
            if (kind === 'pct' && v > 100) {
                this.$store.toasts?.push({ type: 'warning', message: this.labels.discount_percent_max || 'Percent discount can\'t be greater than 100%.' });
                return;
            }
            if (kind === 'amt' && v > this.discountedSubtotal) {
                this.$store.toasts?.push({ type: 'warning', message: this.labels.discount_amount_max_order || 'Discount amount can\'t be greater than the order total.' });
                return;
            }

            // Reason + category (Slice 3) — captured on the discount for audit.
            const reason_category = this._discountForm.reason_category || null;
            const reason = (this._discountForm.reason || '').trim() || null;
            const prev = this.discount;

            // Apply first, then gate on the resulting TOTAL discount so the
            // check covers line + order discounts together.
            this.discount = { type: v > 0 ? kind : null, value: v, reason, reason_category };
            this._gateDiscount(() => { this.discount = prev; });
            this.showDiscount = false;
        },

        removeDiscount() {
            this.discount = { type: null, value: 0 };
            this.discountApproval = null;
            this._pendingApproval = null;
            this.showDiscount = false;
        },

        /* ── Per-line discount (Slice 4) ───────────────────────────── */

        openLineDiscount(line) {
            this._lineDiscLineId = line.id;
            const d = line.discount;
            this._lineDiscForm = { kind: d?.type || 'pct', val: d?.type ? Number(d.value) : 10 };
            this.lineDiscOpen = true;
        },

        closeLineDiscount() { this.lineDiscOpen = false; },

        get _lineDiscTarget() { return this.cart.find((l) => l.id === this._lineDiscLineId) || null; },

        applyLineDiscount() {
            const line = this._lineDiscTarget;
            if (!line) { this.lineDiscOpen = false; return; }
            const v = Math.max(0, parseFloat(this._lineDiscForm.val) || 0);
            const kind = this._lineDiscForm.kind === 'amt' ? 'amt' : 'pct';
            const gross = (parseFloat(line.unit_price) || 0) * (parseFloat(line.quantity) || 0);

            if (kind === 'pct' && v > 100) {
                this.$store.toasts?.push({ type: 'warning', message: this.labels.discount_percent_max || 'Percent discount can\'t be greater than 100%.' });
                return;
            }
            if (kind === 'amt' && v > gross) {
                this.$store.toasts?.push({ type: 'warning', message: this.labels.discount_amount_max_line || 'Discount can\'t be greater than the line total.' });
                return;
            }

            const prev = line.discount || null;
            line.discount = v > 0 ? { type: kind, value: v } : null;
            this._gateDiscount(() => { line.discount = prev; });
            this.lineDiscOpen = false;
        },

        removeLineDiscount() {
            const line = this._lineDiscTarget;
            if (line) line.discount = null;
            this.lineDiscOpen = false;
        },

        /**
         * Governance gate run AFTER a discount is applied (order or line).
         * Every non-zero discount now needs a PIN — identifying WHO
         * applied it, not just whether the logged-in session is allowed
         * to. Under the store threshold any active user's PIN is
         * accepted server-side; above it, only a manager's. `revert`
         * undoes the applied discount if the cashier cancels the prompt.
         */
        _gateDiscount(revert) {
            if (this.discountAmt <= 0) return;

            const gross = this.subtotal;
            const effectivePct = gross > 0 ? (this.discountAmt / gross) * 100 : 0;
            const covered = this.discountApproval
                && effectivePct <= Number(this.discountApproval.percent) + 1e-6;
            if (!covered) {
                this._pendingApproval = { revert, effectivePct };
                this.approvalForm = { pin: '' };
                this.approvalOpen = true;
            }
        },

        /** Does the pending discount exceed the store's threshold, i.e.
         *  does the PIN modal need a manager rather than any staff PIN? */
        get approvalNeedsManager() {
            const pct = Number(this._pendingApproval?.effectivePct) || 0;
            const threshold = Number(this.settings?.discount_threshold_percent ?? 100);
            return pct > threshold;
        },

        closeApproval() {
            // Manager cancelled → undo the discount that needed approval.
            if (this._pendingApproval?.revert) this._pendingApproval.revert();
            this._pendingApproval = null;
            this.approvalOpen = false;
        },

        /**
         * A manager re-authenticates to approve the pending over-threshold
         * discount. On success we bank a signed token (the server re-checks
         * it at ring-up) and apply the held discount.
         */
        async submitApproval() {
            if (this.approvalSubmitting || !this._pendingApproval) return;
            const url = this.settings?.discount_approval_url;
            if (!url) return;
            if ((this.approvalForm.pin || '').length !== 6) return;

            this.approvalSubmitting = true;
            const pa = this._pendingApproval;
            try {
                const { data } = await posPost(url, {
                    pin:     this.approvalForm.pin,
                    percent: pa.effectivePct,
                });
                // The discount is already applied; banking the token authorises
                // it (rides along in complete(); the server re-verifies).
                this.discountApproval = { token: data.token, approver: data.approver, percent: pa.effectivePct };
                this._pendingApproval = null;
                this.approvalOpen = false;
                this.approvalForm = { pin: '' };
                this.$store.toasts?.push({ type: 'success', message: data.message || this.labels.discount_approved || 'Discount approved.' });
            } catch (e) {
                // http.js handles 5xx/419; surface the 422 (wrong PIN / not
                // authorised) and 429 (throttled) here. Clear the pad either
                // way so the manager can retype without editing digits out.
                this.approvalForm = { pin: '' };
                if (e?.status === 422 || e?.status === 429) {
                    const msg = e.message || Object.values(e.errors || {}).flat()[0] || this.labels.approval_failed || 'Approval failed.';
                    this.$store.toasts?.push({ type: 'error', message: msg });
                }
            } finally {
                this.approvalSubmitting = false;
            }
        },

        closeHoldPrompt() {
            if (this.holdSubmitting) return;
            this.holdNamePromptOpen = false;
        },

        async submitHold() {
            if (this.holdSubmitting || this.cart.length === 0 || !holdUrl) return;
            this.holdSubmitting = true;
            try {
                const payload = {
                    store_id:    storeId,
                    customer_id: this.customer?.id ?? null,
                    held_label:  (this.holdLabel || '').trim() || null,
                    // Round-trip the order-level discount so resume gets it
                    // back unchanged. Null when no discount is set.
                    discount:    this.discount.type
                        ? { type: this.discount.type, value: String(this.discount.value) }
                        : null,
                    items: this.cart.map((l) => ({
                        product_id: l.product_id,
                        variant_id: l.variant_id ?? null,
                        quantity:   String(l.quantity),
                        unit_price: String(l.unit_price),
                        notes:      l.note || null,
                    })),
                };
                // Snapshot what's about to be reserved before clearing the cart.
                const reservedItems = this.cart.map((l) => ({
                    product_id: l.product_id,
                    variant_id: l.variant_id ?? null,
                    quantity:   String(l.quantity),
                }));
                const { data } = await posPost(holdUrl, payload);
                // Server accepted the hold + bumped reserved_quantity.
                // Mirror it locally so the next ring-up sees the SKU
                // as reserved without a page refresh.
                this._applyReservation(reservedItems, -1);
                this.cart               = [];
                this.customer           = null;
                this.discount           = { type: null, value: 0 };
                this.holdLabel          = '';
                this.holdNamePromptOpen = false;
                this._resetClientUuid();
                // Confirmation toast — lib/http.js only toasts errors,
                // not successes, so we explicitly surface the hold number.
                const num = data?.sale?.number || data?.extra?.sale?.number;
                this.$store.toasts?.push({
                    type:    'success',
                    message: num
                        ? (this.labels.order_held_named || 'Order :number held.').replace(':number', num)
                        : (this.labels.order_held || 'Order held.'),
                });
            } catch (e) {
                const msg = e?.message
                    || e?.errors?._action?.[0]
                    || this.labels.hold_failed || 'Couldn\'t hold the order.';
                this.$store.toasts?.push({
                    type:     'error',
                    message:  msg,
                    duration: 6000,
                });
            } finally {
                this.holdSubmitting = false;
            }
        },

        async openHeldDrawer() {
            this.heldDrawerOpen = true;
            this.heldLoading    = true;
            this.heldList       = [];
            try {
                const { data } = await posGet(holdsListUrl);
                this.heldList = Array.isArray(data) ? data : [];
            } catch (e) {
                this.heldList = [];
            } finally {
                this.heldLoading = false;
            }
        },

        closeHeldDrawer() {
            this.heldDrawerOpen = false;
        },

        async resumeHeld(held) {
            if (!holdResumeUrlTemplate) return;
            const url = holdResumeUrlTemplate.replace('__ID__', held.id);
            try {
                const { data } = await posPost(url, {});
                if (data?.items?.length) {
                    // Server released the reservation — bump local
                    // on_hand back BEFORE pushing to cart so the new
                    // cart-line snapshot captures the freshly-available
                    // qty (not the still-reserved value).
                    this._applyReservation(data.items, +1);
                    // Replace local cart with the resumed lines.
                    this.cart        = [];
                    this._nextLineId = 1;
                    data.items.forEach((item) => {
                        this._pushLine({
                            product_id:    item.product_id,
                            variant_id:    item.variant_id,
                            name:          item.name,
                            sku:           item.sku,
                            unit:          item.unit ?? 'pc',
                            unit_price:    item.unit_price,
                            tax_group_id:  item.tax_group_id,
                            on_hand:       0,
                            image_url:     null,
                            variant_label: item.variant_label ?? null,
                        }, true); // silent — don't beep once per item on resume
                        // _pushLine starts qty at 1 — bump to the saved qty.
                        const row = this.cart[this.cart.length - 1];
                        row.quantity = parseFloat(item.quantity) || 1;
                    });
                    this.customer = data.customer || null;
                    // Restore the order-level discount the cashier had
                    // set when this ticket was held. Server returns
                    // {type, value} or null.
                    if (data.discount && data.discount.type && parseFloat(data.discount.value) > 0) {
                        this.discount = {
                            type:  data.discount.type,
                            value: parseFloat(data.discount.value) || 0,
                        };
                    } else {
                        this.discount = { type: null, value: 0 };
                this.discountApproval = null; this._pendingApproval = null;
                    }
                }
                this.heldDrawerOpen = false;
            } catch (e) {
                // http.js auto-handles 401/419/5xx; surface 422 business
                // errors (e.g. the held order was already resumed/voided).
                const msg = e?.message || e?.errors?._action?.[0] || this.labels.resume_failed || 'Couldn\'t resume that order.';
                this.$store.toasts?.push({ type: 'error', message: msg, duration: 6000 });
            }
        },

        voidHeld(held) {
            if (!holdVoidUrlTemplate || this.heldVoidingId === held.id) return;
            // Always show the global confirm dialog — held orders are
            // force-deleted (no soft-delete trail), so we want one extra
            // "yes" click before they vanish.
            this.$store.confirm?.show({
                title:        this.labels.delete_held_title || 'Delete held order?',
                message:      (this.labels.delete_held_message || 'Order :label will be permanently removed.').replace(':label', held.label || held.number),
                intent:       'danger',
                confirmLabel: this.labels.delete_held_confirm || 'Delete order',
                cancelLabel:  this.labels.confirm_keep || 'Keep',
                onConfirm:    () => this._reallyVoidHeld(held),
            });
        },

        async _reallyVoidHeld(held) {
            const url = holdVoidUrlTemplate.replace('__ID__', held.id);
            this.heldVoidingId = held.id;
            try {
                const { data } = await posPost(url, { _method: 'DELETE' });
                // Bump local on_hand back for every line the server
                // just unreserved. `freed_items` is the snapshot the
                // controller takes before force-deleting the hold.
                this._applyReservation(data?.freed_items ?? [], +1);
                this.heldList = this.heldList.filter((h) => h.id !== held.id);
                this.$store.toasts?.push({
                    type:    'success',
                    message: (this.labels.held_order_deleted || 'Order :label deleted.').replace(':label', held.label || held.number),
                });
            } finally {
                this.heldVoidingId = null;
            }
        },

        /* ── Refund / Return ──────────────────────────────────── */

        /**
         * Open the lookup modal. Always fires an empty-query lookup so
         * the cashier sees the 20 most recent completed sales on first
         * open (the common "customer returning right after buying" flow).
         */
        async openRefundLookup() {
            if (!refundLookupUrl) return;
            // Refunds require a server round-trip — looking up the sale,
            // computing remaining-returnable qty per line, releasing
            // stock + writing the refund row. None of that is safe to
            // queue offline without first caching every completed sale
            // locally (v1.1+). For now we bail with a clear message so
            // the cashier knows why nothing happens.
            if (this.connectivity.status === 'offline') {
                this.$store.toasts?.push({
                    type:    'warning',
                    message: this.labels.refunds_need_internet || 'Refunds need an internet connection — try again when you\'re back online.',
                    duration: 5000,
                });
                return;
            }
            this.refundLookupOpen   = true;
            this.refundLookupQuery  = '';
            this.refundLookupResults = [];
            await this._fetchRefundLookup();
            this.$nextTick(() => document.querySelector('[data-refund-lookup-input]')?.focus());
        },

        closeRefundLookup() {
            this.refundLookupOpen = false;
        },

        _refundLookupDebounced() {
            clearTimeout(this._refundLookupTimer);
            this._refundLookupTimer = setTimeout(() => this._fetchRefundLookup(), 200);
        },

        async _fetchRefundLookup() {
            if (!refundLookupUrl) return;
            this.refundLookupLoading = true;
            try {
                const { data } = await posGet(refundLookupUrl, { q: this.refundLookupQuery });
                this.refundLookupResults = Array.isArray(data) ? data : [];
            } catch (e) {
                this.refundLookupResults = [];
            } finally {
                this.refundLookupLoading = false;
            }
        },

        /**
         * Pick a sale from the lookup → fetch its detail → open the
         * refund modal pre-populated for a one-click full refund.
         */
        async pickRefundSale(sale) {
            if (!refundShowUrlTemplate) return;
            const url = refundShowUrlTemplate.replace('__ID__', sale.id);
            try {
                const { data } = await posGet(url);
                this.refundSale     = data?.sale ?? null;
                this.refundReasons  = Array.isArray(data?.reasons) ? data.reasons : [];
                this.refundMethods  = Array.isArray(data?.methods) ? data.methods : [];
                this.refundMethodId = data?.default_method_id ?? null;
                this.refundReasonId = this.refundReasons[0]?.id ?? null;
                this.refundCanRefund = !!data?.can_refund;
                this.refundRestock  = true;
                this.refundNotes    = '';
                this.refundClientUuid = (window.crypto && typeof window.crypto.randomUUID === 'function')
                    ? window.crypto.randomUUID()
                    : 'rfb-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10);
                // Seed each row's `refundQty` to its remaining returnable
                // qty — full refund as the one-tap default.
                this.refundRows = (Array.isArray(data?.items) ? data.items : []).map((r) => ({
                    ...r,
                    refundQty: parseFloat(r.remaining) || 0,
                }));
                this.refundLookupOpen = false;
                this.refundOpen       = true;
            } catch (e) {
                const msg = e?.message
                    || e?.errors?._action?.[0]
                    || this.labels.refund_open_failed || 'Couldn\'t open this sale for refund.';
                this.$store.toasts?.push({ type: 'error', message: msg });
            }
        },

        closeRefund() {
            if (this.refundSubmitting) return;
            this.refundOpen = false;
        },

        setRefundQty(row, value) {
            const max = parseFloat(row.remaining) || 0;
            const n   = parseFloat(value);
            if (!Number.isFinite(n) || n < 0) {
                row.refundQty = 0;
                return;
            }
            row.refundQty = Math.min(n, max);
        },

        refundIncrement(row) {
            const max = parseFloat(row.remaining) || 0;
            row.refundQty = Math.min((parseFloat(row.refundQty) || 0) + 1, max);
        },

        refundDecrement(row) {
            const q = (parseFloat(row.refundQty) || 0) - 1;
            row.refundQty = q < 0 ? 0 : q;
        },

        /**
         * Per-line refund total = (qty × unit_price − proportional
         * discount slice) + proportional tax slice. Mirrors the server-
         * side math in RecordSaleReturn so the cashier sees the same
         * number that lands on the refund row.
         */
        refundLineTotal(row) {
            const qty = parseFloat(row.refundQty) || 0;
            if (qty <= 0) return 0;
            const origQty = parseFloat(row.quantity) || 0;
            if (origQty <= 0) return 0;
            const ratio = qty / origQty;
            const unitPrice = parseFloat(row.unit_price) || 0;
            let subtotal = qty * unitPrice;
            const discount = parseFloat(row.discount_amount) || 0;
            if (discount > 0) subtotal -= discount * ratio;
            const tax = (parseFloat(row.tax_amount) || 0) * ratio;
            return Math.max(0, subtotal + tax);
        },

        get refundTotals() {
            let subtotal = 0, tax = 0, grand = 0;
            for (const r of this.refundRows) {
                const qty = parseFloat(r.refundQty) || 0;
                if (qty <= 0) continue;
                const origQty = parseFloat(r.quantity) || 0;
                if (origQty <= 0) continue;
                const ratio   = qty / origQty;
                const unitP   = parseFloat(r.unit_price) || 0;
                let lineSub   = qty * unitP;
                const disc    = parseFloat(r.discount_amount) || 0;
                if (disc > 0) lineSub -= disc * ratio;
                const lineTax = (parseFloat(r.tax_amount) || 0) * ratio;
                subtotal += lineSub;
                tax      += lineTax;
                grand    += lineSub + lineTax;
            }
            return { subtotal, tax, grand };
        },

        get canSubmitRefund() {
            if (!this.refundReasonId) return false;
            if (!this.refundSale) return false;
            // Must refund at least one line with a positive qty.
            return this.refundRows.some((r) => (parseFloat(r.refundQty) || 0) > 0);
        },

        /**
         * Entry point for the invoice-based refund's submit button. A
         * cashier who already holds `sales.refund` posts straight
         * through; otherwise this opens the manager-PIN modal and
         * `_doSubmitRefund()` runs once approved (see
         * `submitRefundApproval()`).
         */
        async submitRefund() {
            if (this.refundSubmitting || !this.canSubmitRefund) return;
            if (!refundStoreUrlTemplate || !this.refundSale) return;
            // Connection may have dropped after the cashier loaded the
            // sale into the modal but before they hit submit. Same
            // reasoning as `openRefundLookup` — refunds can't be queued
            // without a sale cache, so we surface the offline state
            // instead of letting the POST blow up with a generic
            // network error.
            if (this.connectivity.status === 'offline') {
                this.$store.toasts?.push({
                    type:    'warning',
                    message: this.labels.refunds_need_internet || 'Refunds need an internet connection — try again when you\'re back online.',
                    duration: 5000,
                });
                return;
            }

            if (!this.refundCanRefund && !this._hasRefundApprovalFor(this.refundTotals.grand)) {
                this._pendingRefundApproval = { kind: 'invoice', amount: this.refundTotals.grand };
                this.refundApprovalForm = { pin: '' };
                this.refundApprovalOpen = true;
                return;
            }

            await this._doSubmitRefund();
        },

        async _doSubmitRefund() {
            this.refundSubmitting = true;
            const url = refundStoreUrlTemplate.replace('__ID__', this.refundSale.id);

            const payload = {
                reason_code_id:   this.refundReasonId,
                refund_method_id: this.refundMethodId || null,
                restock:          this.refundRestock ? 1 : 0,
                notes:            (this.refundNotes || '').trim() || null,
                client_uuid:      this.refundClientUuid,
                refund_approval:  this.refundCanRefund ? null : (this.refundApproval?.token ?? null),
                items: this.refundRows
                    .filter((r) => (parseFloat(r.refundQty) || 0) > 0)
                    .map((r) => ({
                        sale_item_id: r.id,
                        quantity:     String(r.refundQty),
                        restock:      null,   // inherit header
                    })),
            };

            try {
                const { data } = await posPost(url, payload);
                this.refundSuccess = data?.refund ?? null;
                this.refundSuccessOpen = true;
                this.refundOpen        = false;
                this.refundApproval    = null; // spent — a new refund needs a fresh approval
                // Reset so the next refund open starts clean.
                this.refundSale = null;
                this.refundRows = [];
                // Sequenced, not fire-and-forget in parallel — both share
                // the `printingReceipt` guard (so a second refund attempt
                // mid-print is blocked), and printing two jobs to the same
                // physical WebUSB printer at once would race the write.
                await this.printRefundReceipt(this.refundSuccess);
                if (this.refundSuccess?.sale_id) {
                    await this.printCorrectedSaleCopy(this.refundSuccess.sale_id);
                }
            } catch (e) {
                const msg = e?.message
                    || e?.errors?._action?.[0]
                    || this.labels.refund_failed || 'Refund failed.';
                this.$store.toasts?.push({
                    type:     'error',
                    message:  msg,
                    duration: 6000,
                });
            } finally {
                this.refundSubmitting = false;
            }
        },

        /** Does a still-fresh approval token cover this amount already? */
        _hasRefundApprovalFor(amount) {
            return !!this.refundApproval && amount <= Number(this.refundApproval.amount) + 1e-6;
        },

        closeRefundApproval() {
            if (this.refundApprovalSubmitting) return;
            this._pendingRefundApproval = null;
            this.refundApprovalOpen = false;
        },

        /**
         * A manager types their PIN to approve whichever refund is
         * pending (`_pendingRefundApproval.kind` — 'invoice' or 'blind').
         * On success, banks the token and immediately continues the
         * flow that was waiting on it.
         */
        async submitRefundApproval() {
            if (this.refundApprovalSubmitting || !this._pendingRefundApproval) return;
            if (!refundApprovalUrl) return;
            if ((this.refundApprovalForm.pin || '').length !== 6) return;

            this.refundApprovalSubmitting = true;
            const pa = this._pendingRefundApproval;
            try {
                const { data } = await posPost(refundApprovalUrl, {
                    pin:    this.refundApprovalForm.pin,
                    amount: pa.amount,
                });
                this.refundApproval = { token: data.token, approver: data.approver, amount: pa.amount };
                this._pendingRefundApproval = null;
                this.refundApprovalOpen = false;
                this.refundApprovalForm = { pin: '' };
                this.$store.toasts?.push({ type: 'success', message: data.message || this.labels.refund_approved || 'Refund approved.' });

                if (pa.kind === 'blind') {
                    await this._doSubmitBlindRefund();
                } else {
                    await this._doSubmitRefund();
                }
            } catch (e) {
                this.refundApprovalForm = { pin: '' };
                if (e?.status === 422 || e?.status === 429) {
                    const msg = e.message || Object.values(e.errors || {}).flat()[0] || this.labels.approval_failed || 'Approval failed.';
                    this.$store.toasts?.push({ type: 'error', message: msg });
                }
            } finally {
                this.refundApprovalSubmitting = false;
            }
        },

        /**
         * "Check price" — scan/type a barcode and see what it rings up
         * at, with NO cart mutation. New to the web cashier (the mobile
         * app has had this as its own screen for a while); lives in the
         * Focus layout's action rail. Reuses the same local-catalog
         * resolver the cart-scan path uses, so it works offline too.
         */
        openPriceCheck() {
            this.priceCheckQuery  = '';
            this.priceCheckResult = null;
            this.priceCheckOpen   = true;
        },
        closePriceCheck() {
            this.priceCheckOpen = false;
        },
        priceCheckEnter() {
            const q = this.priceCheckQuery.trim();
            if (q === '') return;
            const lower = q.toLowerCase();

            const variantHit = this._findVariantExact(lower);
            const product = variantHit ? variantHit.product : this.products.find((p) =>
                (p.barcode || '').toLowerCase() === lower || (p.sku || '').toLowerCase() === lower
            );

            if (!product) {
                this.priceCheckResult = 'not_found';
                this.priceCheckQuery  = '';
                return;
            }

            const variant = variantHit ? variantHit.variant : null;
            this.priceCheckResult = {
                name:           variant ? `${product.name} — ${variant.label || variant.sku || ''}` : product.name,
                sku:            variant?.sku || product.sku,
                selling_price:  variant ? variant.selling_price : product.selling_price,
                sale_price:     variant ? variant.sale_price    : product.sale_price,
                charge_price:   variant ? (variant.charge_price ?? variant.selling_price) : (product.charge_price ?? product.selling_price),
            };
            this.priceCheckQuery = '';
        },

        /* ── Blind refund (scan, no invoice) ───────────────────── */

        openBlindRefund() {
            this.blindRefundRows     = [];
            this.blindScanQuery      = '';
            this.blindRefundReasonId = this.returnReasons[0]?.id ?? null;
            this.blindRefundMethodId = this.blindRefundNonGatewayMethods[0]?.id ?? null;
            this.blindRefundRestock  = true;
            this.blindRefundNotes    = '';
            this.blindRefundOpen     = true;
        },

        closeBlindRefund() {
            if (this.blindRefundSubmitting) return;
            this.blindRefundOpen = false;
        },

        // Gateway tenders can't back a no-invoice refund — there's no
        // original charge to reverse (see RecordBlindReturn). Filter
        // them out of the picker entirely rather than let the cashier
        // pick one and get a 422 at submit.
        get blindRefundNonGatewayMethods() {
            const gatewayProviders = new Set(['stripe', 'razorpay', 'flutterwave', 'paystack']);
            return this.paymentMethods.filter((m) => !gatewayProviders.has(m.provider));
        },

        /** Scan/type a barcode into the blind-refund panel's own input. */
        blindScanEnter() {
            const q = this.blindScanQuery.trim();
            if (q === '') return;
            const lower = q.toLowerCase();

            const variantHit = this._findVariantExact(lower);
            const product = variantHit ? variantHit.product : this.products.find((p) =>
                (p.barcode || '').toLowerCase() === lower || (p.sku || '').toLowerCase() === lower
            );

            if (!product) {
                this.$store.toasts?.push({ type: 'warning', message: (this.labels.no_product_for_barcode || 'No product for ":code".').replace(':code', q) });
                this.blindScanQuery = '';
                return;
            }

            const variant = variantHit ? variantHit.variant : null;
            const code = variant?.barcode || variant?.sku || product.barcode || product.sku || q;
            const existing = this.blindRefundRows.find((r) => r.code === code);
            if (existing) {
                existing.qty = (parseFloat(existing.qty) || 0) + 1;
            } else {
                const price = variant
                    ? Number(variant.charge_price ?? variant.selling_price) || 0
                    : Number(product.charge_price ?? product.selling_price) || 0;
                this.blindRefundRows.push({
                    code,
                    name: variant ? `${product.name} — ${variant.label || variant.sku || ''}` : product.name,
                    sku:  variant?.sku || product.sku,
                    qty:  1,
                    unit_price: price,
                });
            }
            if (this.settings.sound_on_add !== false) playAddBeep();
            this.blindScanQuery = '';
        },

        blindRefundInc(row) { row.qty = (parseFloat(row.qty) || 0) + 1; },
        blindRefundDec(row) {
            const q = (parseFloat(row.qty) || 0) - 1;
            row.qty = q < 0 ? 0 : q;
        },
        blindRefundRemove(row) {
            this.blindRefundRows = this.blindRefundRows.filter((r) => r !== row);
        },
        blindRefundLineTotal(row) {
            return (parseFloat(row.qty) || 0) * (parseFloat(row.unit_price) || 0);
        },

        get blindRefundTotal() {
            return this.blindRefundRows.reduce((sum, r) => sum + this.blindRefundLineTotal(r), 0);
        },

        get canSubmitBlindRefund() {
            if (!this.blindRefundReasonId) return false;
            return this.blindRefundRows.some((r) => (parseFloat(r.qty) || 0) > 0);
        },

        /** Entry point for the blind-refund panel's submit button —
         *  ALWAYS needs manager approval (there's no permission path
         *  that lets a cashier skip it for a no-invoice refund). */
        async submitBlindRefundApproval() {
            if (this.blindRefundSubmitting || !this.canSubmitBlindRefund) return;
            if (this.connectivity.status === 'offline') {
                this.$store.toasts?.push({
                    type: 'warning',
                    message: this.labels.refunds_need_internet || 'Refunds need an internet connection — try again when you\'re back online.',
                    duration: 5000,
                });
                return;
            }

            const amount = this.blindRefundTotal;
            if (this._hasRefundApprovalFor(amount)) {
                await this._doSubmitBlindRefund();
                return;
            }
            this._pendingRefundApproval = { kind: 'blind', amount };
            this.refundApprovalForm = { pin: '' };
            this.refundApprovalOpen = true;
        },

        async _doSubmitBlindRefund() {
            if (!refundStoreBlindUrl) return;
            this.blindRefundSubmitting = true;

            const payload = {
                reason_code_id:   this.blindRefundReasonId,
                refund_method_id: this.blindRefundMethodId || null,
                restock:          this.blindRefundRestock ? 1 : 0,
                notes:            (this.blindRefundNotes || '').trim() || null,
                client_uuid:      (window.crypto && typeof window.crypto.randomUUID === 'function')
                    ? window.crypto.randomUUID()
                    : 'brf-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10),
                refund_approval:  this.refundApproval?.token ?? null,
                items: this.blindRefundRows
                    .filter((r) => (parseFloat(r.qty) || 0) > 0)
                    .map((r) => ({
                        barcode:    r.code,
                        quantity:   String(r.qty),
                        unit_price: String(r.unit_price),
                    })),
            };

            try {
                const { data } = await posPost(refundStoreBlindUrl, payload);
                this.blindRefundSuccess     = data?.refund ?? null;
                this.blindRefundSuccessOpen = true;
                this.blindRefundOpen        = false;
                this.refundApproval         = null; // spent
                this.blindRefundRows        = [];
                this.printRefundReceipt(this.blindRefundSuccess);
            } catch (e) {
                const msg = e?.message
                    || e?.errors?._action?.[0]
                    || this.labels.refund_failed || 'Refund failed.';
                this.$store.toasts?.push({ type: 'error', message: msg, duration: 6000 });
            } finally {
                this.blindRefundSubmitting = false;
            }
        },

        /** Short date formatter for the lookup result rows. */
        _formatDate(iso) {
            if (!iso) return '';
            try {
                const d = new Date(iso);
                return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
                    + ' · ' + d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
            } catch (e) { return ''; }
        },

        /* ── Totals (display-only — server is authoritative) ─── */

        get subtotal() {
            // JS `subtotal` should be sum of gross so it matches PHP's base for discount.
            return this.cart.reduce(
                (s, l) => s + (parseFloat(l.unit_price) || 0) * (parseFloat(l.quantity) || 0),
                0,
            );
        },

        /**
         * Final-price line total for the cashier-line display: the
         * gross subtotal plus exclusive tax (inclusive tax is already
         * baked into unit_price). Used by the cart line AND by the
         * `subtotal` getter so the summary number matches what the
         * cashier sees stacked above.
         */
        lineTotalDisplay(line) {
            // Show the line's own-discounted price (the order-level discount
            // is shown separately in the totals footer).
            const sub = ((parseFloat(line?.unit_price) || 0) * (parseFloat(line?.quantity) || 0)) - this.lineOwnDiscount(line);
            if (!line || !line.tax_taxable || line.tax_inclusive) return sub;
            return sub + this._lineTax(line);
        },

        /**
         * Per-line tax preview. Mirrors {@see TaxResolver}'s math
         * closely enough for an honest display — server is still the
         * authority at checkout.
         *
         * Per line:
         *   - if `tax_taxable=false` (exempt / zero-rated / reverse-charge)
         *     → tax = 0.
         *   - if `tax_inclusive=true` → unit_price IS gross. Net is
         *     back-extracted: net = gross / (1 + rate/100); tax = gross − net.
         *   - if `tax_inclusive=false` → unit_price is net. tax = net × rate/100.
         *
         * Mixed-mode carts (some lines inclusive, some exclusive) are
         * supported — the totals shake out per line.
         */
        /** Per-line explicit discount (Slice 4), on that line's own gross. */
        lineOwnDiscount(line) {
            const d = line?.discount;
            if (!d || !d.type) return 0;
            const gross = (parseFloat(line.unit_price) || 0) * (parseFloat(line.quantity) || 0);
            const v = parseFloat(d.value) || 0;
            return d.type === 'pct' ? (gross * v) / 100 : Math.min(v, gross);
        },

        get sumLineDiscounts() {
            return this.cart.reduce((s, l) => s + this.lineOwnDiscount(l), 0);
        },

        /** Base the ORDER-level discount applies to (gross minus line discounts). */
        get discountedSubtotal() {
            return Math.max(0, this.subtotal - this.sumLineDiscounts);
        },

        /** Order-level discount amount, applied on top of the line discounts. */
        get orderDiscountAmt() {
            if (!this.discount.type) return 0;
            const v = parseFloat(this.discount.value) || 0;
            if (this.discount.type === 'pct') return (this.discountedSubtotal * v) / 100;
            return Math.min(v, this.discountedSubtotal);
        },

        /** A line's share of the order discount, distributed by line net. */
        _lineOrderShare(line) {
            const base = this.discountedSubtotal;
            if (base <= 0 || this.orderDiscountAmt <= 0) return 0;
            const net = ((parseFloat(line.unit_price) || 0) * (parseFloat(line.quantity) || 0)) - this.lineOwnDiscount(line);
            return (net / base) * this.orderDiscountAmt;
        },

        /** Total per-line discount (own + order share) — server payload + tax base. */
        _lineDiscount(line) {
            return this.lineOwnDiscount(line) + this._lineOrderShare(line);
        },

        /**
         * The sale-line payload — product, qty, price, and the per-line
         * allocated order discount. Shared by complete() AND the QR
         * payment-session create, so the server prices the *same* lines
         * both when it charges the customer and when it records the sale.
         * That's what guarantees charge == record (no JS-vs-server drift).
         */
        _buildSaleItems() {
            return this.cart.map((l) => ({
                product_id: l.product_id,
                variant_id: l.variant_id ?? null,
                batch_id:   l.batch_id ?? null,
                quantity:   String(l.quantity),
                unit_price: String(l.unit_price),
                discount_amount: this._lineDiscount(l).toFixed(4),
                notes:      l.note || null,
            }));
        },

        _lineTax(line) {
            if (!line?.tax_taxable) return 0;
            const rate = parseFloat(line.tax_rate_total) || 0;
            if (rate <= 0) return 0;
            const gross = (parseFloat(line.unit_price) || 0) * (parseFloat(line.quantity) || 0);
            const discount = this._lineDiscount(line);
            const lineSubtotal = Math.max(0, gross - discount);
            if (line.tax_inclusive) {
                const net = lineSubtotal / (1 + rate / 100);
                return lineSubtotal - net;
            }
            return lineSubtotal * rate / 100;
        },

        /** Tax that ADDS to grand total (exclusive lines only). */
        get taxAdded() {
            return this.cart.reduce(
                (s, l) => l.tax_inclusive ? s : s + this._lineTax(l),
                0,
            );
        },

        /** Tax already INCLUDED in the subtotal (inclusive lines only). */
        get taxIncluded() {
            return this.cart.reduce(
                (s, l) => l.tax_inclusive ? s + this._lineTax(l) : s,
                0,
            );
        },

        /** Total tax (added + included) — what shows on the tax line. */
        get tax() {
            return this.taxAdded + this.taxIncluded;
        },

        /** Total discount shown on the "Discount" line = per-line discounts
         *  plus the order-level discount (which stacks on top). */
        get discountAmt() {
            return this.sumLineDiscounts + this.orderDiscountAmt;
        },

        get grandTotal() {
            return Math.max(0, this.subtotal - this.discountAmt + this.taxAdded);
        },

        get itemCount() {
            return this.cart.reduce((s, l) => {
                if (l.weighed) return s + 1;
                return s + (parseFloat(l.quantity) || 0);
            }, 0);
        },

        get lineCount() { return this.cart.length; },

        /**
         * Tile price display helpers. For a variant product we want
         * "from ₹min" — or "₹min – ₹max" when the range matters — so
         * the cashier doesn't have to open the picker just to see what
         * range a product covers.
         */
        tilePriceLabel(product) {
            if (!product.has_variants) return this.money(product.charge_price ?? product.selling_price);
            const min = product.price_min ?? product.selling_price;
            const max = product.price_max ?? product.selling_price;
            if (parseFloat(min) === parseFloat(max)) return this.money(min);
            return `${this.money(min)} – ${this.money(max)}`;
        },

        tileMrpLabel(product) {
            if (!product.mrp) return null;
            const sell = parseFloat(product.has_variants ? (product.price_min ?? product.selling_price) : product.selling_price);
            if (parseFloat(product.mrp) <= sell) return null;
            return this.money(product.mrp);
        },

        /**
         * Second strikethrough tier, one notch below tileMrpLabel: when a
         * non-variant product has an active `sale_price`, the *regular*
         * selling price is what gets struck through (tilePriceLabel is
         * already showing the sale price as the big number). Null when
         * there's no active sale — the tile then falls back to the
         * mrp-vs-selling-price comparison alone, unchanged.
         */
        tileWasLabel(product) {
            if (product.has_variants || !product.sale_price) return null;
            return this.money(product.selling_price);
        },

        /**
         * Returns a short, human stock string for the tile's info row.
         * Returns null when we don't track stock for this product (no
         * row should appear at all in that case).
         */
        tileStockLabel(product) {
            if (!product.track_stock) return null;
            if (product.on_hand === null || product.on_hand === undefined) return null;
            // Kit tiles already use the under-name row for the "Includes
            // A, B, C" components summary. Stacking the stock line on top
            // of it crams the tile and the name gets lost in the noise —
            // see the screenshot user feedback. The kit's components
            // carry the real stock signal anyway.
            if (product.is_kit) return null;
            const q = parseFloat(product.on_hand) || 0;
            const unit = product.unit ? ` ${product.unit}` : '';
            if (q <= 0) return null;  // "out" handled by the corner badge alone
            return `${this._fmtQty(q)}${unit} in stock`;
        },

        /**
         * Trim trailing zeros from a stock quantity — products track
         * stock at 4dp but most are whole units. "5.0000" reads as a
         * bug; "5" is what the cashier expects to see.
         */
        _fmtQty(q) {
            const n = parseFloat(q) || 0;
            if (Number.isInteger(n)) return String(n);
            return String(n.toFixed(4)).replace(/0+$/, '').replace(/\.$/, '');
        },

        /**
         * One-line summary of what a kit contains — used on the tile
         * (`title` attr for tooltip + visible truncated text). Renders
         * "Includes 2× Cola, 1× Chips" — variant labels are folded in
         * parens when present.
         */
        kitSummary(product) {
            if (!product?.is_kit) return '';
            const parts = (product.kit_items ?? []).map((k) => {
                const qty   = this._fmtQty(k.quantity);
                const label = k.variant_label ? `${k.name} (${k.variant_label})` : k.name;
                return `${qty}× ${label}`;
            });
            if (parts.length === 0) return '';
            return (this.labels.kit_includes || 'Includes :list').replace(':list', parts.join(', '));
        },

        money(value) {
            return window.posFormatMoney
                ? window.posFormatMoney(value)
                : Number(value || 0).toFixed(2);
        },

        /* ── Customer-Facing Display (CFD) ─────────────────────────
         * Slice 1: idle + sale states. The cashier is the single source
         * of truth; the second screen only paints what it's sent. The
         * snapshot is customer-SAFE — names, quantities, and pre-formatted
         * money only. Never cost, margin, or internal ids beyond a line key.
         * See docs/features/customer-display.md §4.
         */

        /** Build one customer-safe snapshot from the current cart. */
        _cfdSnapshot() {
            const lines = this.cart.map((l) => ({
                key:        l.id,
                name:       l.name ?? '',
                image:      l.image_url || null,
                quantity:   this._fmtQty(l.quantity) + (l.weighed && l.unit ? ' ' + l.unit : ''),
                unit_price: this.money(l.unit_price),
                line_total: this.money(this.lineTotalDisplay(l)),
            }));

            const last = this.cart.length ? this.cart[this.cart.length - 1] : null;
            const lastLine = last ? {
                name:       last.name ?? '',
                image:      last.image_url || null,
                meta:       this._fmtQty(last.quantity) + (last.weighed && last.unit ? ' ' + last.unit : '')
                                + ' × ' + this.money(last.unit_price),
                line_total: this.money(this.lineTotalDisplay(last)),
            } : null;

            const c = this.customer;
            const customer = c ? {
                name:     c.name ?? '',
                initials: String(c.name ?? '?').trim().split(/\s+/).map((w) => w[0]).slice(0, 2).join('').toUpperCase() || '?',
                // Loyalty points are optional — only surfaced when the
                // attached customer record carries them.
                points:   c.loyalty_points ?? c.points ?? null,
            } : null;

            const discount = this.discountAmt;
            const tax = this.tax;

            // The pay modal being open takes over the display (Slice 2):
            // amount due, cash tendered/change, and — for the QR-chooser
            // flow — the pay_url the customer scans off their own screen.
            const state = this.payOpen ? 'payment' : (this.cart.length > 0 ? 'sale' : 'idle');
            const payment = this.payOpen ? {
                amount_due:   this.money(this.payRemaining),
                tendered:     this.money(parseFloat(this.payTendered) || 0),
                has_tendered: (parseFloat(this.payTendered) || 0) > 0,
                change_due:   this.money(this.changeDue),
                has_change:   this.changeDue > 0,
                // Present only while a QR-chooser session is live.
                pay_url:      this.paySession?.pay_url || null,
                // Raw `upi://pay?…` deep link when the cashier picks a UPI
                // method — the CFD renders it to a QR (gated by the terminal's
                // "show UPI QR on customer display" option). Null otherwise.
                upi_pay_url:  this.upiPayUrl || null,
                // 'apple_pay' | 'google_pay' | null — set only when the
                // cashier tapped a wallet button. Same `pay_url`/QR as
                // the generic flow; the CFD just swaps the scan label.
                wallet_brand: this.walletBrand || null,
            } : null;

            return {
                v:     1,
                seq:   ++cfdSeq,
                state,
                lines,
                last_line: lastLine,
                customer,
                payment,
                totals: {
                    item_count:   this._fmtQty(this.itemCount),
                    subtotal:     this.money(this.subtotal),
                    discount:     this.money(discount),
                    has_discount: discount > 0,
                    tax:          this.money(tax),
                    has_tax:      tax > 0,
                    grand:        this.money(this.grandTotal),
                },
            };
        },

        /**
         * Publish the "thank you" frame after a sale completes, then return
         * the display to idle after a dwell. Fired from a $watch on
         * `successOpen` (covers the online + offline success paths).
         *
         * On completion the cart is cleared, which makes the reactive
         * `x-effect` want to publish an idle frame — that used to race this
         * one and sometimes win, so the thank-you (and its receipt QR) never
         * showed. We now (a) publish immediately with a fresh seq AND (b) set
         * a suppression window so `publishCfd()` drops idle frames for the
         * dwell. Either way the thank-you wins. See customer-display.md §3.4.
         */
        _cfdThankyou() {
            if (!this._cfdPublisher && this.cfdTransport !== 'separate_device') return;
            const s = this.successSale || {};
            const change = s.change_returned ?? 0;
            // Only online sales carry a server receipt link — an offline
            // sale isn't on the server yet, so its thank-you shows no QR.
            const receiptUrl = s.public_receipt_url || null;
            const dwellMs = 9000;

            cfdSuppressIdleUntil = Date.now() + dwellMs;

            this._emitCfd({
                v:     1,
                seq:   ++cfdSeq,
                state: 'thankyou',
                thankyou: {
                    change_due:  this.money(change),
                    has_change:  parseFloat(change) > 0,
                    receipt_url: receiptUrl,
                },
            });

            // Return to idle after the dwell — unless the cashier already
            // started the next sale / reopened pay.
            setTimeout(() => {
                cfdSuppressIdleUntil = 0;
                if (this.cart.length === 0 && !this.payOpen) this.publishCfd();
            }, dwellMs);
        },

        /** Push the current snapshot to the customer display. Called
         *  reactively from an `x-effect` in the Blade — safe to over-call
         *  (the display drops stale frames by `seq`). */
        publishCfd() {
            if (!this._cfdPublisher && this.cfdTransport !== 'separate_device') return;
            const snap = this._cfdSnapshot();
            // Don't let the cart-clear idle frame overwrite a live thank-you
            // screen; a real new sale (non-idle) is allowed through.
            if (snap.state === 'idle' && Date.now() < cfdSuppressIdleUntil) return;
            this._emitCfd(snap);
        },

        /** Emit one frame over every configured transport: the same-machine
         *  BroadcastChannel always, plus the server relay when this terminal
         *  drives a separate-device display. */
        _emitCfd(snap) {
            if (this._cfdPublisher) { try { this._cfdPublisher.publish(snap); } catch (e) { /* no-op */ } }
            if (this.cfdTransport === 'separate_device') this._queueCfdPush(snap);
        },

        /** Debounced, latest-wins POST of a snapshot to the relay. Coalesces
         *  a burst of cart changes into ~1 request per 700ms so a fast
         *  scanner doesn't hammer the server. */
        _queueCfdPush(snap) {
            if (!this.cfdPushUrl) return;
            this._cfdPending = snap;
            if (this._cfdPushTimer) return;
            this._cfdPushTimer = setTimeout(() => {
                this._cfdPushTimer = null;
                const s = this._cfdPending;
                this._cfdPending = null;
                if (s) posPost(this.cfdPushUrl, { snapshot: s }).catch(() => { /* transient */ });
            }, 700);
        },

        /** Open (or focus) the customer-display window on the second screen. */
        openCustomerDisplay() {
            if (!this.displayUrl) return;
            const win = window.open(this.displayUrl, 'pos-customer-display');
            if (win) { try { win.focus(); } catch (e) {} }
        },

        /**
         * Returns 'low' / 'out' / null for the tile's stock badge. The
         * badge is purely informational — the server-side CompleteSale
         * action is the authority on whether the sale actually goes
         * through. Skipping the badge entirely in these cases:
         *   - product.track_stock === false  → it doesn't track stock
         *     (services, kits, etc.). Always sellable, no badge.
         *   - product.on_hand === null       → no stock-level row exists
         *     yet (fresh install with no receipts). We don't have signal
         *     to claim "out" — better to stay quiet than mislead.
         *
         * Low threshold = product.reorder_level (per-product setting on
         * the Product form). When the product has no reorder_level set,
         * we don't paint a `low` badge — only "out" — because we have
         * no signal for what the merchant considers "low".
         */
        stockTone(product) {
            if (!product.track_stock) return null;
            if (product.on_hand === null || product.on_hand === undefined) return null;
            const q = parseFloat(product.on_hand) || 0;
            if (q <= 0) return 'out';
            const threshold = parseFloat(product.reorder_level);
            if (threshold > 0 && q <= threshold) return 'low';
            return null;
        },

        /* ── Payment ──────────────────────────────────────────── */

        openPay() {
            if (this.cart.length === 0) return;
            // Over-stock guard: blocks the checkout flow even before the
            // pay modal opens. The per-line "Only N in stock" badge is
            // already shown in the cart; this is the hard stop so F9 +
            // the bottom button don't bypass it. The internal "Complete
            // sale" button is also gated via `canComplete`.
            if (this.hasBlockingOverStock) {
                const over = this.cart.find((l) => this.lineOverStockBlocks(l));
                this.$store.toasts?.push({
                    type:    'warning',
                    message: over
                        ? (this.labels.stock_exceeds_named || '":name" exceeds available stock — reduce qty to continue.').replace(':name', over.name)
                        : (this.labels.stock_exceeds_generic || 'Some items exceed available stock — reduce qty to continue.'),
                });
                return;
            }
            this._resetPaymentSession();                         // clear any stale QR session from the prior sale
            this.payOpen      = true;
            this.payments     = [];                              // start with no committed rows
            this.payMethodId  = this.paymentMethods[0]?.id ?? null;
            this.payTendered  = String(this.grandTotal.toFixed(2));   // seed with full grand
            this.payReference = '';
            this.$nextTick(() => {
                document.querySelector('[data-cashier-tendered]')?.focus();
                document.querySelector('[data-cashier-tendered]')?.select?.();
            });
        },

        closePay() {
            if (this.submitting) return;
            // Closing the modal (X / Escape / scrim) abandons any open
            // QR session — cancel it server-side so its link dies, then
            // halt the poll. Otherwise a pending session lingers until
            // its 15-min TTL and stays payable.
            this._resetPaymentSession();
            this.payOpen = false;
        },

        pickMethod(method) {
            // Picking another method abandons any open QR session. Kill it
            // server-side quietly and drop the pane — this is "navigate
            // away", not the explicit "Cancel" button (which instead shows
            // the dead-link state in place via cancelPaymentSession()).
            if (this.paySession || this.payStatus === 'cancelled') {
                this._cancelServerSession(this.paySession?.uuid);
                this._stopPaymentTimers();
                this.paySession   = null;
                this.payQrDataUrl = '';
                this.payCountdown = '';
                this.payStatus    = 'idle';
                this.walletBrand  = null;
            }
            this.payMethodId  = method.id;
            this.payTendered  = String(this.payRemaining.toFixed(2));
            this.payReference = '';

            // Mint a fresh `tr` (transaction reference) the moment the
            // UPI tile is picked — kept stable for re-renders so a
            // customer scanning mid-edit gets the same ref that lands
            // in their bank statement. Cleared when the modal closes.
            if (method.code === 'upi' && method.vpa) {
                this.upiTr = this._mintUpiTr();
            } else {
                this.upiTr = '';
            }

            // UPI VPA → render a `upi://pay?…` QR. Manual-confirm
            // method: customer scans + pays, cashier types the UTR
            // into the reference field, sale completes normally.
            this._refreshUpiQr();
        },

        /**
         * Mint a short, human-readable POS reference like `POS<ts><rnd>`.
         * NPCI caps `tr` at 35 alphanumeric characters; we stay well
         * under that. Deterministic prefix so reconciliation queries
         * (`LIKE 'POS%'` against bank statements) are easy.
         */
        _mintUpiTr() {
            const ts  = Date.now().toString(36).toUpperCase();        // ~8 chars
            const rnd = Math.random().toString(36).slice(2, 6).toUpperCase();  // 4 chars
            return `POS${ts}${rnd}`;
        },

        /**
         * Build the UPI deep-link QR for the currently-picked method.
         * Re-runs whenever the amount being charged changes (split-
         * tender edits, etc.) because the amount is baked into the URL.
         * Clears the QR if the picked method isn't a UPI-with-VPA.
         */
        async _refreshUpiQr() {
            const m = this.pickedMethod;
            const vpa = (m?.code === 'upi' && m?.vpa) ? String(m.vpa).trim() : '';
            if (!vpa) { this.upiQrDataUrl = ''; this.upiPayUrl = ''; return; }

            // Amount on the wire = what's being charged for THIS row
            // (split tender: the amount on this method; single tender:
            // payRemaining). 2dp is the universal UPI spec.
            const amountNum = parseFloat(this.payTendered) || this.payRemaining || 0;
            const amount    = amountNum > 0 ? amountNum.toFixed(2) : '';

            const params = new URLSearchParams();
            params.set('pa', vpa);
            if (m.payee_name) params.set('pn', String(m.payee_name));
            if (amount)        params.set('am', amount);
            params.set('cu', 'INR');
            params.set('tn', 'POS Sale');
            // Embed our transaction ref so the merchant can later
            // match a bank-statement entry back to the cart that
            // generated it. Customer's UPI app passes it through to
            // their bank as the merchant reference.
            if (this.upiTr) params.set('tr', this.upiTr);
            const url = `upi://pay?${params.toString()}`;
            this.upiPayUrl = url;

            try {
                this.upiQrDataUrl = await QRCode.toDataURL(url, {
                    width: 220,
                    margin: 1,
                    errorCorrectionLevel: 'M',
                });
            } catch (_) {
                this.upiQrDataUrl = '';
            }
        },

        get pickedMethod() {
            return this.paymentMethods.find((m) => m.id === this.payMethodId) ?? null;
        },

        get requiresReference() {
            return !!this.pickedMethod?.requires_reference;
        },

        /* ── Split tender ─────────────────────────────────────── */

        /** Sum of every payment already committed in `payments[]`. */
        get paymentsTotal() {
            return this.payments.reduce((s, p) => s + (parseFloat(p.amount) || 0), 0);
        },

        /**
         * How much is still owed after the committed payments. Never
         * negative — over-tender on a cash row only inflates that row's
         * `tendered_amount` (→ change). The current-row input on its own
         * never reduces `payRemaining`; it only does when added.
         */
        get payRemaining() {
            return Math.max(0, this.grandTotal - this.paymentsTotal);
        },

        /**
         * The "charge" for the current row — i.e. how much would land on
         * `amount` if the cashier taps Accept now. Capped at `payRemaining`
         * so a cash over-tender still only charges what's owed (surplus
         * becomes change, not a phantom payment).
         */
        get currentCharge() {
            const t = parseFloat(this.payTendered) || 0;
            return Math.min(t, this.payRemaining);
        },

        /** Change on the current row (cash over-tender). */
        get changeDue() {
            if (this.pickedMethod?.type !== 'cash') return 0;
            const t = parseFloat(this.payTendered) || 0;
            const diff = t - this.payRemaining;
            return diff > 0 ? diff : 0;
        },

        /**
         * Credit-sale mode — partial pay (or zero pay) lands on the
         * customer's outstanding balance. Only allowed when a customer
         * is attached; walk-in must fully pay.
         *
         * Triggers when the committed payments + current input still
         * leave a shortfall. Used both to show the "Goes on account"
         * banner and to enable the "Charge on credit" path.
         */
        get isCreditMode() {
            if (!this.customer) return false;
            return this.paymentsTotal + this.currentCharge < this.grandTotal;
        },

        /** Unpaid portion after the current row would be added. */
        get balanceDue() {
            const diff = this.grandTotal - this.paymentsTotal - this.currentCharge;
            return diff > 0 ? diff : 0;
        },

        /**
         * Does the current input fill the remaining due exactly (or over,
         * which collapses to exact via `currentCharge`)? Drives the
         * action button — "Complete sale" vs "Add payment".
         */
        get willCompleteCurrent() {
            return this.currentCharge >= this.payRemaining - 0.05;
        },

        /**
         * Add the row currently being entered to the committed list,
         * clear the form, leave the modal open so the cashier can pick
         * the next method. Returns `true` if it was added (i.e. valid),
         * `false` otherwise.
         */
        addCurrentPayment() {
            if (!this.canAddCurrent) return false;
            const m = this.pickedMethod;
            const charged  = this.currentCharge;
            const tendered = m?.type === 'cash'
                ? Math.max(charged, parseFloat(this.payTendered) || 0)
                : charged;
            this.payments.push({
                payment_method_id: this.payMethodId,
                method_name:       m?.name ?? '',
                method_type:       m?.type ?? '',
                amount:            charged.toFixed(4),
                tendered_amount:   tendered.toFixed(4),
                reference:         (this.payReference || '').trim() || null,
                // Gateway provenance — populated by the Stripe flow
                // when the customer pays via Checkout; null for cash /
                // manual reference. CompleteSale persists them onto the
                // sale_payments row so refund-via-gateway can find the
                // payment intent later.
                gateway_provider:  m?.provider || null,
                gateway_payment_id: this._stripePaymentId || null,
            });
            this._stripePaymentId = null;
            // Clear the form and reseed it with the new `payRemaining`.
            this.payReference = '';
            this.payTendered  = String(this.payRemaining.toFixed(2));
            this.$nextTick(() => {
                document.querySelector('[data-cashier-tendered]')?.focus();
                document.querySelector('[data-cashier-tendered]')?.select?.();
            });
            return true;
        },

        /** Drop a previously-committed payment row. */
        removeCommittedPayment(index) {
            if (this.submitting) return;
            this.payments.splice(index, 1);
            // After removal there's more due again — preload the input
            // to the new `payRemaining` so the next pick is one-tap.
            this.payTendered = String(this.payRemaining.toFixed(2));
        },

        /**
         * Cashier-side guard — the current row is valid enough to add to
         * the tally. The server-side CompleteSale + CompleteSaleRequest
         * still re-validates everything.
         */
        get canAddCurrent() {
            if (!this.payMethodId) return false;
            const charge = this.currentCharge;
            if (charge <= 0) return false;
            if (this.requiresReference && !(this.payReference || '').trim()) return false;
            return true;
        },

        get canComplete() {
            if (this.cart.length === 0) return false;
            if (this.hasBlockingOverStock) return false;
            // Already at zero-remaining with committed payments — no need
            // for any current-row input. Submit straight away.
            if (this.payments.length > 0 && this.payRemaining <= 0.05) return true;
            // Otherwise we need a valid current row.
            if (!this.canAddCurrent) {
                // Credit-mode fallback: customer attached + at least one
                // committed payment + remainder will land on the account.
                if (this.customer && this.payments.length > 0) return true;
                // Or: customer attached + zero committed + zero current
                // → pure-credit ring-up (no payment at all).
                if (this.customer && this.payments.length === 0 && (parseFloat(this.payTendered) || 0) === 0) return true;
                return false;
            }
            // Current row is valid. Either it completes the sale, or the
            // customer is attached (remainder lands on credit).
            if (this.willCompleteCurrent) return true;
            return !!this.customer;
        },

        /** Quick-tender helpers — populate `tendered` with common denominations. */
        quickTender(amount) {
            this.payTendered = String(amount.toFixed(2));
        },

        /* ── POS payment session (QR-chooser flow) ─────────────
         * Generic flow that doesn't care which gateway will end up
         * processing the charge — the customer picks that on the
         * /pay/pos/{uuid} page after scanning the QR.
         *
         *   1. startPaymentSession()    — POST /cashier/pos-sessions
         *                                 → {uuid, pay_url, expires_at}
         *   2. renderPayQr(url)         — converts pay_url to QR data URL
         *   3. _pollPaymentSession()    — GET status every 2s
         *   4. On 'paid' → addCurrentPayment + complete()
         *   5. cancelPaymentSession()   — POST cancel; "switch to cash"
         * Each provider's webhook + return handler flips the row to
         * paid; the cashier's polling sees it. Single flow, all
         * providers. */

        /**
         * Full cart snapshot for the QR session — what the customer sees
         * on the pay page. Names + numbers only, no internal IDs. Mirrors
         * the cashier's own totals block (subtotal / discount / tax /
         * grand) plus per-line tax and kit components, so the customer
         * sees exactly the breakdown the cashier sees.
         */
        _cartSummaryForSession() {
            const lines = this.cart.map((l) => {
                const price = parseFloat(l.unit_price) || 0;
                const taxable = l.tax_taxable !== false;
                const kit = Array.isArray(l.kit_items) ? l.kit_items : [];
                return {
                    name:          l.name ?? '',
                    quantity:      String(l.quantity),
                    unit_price:    price.toFixed(4),
                    line_total:    this.lineTotalDisplay(l).toFixed(4),
                    tax_rate:      (parseFloat(l.tax_rate_total) || 0).toFixed(2),
                    tax_amount:    (taxable ? this._lineTax(l) : 0).toFixed(4),
                    tax_inclusive: !!l.tax_inclusive,
                    tax_taxable:   taxable,
                    kit_items:     kit.map((k) => ({
                        name:          k.name ?? '',
                        quantity:      String(k.quantity ?? ''),
                        variant_label: k.variant_label ?? null,
                    })),
                };
            });

            return {
                lines,
                totals: {
                    subtotal:        this.subtotal.toFixed(4),
                    discount_type:   this.discount.type || null,
                    discount_value:  this.discount.type ? String(this.discount.value) : null,
                    discount_amount: this.discountAmt.toFixed(4),
                    tax_total:       this.tax.toFixed(4),
                    grand_total:     this.grandTotal.toFixed(4),
                },
            };
        },

        /**
         * Apple Pay / Google Pay buttons — both are the same Stripe
         * Checkout Session (Stripe has no separate wallet payment-method
         * type; the wallet button just surfaces automatically on the
         * customer's own device once they open the link). `brand` only
         * carries through to the cashier/CFD label — see startPaymentSession()
         * and _cfdSnapshot().
         */
        payWithWallet(brand) {
            if (!stripeMethodId) return;
            this.walletBrand = brand;
            this.startPaymentSession();
        },

        async startPaymentSession() {
            if (!posSessionCreateUrl) return;
            if (this.payRemaining <= 0) return;

            // QR pay needs the server to mint a session + a reachable public
            // URL — neither works offline. Bail with a clear toast instead of
            // letting the request fail.
            if (this.connectivity?.status === 'offline') {
                this.$store.toasts?.push({
                    type:    'warning',
                    message: this.labels.qr_payment_needs_internet || 'QR payment needs an internet connection. Take cash, or retry when you\'re back online.',
                });
                return;
            }

            this.payStatus    = 'starting';
            this.paySession   = null;
            this.payQrDataUrl = '';

            try {
                const baseCurrency = (document.querySelector('meta[name="pos-base-currency"]')?.content) || 'USD';
                const { data } = await posPost(posSessionCreateUrl, {
                    // `amount` is only a fallback estimate now — the server
                    // re-prices `items` authoritatively and charges THAT,
                    // so the gateway charge always matches what CompleteSale
                    // records. `paid_already` is any committed split-tender
                    // (cash) so the QR charges only the remainder.
                    amount:       this.payRemaining.toFixed(4),
                    currency:     baseCurrency,
                    local_uuid:   this.clientUuid,
                    items:        this._buildSaleItems(),
                    paid_already: this.paymentsTotal.toFixed(4),
                    // Cart snapshot so the customer's pay page can show
                    // the full breakdown (items, kit contents, per-line
                    // tax, discount, totals). Names + numbers only.
                    cart:         this._cartSummaryForSession(),
                    // Set only for the wallet buttons — stamps the
                    // session's allow-list to Stripe (payment_method_id) and
                    // which button was tapped (wallet_brand), so the
                    // customer's pay page renders the minimal Payment
                    // Request Button page instead of the gateway chooser.
                    ...(this.walletBrand
                        ? { payment_method_id: stripeMethodId, wallet_brand: this.walletBrand }
                        : {}),
                });
                this.paySession = data;
                this.payStatus  = 'pending';
                this._renderPayQr(data?.pay_url || '');
                this._beginPaymentPoll();
                this._beginPayCountdown();
            } catch (e) {
                this.payStatus = 'failed';
                this.$store.toasts?.push({
                    type: 'error',
                    message: e?.message || this.labels.payment_session_start_failed || 'Couldn\'t start payment session.',
                });
            }
        },

        async _renderPayQr(url) {
            if (!url) { this.payQrDataUrl = ''; return; }
            try {
                this.payQrDataUrl = await QRCode.toDataURL(url, {
                    width: 240,
                    margin: 1,
                    errorCorrectionLevel: 'M',
                });
            } catch (_) {
                this.payQrDataUrl = '';
            }
        },

        _beginPaymentPoll() {
            if (this._payPollTimer) clearInterval(this._payPollTimer);
            this._payPollTimer = setInterval(() => this._pollPaymentSession(), 2000);
        },

        _beginPayCountdown() {
            if (this._payCountdownTimer) clearInterval(this._payCountdownTimer);
            const tick = () => {
                if (!this.paySession?.expires_at) { this.payCountdown = ''; return; }
                const ms = new Date(this.paySession.expires_at).getTime() - Date.now();
                if (ms <= 0) { this.payCountdown = '0:00'; return; }
                const total = Math.floor(ms / 1000);
                const m = Math.floor(total / 60);
                const s = total % 60;
                this.payCountdown = `${m}:${s.toString().padStart(2, '0')}`;
            };
            tick();
            this._payCountdownTimer = setInterval(tick, 1000);
        },

        async _pollPaymentSession() {
            const tpl = posSessionStatusUrlTemplate;
            if (!tpl || !this.paySession?.uuid) return;
            try {
                const url = tpl.replace('__UUID__', this.paySession.uuid);
                const { data } = await posGet(url);
                this.payStatus = data?.status || 'pending';

                if (this.payStatus === 'paid') {
                    this._stopPaymentTimers();
                    // Stamp the gateway info on the payment row that
                    // will be created when the sale completes.
                    this._stripePaymentId = data?.gateway_payment_id || null;
                    this.payMethodId      = data?.payment_method_id  || this.payMethodId;
                    this.payReference     = data?.gateway_payment_id || '';
                    // Record the QR payment as the amount the gateway
                    // actually charged — the SERVER-priced session amount,
                    // not the JS estimate. This keeps the recorded payment
                    // equal to both the charge and CompleteSale's grand
                    // total (the whole point of the server-authoritative
                    // fix). Falls back to the JS remainder if the session
                    // somehow lacks an amount.
                    this.payTendered = String(this.paySession?.amount ?? this.payRemaining.toFixed(2));
                    await this.complete();
                } else if (this.payStatus === 'failed' || this.payStatus === 'expired' || this.payStatus === 'cancelled') {
                    this._stopPaymentTimers();
                    this.$store.toasts?.push({
                        type: 'warning',
                        message: this.payStatus === 'expired'
                            ? (this.labels.payment_session_expired || 'Payment session expired.')
                            : (this.payStatus === 'cancelled' ? (this.labels.payment_cancelled || 'Payment cancelled.') : (this.labels.payment_failed || 'Payment failed.')),
                    });
                }
            } catch (_) { /* transient — next tick retries */ }
        },

        /**
         * "Cancel payment" — kill the session server-side (so the
         * customer's open page flips to "expired" on its next poll) and
         * surface the dead-link state IN PLACE: the QR dims, the status
         * chip reads "Session cancelled", and a "Generate new QR" button
         * appears. We deliberately stay on the QR pane (keep `paySession`)
         * rather than jumping to cash — the cashier can regenerate, or
         * pick any other method from the left rail.
         */
        async cancelPaymentSession() {
            const uuid = this.paySession?.uuid;
            this._stopPaymentTimers();
            this.payCountdown = '';
            // Flip to a terminal status so the dead-overlay + regenerate
            // button render (gated on expired/cancelled/failed). Keep
            // `paySession` set so the QR pane stays visible.
            this.payStatus = 'cancelled';
            this._cancelServerSession(uuid);
        },

        _stopPaymentTimers() {
            if (this._payPollTimer)      { clearInterval(this._payPollTimer);      this._payPollTimer = null; }
            if (this._payCountdownTimer) { clearInterval(this._payCountdownTimer); this._payCountdownTimer = null; }
        },

        /**
         * Wipe every QR-session field back to a clean slate. Called when
         * the pay modal (re)opens so a previous sale's `paid` session
         * doesn't bleed into the next ring-up — otherwise the QR tile
         * shows as already-active and the right pane renders the stale
         * "Paid" QR instead of letting the cashier start a fresh
         * session ("new sale won't generate a link" bug).
         */
        _resetPaymentSession() {
            // If a live (non-paid) session is still open server-side,
            // kill it so its QR link can't be paid after the cashier
            // moves on. Fire-and-forget; the server no-ops if the
            // session is already terminal (paid/expired/cancelled), so
            // this is safe to call after a completed QR sale too.
            this._cancelServerSession(this.paySession?.uuid);
            this._stopPaymentTimers();
            this.payStatus    = 'idle';
            this.paySession   = null;
            this.payQrDataUrl = '';
            this.payCountdown = '';
            this._stripePaymentId = null;
            this.walletBrand  = null;
        },

        /**
         * Mark a server-side QR session cancelled. Fire-and-forget — we
         * never block the cashier UI on it. The cancel endpoint is a
         * no-op on an already-terminal session, so over-calling is safe.
         */
        _cancelServerSession(uuid) {
            const tpl = posSessionCancelUrlTemplate;
            if (!tpl || !uuid) return;
            try { posPost(tpl.replace('__UUID__', uuid), {}); } catch (_) {}
        },

        /** Copy the pay URL to clipboard — for the "share this link" affordance. */
        async copyPayUrl() {
            const url = this.paySession?.pay_url;
            if (!url) return;
            try {
                await navigator.clipboard.writeText(url);
                this.$store.toasts?.push({ type: 'success', message: this.labels.link_copied || 'Link copied.' });
            } catch (_) {
                this.$store.toasts?.push({ type: 'warning', message: this.labels.link_copy_failed || 'Couldn\'t copy — long-press the link to copy.' });
            }
        },

        /**
         * Cash quick-tender chips. Built off the still-remaining amount,
         * not the grand total — so on the SECOND payment of a split the
         * suggestions still make sense.
         */
        get quickTenderAmounts() {
            const t = this.payRemaining;
            if (t <= 0) return [];
            const next = (incr) => Math.ceil(t / incr) * incr;
            const candidates = [
                t,            // exact
                next(50),
                next(100),
                next(500),
                next(1000),
            ];
            // Drop duplicates + values below the remaining.
            return [...new Set(candidates)].filter((v) => v >= t).slice(0, 5);
        },

        async complete() {
            if (this.submitting || !this.canComplete) return;
            // If the current row is valid AND the cashier hasn't already
            // tapped "Add payment", fold it in implicitly so single-tender
            // (the 90% case) stays one click. Track whether we did so we
            // can pop it back off on failure (so the cashier doesn't end
            // up with a phantom committed row after a 422).
            let foldedIn = false;
            if (this.canAddCurrent) {
                foldedIn = this.addCurrentPayment();
            }
            this.submitting = true;

            const payload = {
                store_id:    storeId,
                customer_id: this.customer?.id ?? null,
                local_uuid:  this.clientUuid,
                sale_date:   new Date().toISOString().slice(0, 10),
                // Signed manager-approval token for an over-threshold
                // discount (Slice 2). Null when none was needed.
                discount_approval: this.discountApproval?.token ?? null,
                // Order-level discount audit (Slice 3).
                discount_type:            this.discount.type ?? null,
                discount_value:           this.discount.type ? this.discount.value : null,
                discount_reason:          this.discount.reason ?? null,
                discount_reason_category: this.discount.reason_category ?? null,
                items: this._buildSaleItems(),
                // Send the full split-tender list, stripping the display-
                // only fields (method_name / method_type) the server
                // doesn't expect. A pure-credit sale (walk-in is blocked
                // by canComplete) sends an empty array.
                payments: this.payments.map((p) => ({
                    payment_method_id:  p.payment_method_id,
                    amount:             p.amount,
                    tendered_amount:    p.tendered_amount,
                    reference:          p.reference,
                    gateway_provider:   p.gateway_provider   ?? null,
                    gateway_payment_id: p.gateway_payment_id ?? null,
                })),
            };

            // Captured now (before `this.payments` gets wiped below) so the
            // auto-print/auto-drawer step after completion knows whether
            // any tender on this sale is configured to kick the drawer —
            // e.g. cash, not a card/wallet payment.
            const shouldOpenDrawer = payload.payments.some(
                (p) => this.paymentMethods.find((m) => m.id === p.payment_method_id)?.opens_cash_drawer,
            );

            // ── Offline path (Slice 2) ─────────────────────────
            // If we know we're offline, queue the sale to IndexedDB and
            // paint the success overlay with a provisional OFF-prefixed
            // number — the sync engine will POST it as soon as
            // connectivity returns. Idempotency is handled server-side
            // by `CompleteSale` deduping on `sales.local_uuid`, so even
            // if the queue retries we don't double-post.
            if (this.connectivity.status === 'offline') {
                try {
                    // Snapshot the cart/payments/totals for the receipt
                    // BEFORE `enqueueSale` + the reset below wipe them —
                    // this is what the client-side renderer prints so the
                    // cashier can hand over a receipt with no server round-
                    // trip (offline-sync doc §13).
                    const offlineNumber = 'OFF-' + (this.clientUuid || '').slice(0, 8).toUpperCase();
                    const receiptSnapshot = this.buildOfflineReceiptSnapshot(offlineNumber);

                    await enqueueSale(payload);
                    // Build a fake "successSale" so the same success
                    // overlay renders without branching the markup. The
                    // OFF-XXXXXXXX prefix mirrors docs §10.3; the real
                    // sale number is assigned on sync (Slice 4 surfaces
                    // it via the sync log).
                    this.successSale = {
                        id:              null,
                        number:          offlineNumber,
                        grand_total:     receiptSnapshot.totals.grand_total,
                        change_returned: receiptSnapshot.totals.change_returned,
                        offline:         true,
                        // Carried so the overlay's Print/View can render the
                        // receipt without re-reading the (now-cleared) cart.
                        receipt:         receiptSnapshot,
                    };
                    this.successOpen = true;
                    this.payOpen     = false;
                    this.cart = [];
                    this.customer = null;
                    this.discount = { type: null, value: 0 };
                this.discountApproval = null; this._pendingApproval = null;
                    this.payments = [];
                    this.payTendered = '';
                    this.payReference = '';
                    this._resetClientUuid();

                    // Fire-and-forget drain attempt — usually a no-op
                    // (we know we're offline) but harmless if we just
                    // came back online between offline-check and now.
                    drainQueue();

                    this.$store.toasts?.push({
                        type:    'success',
                        message: this.labels.sale_queued_offline || 'Sale queued — will sync when online.',
                        duration: 4000,
                    });

                    // Cash was physically handled regardless of sync state —
                    // the drawer still opens now. Printing waits for the
                    // real sale id from sync (see printSaleReceipt() guard).
                    this._autoOpenDrawerIfNeeded(shouldOpenDrawer);
                } catch (e) {
                    this.$store.toasts?.push({
                        type:    'error',
                        message: (this.labels.sale_queue_failed || 'Couldn\'t queue the sale: ') + (e?.message || (this.labels.unknown_error || 'unknown error')),
                        duration: 6000,
                    });
                    if (foldedIn && this.payments.length > 0) {
                        this.payments.pop();
                        this.payTendered = String(this.payRemaining.toFixed(2));
                    }
                } finally {
                    this.submitting = false;
                }
                return;
            }

            // ── Online path ───────────────────────────────────
            try {
                const { data } = await posPost(completeUrl, payload);
                this.successSale = data?.sale ?? null;
                this.successOpen = true;
                this.payOpen = false;

                this.cart = [];
                this.customer = null;
                this.discount = { type: null, value: 0 };
                this.discountApproval = null; this._pendingApproval = null;
                this.payments = [];
                this.payTendered = '';
                this.payReference = '';
                this._resetClientUuid();

                // Auto-print + auto-drawer — no button press needed. Both
                // go through the same USB interface on WebUSB terminals, so
                // they must NOT run concurrently: firing both at once races
                // two claimInterface() calls on one device, and the loser
                // throws and silently falls back to browser-print (the
                // print dialog reappearing despite the drawer opening fine
                // was exactly this race). Drawer kick first (short, cheap),
                // then print once it's settled.
                this._autoOpenDrawerIfNeeded(shouldOpenDrawer).finally(() => {
                    this.printSaleReceipt(this.successSale);
                });

                // No auto-close — the cashier needs time to read the
                // sale number, hit View/Print receipt, or click "New
                // sale" to start the next ring-up. Escape or scrim-
                // click dismisses; the receipt links open in a new tab.
            } catch (e) {
                // The interceptor in lib/http.js auto-toasts 5xx, but
                // 422 (which is what `InsufficientStock` and the rest
                // of CompleteSale's RuntimeException paths return) is
                // delivered as `{message, errors: {_action: [...]}}`
                // and the page is responsible for surfacing it. Show
                // the friendliest available text.
                const msg = e?.message
                    || e?.errors?._action?.[0]
                    || this.labels.complete_sale_failed || 'Something went wrong completing the sale.';
                this.$store.toasts?.push({
                    type:     'error',
                    message:  msg,
                    duration: 6000,
                });
                // Roll back the implicit fold-in so the cashier can edit
                // the current row and retry — otherwise the failed row
                // would still appear in the committed list.
                if (foldedIn && this.payments.length > 0) {
                    this.payments.pop();
                    this.payTendered = String(this.payRemaining.toFixed(2));
                }
            } finally {
                this.submitting = false;
            }
        },

        /**
         * Build the receipt URL for the just-completed sale.
         * `autoprint = true` appends `?print=1` which makes the receipt
         * page fire `window.print()` on load — used by the success
         * overlay's "Print receipt" button. Plain `false` is the
         * "View receipt" link which renders the same page without auto-
         * triggering the print dialog.
         */
        receiptUrl(autoprint = false) {
            if (!this.successSale?.id || !receiptUrlTemplate) return '#';
            const url = receiptUrlTemplate.replace('__ID__', this.successSale.id);
            return autoprint ? url + '?print=1' : url;
        },

        /**
         * Kick the cash drawer right after a sale completes — only when
         * the terminal is wired for WebUSB (browser-print mode has no raw
         * byte access, so there's nothing to send) and only when the
         * tender used is configured to open the drawer (cash, not a
         * card/wallet payment — see `shouldOpenDrawer` in complete()).
         * Best-effort: a printer that's unplugged or in browser-print mode
         * just means the cashier opens the drawer by hand, same as the
         * existing "Open drawer (no sale)" flow.
         */
        async _autoOpenDrawerIfNeeded(shouldOpenDrawer) {
            if (!shouldOpenDrawer) return;
            const cfg = terminalPrinterConfig();
            if (cfg.mode !== 'webusb') return;
            try {
                await openDrawer(cfg);
            } catch (e) {
                if (!(e instanceof DrawerKickUnavailable)) {
                    console.warn('[cashier] auto drawer kick failed', e);
                }
            }
        },

        /**
         * Print the completed sale's receipt through the hardware print
         * bridge (WebUSB → browser-print). On a hard failure the receipt is
         * parked in the failed-print queue so it isn't lost, and the
         * outcome is logged. Online only — an offline sale has no server
         * id yet, so the success overlay hides this until it syncs.
         */
        async printSaleReceipt(sale) {
            sale = sale || this.successSale;
            if (!sale?.id || !printPayloadUrlTemplate || this.printingReceipt) return;

            this.printingReceipt = true;
            let payload = null;
            try {
                const url = printPayloadUrlTemplate.replace('__ID__', sale.id);
                const { data } = await posGet(url);
                payload = data;
            } catch (e) {
                this.$store.toasts?.push({ type: 'error', message: this.labels.receipt_load_failed || 'Could not load the receipt to print.' });
                this.printingReceipt = false;
                return;
            }

            let mode = 'browser_print';
            let ok = true;
            let queued = false;
            let errorMessage = null;
            try {
                mode = await printReceipt(payload, terminalPrinterConfig());
            } catch (e) {
                ok = false;
                errorMessage = e?.message ?? 'Print failed';
                try {
                    await enqueueFailedPrint({
                        reference_type: 'Sale',
                        reference_id:   sale.id,
                        label:          sale.number,
                        payload,
                        error:          errorMessage,
                    });
                    queued = true;
                    this.$store.toasts?.push({ type: 'error', message: this.labels.print_failed_queued || 'Print failed — added to the print queue to retry.' });
                } catch (_) {
                    this.$store.toasts?.push({ type: 'error', message: this.labels.print_failed_check || 'Print failed. Check the printer.' });
                }
            }

            if (printLogUrl) {
                try {
                    await posPost(printLogUrl, {
                        reference_type: 'Sale',
                        reference_id:   sale.id,
                        printer_type:   'receipt',
                        mode,
                        status:         ok ? 'success' : (queued ? 'queued' : 'failed'),
                        error_message:  errorMessage,
                        bytes_size:     payload?.escpos_bytes ? Math.floor((payload.escpos_bytes.length * 3) / 4) : null,
                    });
                } catch (_) { /* logging is best-effort */ }
            }

            this.printingReceipt = false;
        },

        /**
         * Same shape as printSaleReceipt(), pointed at a refund instead
         * of a sale — used by both refund flows' success paths (invoice-
         * based and blind). `refund.id` is always present (both
         * RecordSaleReturn and RecordBlindReturn return a real
         * persisted SaleReturn, never an offline-queued placeholder —
         * refunds aren't queued offline).
         */
        async printRefundReceipt(refund) {
            if (!refund?.id || !refundPrintPayloadUrlTemplate || this.printingReceipt) return;

            this.printingReceipt = true;
            let payload = null;
            try {
                const url = refundPrintPayloadUrlTemplate.replace('__ID__', refund.id);
                const { data } = await posGet(url);
                payload = data;
            } catch (e) {
                this.$store.toasts?.push({ type: 'error', message: this.labels.refund_receipt_load_failed || 'Could not load the refund receipt to print.' });
                this.printingReceipt = false;
                return;
            }

            let mode = 'browser_print';
            let ok = true;
            let queued = false;
            let errorMessage = null;
            try {
                mode = await printReceipt(payload, terminalPrinterConfig());
            } catch (e) {
                ok = false;
                errorMessage = e?.message ?? 'Print failed';
                try {
                    await enqueueFailedPrint({
                        reference_type: 'SaleReturn',
                        reference_id:   refund.id,
                        label:          refund.number,
                        payload,
                        error:          errorMessage,
                    });
                    queued = true;
                    this.$store.toasts?.push({ type: 'error', message: this.labels.print_failed_queued || 'Print failed — added to the print queue to retry.' });
                } catch (_) {
                    this.$store.toasts?.push({ type: 'error', message: this.labels.print_failed_check || 'Print failed. Check the printer.' });
                }
            }

            if (printLogUrl) {
                try {
                    await posPost(printLogUrl, {
                        reference_type: 'SaleReturn',
                        reference_id:   refund.id,
                        printer_type:   'receipt',
                        mode,
                        status:         ok ? 'success' : (queued ? 'queued' : 'failed'),
                        error_message:  errorMessage,
                        bytes_size:     payload?.escpos_bytes ? Math.floor((payload.escpos_bytes.length * 3) / 4) : null,
                    });
                } catch (_) { /* logging is best-effort */ }
            }

            this.printingReceipt = false;
        },

        /**
         * The customer's "corrected" copy of the original invoice after a
         * refund — same sale number, refunded lines dropped/reduced (see
         * BuildPostRefundSaleCopy). Fired alongside printRefundReceipt()
         * so the till produces both the refund slip and an up-to-date
         * copy of the original for the customer's records. Silently
         * no-ops for a blind refund (no `sale_id` — there's no original
         * invoice to correct).
         */
        async printCorrectedSaleCopy(saleId) {
            if (!saleId || !correctedCopyPrintPayloadUrlTemplate || this.printingReceipt) return;

            this.printingReceipt = true;
            let payload = null;
            try {
                const url = correctedCopyPrintPayloadUrlTemplate.replace('__ID__', saleId);
                const { data } = await posGet(url);
                payload = data;
            } catch (e) {
                this.$store.toasts?.push({ type: 'error', message: this.labels.corrected_copy_load_failed || 'Could not load the corrected invoice copy to print.' });
                this.printingReceipt = false;
                return;
            }

            // A fully-refunded sale has nothing left to show — the refund
            // slip already documents that. Skip printing a blank invoice.
            if (payload && payload.has_remaining_items === false) {
                this.printingReceipt = false;
                return;
            }

            let mode = 'browser_print';
            let ok = true;
            let queued = false;
            let errorMessage = null;
            try {
                mode = await printReceipt(payload, terminalPrinterConfig());
            } catch (e) {
                ok = false;
                errorMessage = e?.message ?? 'Print failed';
                try {
                    await enqueueFailedPrint({
                        reference_type: 'Sale',
                        reference_id:   saleId,
                        label:          'corrected-copy',
                        payload,
                        error:          errorMessage,
                    });
                    queued = true;
                    this.$store.toasts?.push({ type: 'error', message: this.labels.print_failed_queued || 'Print failed — added to the print queue to retry.' });
                } catch (_) {
                    this.$store.toasts?.push({ type: 'error', message: this.labels.print_failed_check || 'Print failed. Check the printer.' });
                }
            }

            if (printLogUrl) {
                try {
                    await posPost(printLogUrl, {
                        reference_type: 'Sale',
                        reference_id:   saleId,
                        printer_type:   'receipt',
                        mode,
                        status:         ok ? 'success' : (queued ? 'queued' : 'failed'),
                        error_message:  errorMessage,
                        bytes_size:     payload?.escpos_bytes ? Math.floor((payload.escpos_bytes.length * 3) / 4) : null,
                    });
                } catch (_) { /* logging is best-effort */ }
            }

            this.printingReceipt = false;
        },

        /**
         * Capture everything the offline receipt renderer needs from the
         * live cart/payments/totals, keyed to the provisional OFF- number.
         * Called from the offline branch of complete() BEFORE the reset
         * clears the cart. Totals mirror the sale row CompleteSale will
         * eventually write, so the provisional receipt matches the online
         * one bar the number: the receipt "Subtotal" is the gross,
         * pre-discount, tax-inclusive figure (grand + discount), with tax
         * shown as "already included".
         */
        buildOfflineReceiptSnapshot(number) {
            const items = this.cart.map((l) => {
                const taxable = l.tax_taxable !== false;
                const kit = Array.isArray(l.kit_items) ? l.kit_items : [];
                return {
                    name:          l.name ?? '',
                    variant_label: l.variant_label ?? null,
                    sku:           l.sku ?? null,
                    hsn:           l.hsn ?? null,
                    quantity:      String(l.quantity),
                    unit_price:    parseFloat(l.unit_price) || 0,
                    line_total:    this.lineTotalDisplay(l),
                    tax_amount:    taxable ? this._lineTax(l) : 0,
                    tax_inclusive: !!l.tax_inclusive,
                    batch_number:  l.batch_number ?? null,
                    batch_expiry:  l.batch_expiry ?? null,
                    note:          l.note || null,
                    kit_items:     kit.map((k) => ({
                        name:          k.name ?? '',
                        quantity:      k.quantity ?? '',
                        variant_label: k.variant_label ?? null,
                    })),
                };
            });

            const grand = this.grandTotal;
            const discount = this.discountAmt;
            const tendered = this.payments.reduce(
                (s, p) => s + (parseFloat(p.tendered_amount) || parseFloat(p.amount) || 0), 0,
            );

            return {
                number,
                datetime: new Date().toLocaleString(),
                cashier_name: this.receiptConfig?.cashier_name ?? null,
                customer: this.customer
                    ? { name: this.customer.name, phone: this.customer.phone ?? null }
                    : null,
                items,
                payments: this.payments.map((p) => ({
                    name:            p.method_name || '',
                    amount:          parseFloat(p.amount) || 0,
                    tendered_amount: parseFloat(p.tendered_amount) || 0,
                })),
                totals: {
                    // Receipt subtotal = gross, pre-discount, tax-inclusive.
                    gross_subtotal:  (grand + discount).toFixed(4),
                    discount_total:  discount.toFixed(4),
                    tax_total:       this.tax.toFixed(4),
                    grand_total:     grand.toFixed(4),
                    change_returned: Math.max(0, tendered - grand).toFixed(4),
                },
            };
        },

        /**
         * Print the just-completed OFFLINE sale's receipt, composed entirely
         * client-side (offline-sync doc §13) and pushed through the browser-
         * print path of the hardware bridge. No server id exists yet, so the
         * WebUSB/ESC/POS + print-log paths (which need one) don't apply here.
         */
        async printOfflineReceipt() {
            const snap = this.successSale?.receipt;
            if (!snap || this.printingReceipt) return;
            this.printingReceipt = true;
            try {
                const html = renderOfflineReceiptHtml(snap, this.receiptConfig, (v) => this.money(v));
                await printHtml(html);
            } catch (e) {
                this.$store.toasts?.push({
                    type:    'error',
                    message: this.labels.offline_print_failed || 'Couldn\'t print the receipt. Check the printer, or view it and print from there.',
                });
            } finally {
                this.printingReceipt = false;
            }
        },

        /**
         * Open the offline receipt in a new tab so the cashier can show it
         * on screen or print/save-PDF from the browser — the offline
         * counterpart to the online "View receipt" link.
         */
        viewOfflineReceipt() {
            const snap = this.successSale?.receipt;
            if (!snap) return;
            const html = renderOfflineReceiptHtml(snap, this.receiptConfig, (v) => this.money(v));
            const w = window.open('', '_blank');
            if (!w) {
                this.$store.toasts?.push({
                    type:    'warning',
                    message: this.labels.popup_blocked || 'Allow pop-ups to view the receipt, or use Print instead.',
                });
                return;
            }
            w.document.open();
            w.document.write(html);
            w.document.close();
        },
    };
}
