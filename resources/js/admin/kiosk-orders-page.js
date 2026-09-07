import { composeDataTableServer } from './data-table-server.js';
import { posGet, posPost } from '../lib/http.js';
import QRCode from 'qrcode';

/**
 * Kiosk orders queue (Sales → Kiosk orders).
 *
 * Payment happens in a panel ON this page — staff never bounce to the cashier
 * and back for every order. The panel is a small cashier: the counter can drop
 * a line or change a quantity, then take the money with any tender, split
 * across several, or have the customer scan a QR and pay on their phone.
 *
 * Prices are never sent from here. The server rebuilds the cart from the
 * order's own stored items and applies only the quantity edits, then hands it
 * to CompleteSale — the same action a cashier ring-up uses.
 */
export function kioskOrdersPage({
    endpoint = null,
    perPage = 25,
    total = 0,
    page = 1,
    totalPages = 1,
    paymentMethods = [],
    hasGateway = false,
    sessionCreateUrl = null,
    sessionStatusUrl = null,
    sessionCancelUrl = null,
    currency = 'USD',
    // Which tab opens first. The server picks: land staff on whichever queue
    // actually has work in it, rather than always on an empty "Pending".
    initialTab = 'pending',
    // Paid orders awaiting handover, keyed by sale id. Pre-rendered so the
    // detail modal opens instantly — the cashier has a customer at the counter.
    collectOrders = {},
    // Copy for the unverified-UPI-claim confirmation. Passed in rather than
    // hardcoded so it goes through __() like every other user-facing string.
    claimConfirmTitle = '',
    claimConfirmBody = '',
    claimConfirmLabel = '',
} = {}) {
    return composeDataTableServer(
        { endpoint, initialPageSize: perPage },
        {
            paymentMethods,
            hasGateway,
            currency,

            /* ── Tabs ────────────────────────────────────────────────
               Two queues, two different jobs: orders awaiting payment, and
               paid orders awaiting handover. Stacked one under the other,
               the second was easy to scroll past entirely. */
            tab: initialTab,

            /* ── Handover detail modal ───────────────────────────────
               Read-only: the sale is already paid, so there is nothing to
               edit. It exists so the cashier can check the bag against the
               lines without navigating away from the queue. */
            collectOrders,
            collect: null,      // the order currently shown, or null

            openCollect(id) { this.collect = this.collectOrders[id] ?? null; },
            closeCollect()  { this.collect = null; },

            /* ── Panel state ─────────────────────────────────────── */
            order: null,        // { id, code, note, customer, payUrl, lines: [...] }
            lines: [],          // editable copy
            payments: [],       // committed split-tender rows
            methodId: '',       // the tender currently being entered
            tendered: '',
            reference: '',
            submitting: false,
            error: '',

            /* ── UPI VPA — manual-confirm, not a gateway. We render a
                 `upi://pay?…` QR; the customer pays in their own app and the
                 counter types the UTR into the reference field. ─────── */
            upiQrDataUrl: '',
            upiPayUrl: '',
            upiTr: '',          // our txn ref (POS-…), stable across re-renders

            /* ── QR / gateway ───────────────────────────────────── */
            qrOpen: false,
            qrStatus: 'idle',   // idle | starting | waiting | paid | failed
            qrImage: '',
            _qrUuid: null,
            _qrPoll: null,

            init() {
                this.initDataTableServer({ total, page, totalPages, perPage });

                // The amount is baked into the UPI deep-link, so the QR has to
                // be redrawn whenever the tender, the cart, or the method moves.
                this.$watch('methodId', () => this._onMethodChange());
                this.$watch('tendered', () => this._refreshUpiQr());
                this.$watch('lines',    () => this._refreshUpiQr());
                this.$watch('payments', () => this._refreshUpiQr());
            },

            /** Only the pending queue is server-paginated; the mixin manages its
             *  `<tbody>`. The collect queue below is a separate static list. */
            _dtContainer() {
                return this._dtRoot?.querySelector('[data-dt-rows="table"]') ?? null;
            },

            /* ── Derived totals (display only; the server re-prices) ──
               Tax is proportional to quantity, so scaling each stored line by
               its qty ratio reproduces the server's figures exactly. */
            _scale(l) { return l.qty / l.origQty; },

            get subtotal() { return this.lines.reduce((s, l) => s + l.lineSubtotal * this._scale(l), 0); },
            get tax()      { return this.lines.reduce((s, l) => s + l.taxAmount    * this._scale(l), 0); },
            get total()    { return this.subtotal + this.tax; },

            get paid()      { return this.payments.reduce((s, p) => s + parseFloat(p.amount), 0); },
            get remaining() { return Math.max(0, this.total - this.paid); },

            get method() { return this.paymentMethods.find((m) => String(m.id) === String(this.methodId)) || null; },
            get isCash()         { return this.method?.type === 'cash'; },
            get isUpi()          { return this.method?.code === 'upi' && !!this.method?.vpa; },
            get needsReference() { return !!this.method?.requires_reference; },

            /** What the current tender row would contribute — never more than
             *  what's left to pay. */
            get currentAmount() {
                if (!this.isCash) return this.remaining;
                const given = parseFloat(this.tendered);
                if (!Number.isFinite(given)) return 0;
                return Math.min(given, this.remaining);
            },

            get change() {
                if (!this.isCash) return 0;
                const given = parseFloat(this.tendered);
                if (!Number.isFinite(given)) return 0;
                return Math.max(0, given - this.remaining);
            },

            get canAddPayment() {
                if (!this.methodId || this.remaining <= 0.0001) return false;
                if (this.needsReference && !this.reference.trim()) return false;
                return this.currentAmount > 0.0001;
            },

            /** Ready to charge once the money is fully covered. */
            get canConfirm() {
                if (this.submitting || !this.lines.length) return false;
                if (this.payments.length && this.remaining <= 0.0001) return true;
                // Single-tender fast path: fold the current row in on confirm.
                return this.canAddPayment && this.currentAmount + 0.0001 >= this.remaining;
            },

            /* ── Open / close ───────────────────────────────────── */
            openPay(order) {
                this.order    = order;
                this._claimAcknowledged = false;   // each order is checked on its own
                this.lines    = order.lines.map((l) => ({ ...l, qty: l.origQty }));
                this.payments = [];
                this.methodId = this.paymentMethods.length === 1 ? String(this.paymentMethods[0].id) : '';
                this.tendered = '';
                this.reference = '';
                this.error = '';
                this.submitting = false;
                this._resetQr();
                this._onMethodChange();     // mint a UPI ref + draw the QR if UPI is preselected
            },

            closePay() {
                this._cancelQr();
                this.upiQrDataUrl = '';
                this.upiPayUrl = '';
                this.upiTr = '';
                this.order = null;
                this.submitting = false;
                this._claimAcknowledged = false;
            },

            /* ── Line editing ───────────────────────────────────── */
            inc(line) { line.qty += 1; this._clampPayments(); },
            dec(line) { if (line.qty > 1) { line.qty -= 1; this._clampPayments(); } },
            removeLine(line) {
                this.lines = this.lines.filter((l) => l.id !== line.id);
                this._clampPayments();
            },

            /** Editing the cart after a tender was added can over-pay it —
             *  drop committed rows until they fit again. */
            _clampPayments() {
                while (this.payments.length && this.paid > this.total + 0.0001) {
                    this.payments.pop();
                }
            },

            /* ── Split tender ───────────────────────────────────── */
            addPayment() {
                if (!this.canAddPayment) return;
                const m = this.method;
                this.payments.push({
                    payment_method_id: Number(m.id),
                    method_name:       m.name,
                    amount:            this.currentAmount.toFixed(2),
                    tendered_amount:   this.isCash ? parseFloat(this.tendered).toFixed(2) : null,
                    reference:         this.reference.trim() || null,
                });
                this.tendered  = '';
                this.reference = '';
                // Next tender on the same method gets its own reference.
                if (this.isUpi) this.upiTr = this._mintUpiTr();
            },

            dropPayment(i) { this.payments.splice(i, 1); },

            /* ── UPI deep-link QR ───────────────────────────────── */

            /** A fresh txn ref per UPI tender, so the merchant can match a
             *  bank-statement entry back to the order that produced it. */
            _onMethodChange() {
                this.upiTr = this.isUpi ? this._mintUpiTr() : '';
                this._refreshUpiQr();
            },

            _mintUpiTr() {
                const ts  = Date.now().toString(36).toUpperCase();
                const rnd = Math.random().toString(36).slice(2, 6).toUpperCase();
                return `POS${ts}${rnd}`;
            },

            /** Build (or clear) the `upi://pay?…` QR for the picked method. The
             *  amount rides in the URL, so this re-runs on every amount change. */
            async _refreshUpiQr() {
                if (!this.isUpi) { this.upiQrDataUrl = ''; this.upiPayUrl = ''; return; }

                const amountNum = this.currentAmount;
                const amount    = amountNum > 0 ? amountNum.toFixed(2) : '';

                const params = new URLSearchParams();
                params.set('pa', String(this.method.vpa).trim());
                if (this.method.payee_name) params.set('pn', String(this.method.payee_name));
                if (amount) params.set('am', amount);
                params.set('cu', 'INR');
                params.set('tn', 'POS Sale');
                if (this.upiTr) params.set('tr', this.upiTr);

                const url = `upi://pay?${params.toString()}`;
                this.upiPayUrl = url;

                try {
                    this.upiQrDataUrl = await QRCode.toDataURL(url, { width: 220, margin: 1, errorCorrectionLevel: 'M' });
                } catch (_) {
                    this.upiQrDataUrl = '';
                }
            },

            /* ── QR / gateway ───────────────────────────────────── */
            async startQr() {
                if (!sessionCreateUrl || this.remaining <= 0) return;
                this.qrOpen = true;
                this.qrStatus = 'starting';
                this.qrImage = '';
                try {
                    const { data } = await posPost(sessionCreateUrl, {
                        amount:     this.remaining.toFixed(2),
                        currency:   this.currency,
                        local_uuid: this._uuid(),
                        // Let the server re-price authoritatively from the lines.
                        items: this.lines.map((l) => ({
                            product_id: l.productId,
                            quantity:   String(l.qty),
                            unit_price: String(l.unitPrice),
                        })),
                    });
                    this._qrUuid  = data.uuid;
                    this.qrImage  = await this._qr(data.pay_url);
                    this.qrStatus = 'waiting';
                    this._pollQr();
                } catch (e) {
                    this.qrStatus = 'failed';
                    this.error = e?.message || 'Couldn\'t start the QR payment.';
                }
            },

            _pollQr() {
                clearInterval(this._qrPoll);
                const url = (sessionStatusUrl || '').replace('__UUID__', this._qrUuid || '');
                if (!url) return;
                this._qrPoll = setInterval(async () => {
                    try {
                        const { data } = await posGet(url);
                        const s = data?.status || 'pending';
                        if (s === 'paid') {
                            clearInterval(this._qrPoll);
                            this.qrStatus = 'paid';
                            // Record it as a committed tender, then charge.
                            this.payments.push({
                                payment_method_id: Number(data.payment_method_id),
                                method_name:       'QR',
                                amount:            this.remaining.toFixed(2),
                                tendered_amount:   null,
                                reference:         data.gateway_payment_id ?? null,
                                gateway_payment_id: data.gateway_payment_id ?? null,
                                gateway_status:    'paid',
                            });
                            this.qrOpen = false;
                            await this.confirm();
                        } else if (['failed', 'expired', 'cancelled'].includes(s)) {
                            clearInterval(this._qrPoll);
                            this.qrStatus = 'failed';
                            this.error = 'The QR payment didn\'t complete.';
                        }
                    } catch (_) { /* transient — retry next tick */ }
                }, 2000);
            },

            cancelQr() { this._cancelQr(); this.qrOpen = false; },

            _cancelQr() {
                clearInterval(this._qrPoll);
                this._qrPoll = null;
                const uuid = this._qrUuid;
                this._resetQr();
                if (uuid && sessionCancelUrl) {
                    posPost(sessionCancelUrl.replace('__UUID__', uuid), {}).catch(() => {});
                }
            },

            _resetQr() {
                clearInterval(this._qrPoll);
                this._qrPoll = null;
                this._qrUuid = null;
                this.qrOpen = false;
                this.qrStatus = 'idle';
                this.qrImage = '';
            },

            async _qr(url) {
                try { return await QRCode.toDataURL(url, { width: 240, margin: 1, errorCorrectionLevel: 'M' }); }
                catch (_) { return ''; }
            },

            /* ── Unverified UPI claim ────────────────────────────────
               `_claimAcknowledged` resets with the panel (see closePay), so
               every order demands its own check — acknowledging K001 must never
               carry over to K002. */
            _claimAcknowledged: false,

            _promptClaimCheck() {
                const c = this.order.claim;
                const message = claimConfirmBody
                    .replace(':amount', c.amount)
                    .replace(':reference', c.reference || '—');

                this.$store.confirm?.show({
                    title:        claimConfirmTitle.replace(':method', c.method),
                    message,
                    intent:       'warning',
                    confirmLabel: claimConfirmLabel,
                    onConfirm: () => {
                        this._claimAcknowledged = true;
                        this.confirm();
                    },
                });
            },

            /* ── Charge ─────────────────────────────────────────── */
            async confirm() {
                if (this.submitting) return;
                // Single-tender fast path — fold the row the user is typing in.
                if (!this.payments.length || this.remaining > 0.0001) {
                    if (this.canAddPayment) this.addPayment();
                }
                if (this.remaining > 0.0001) return;

                // The shopper claimed they already paid this by static UPI QR,
                // which nothing verified. Settling it with the same reflex click
                // as a cash sale is how goods walk out unpaid — make the cashier
                // state, out loud, that they saw the transfer.
                if (this.order.claim && !this._claimAcknowledged) {
                    this._promptClaimCheck();
                    return;
                }

                this.submitting = true;
                this.error = '';
                try {
                    const { data } = await posPost(this.order.payUrl, {
                        local_uuid: this._uuid(),
                        items: this.lines.map((l) => ({ sale_item_id: l.id, quantity: String(l.qty) })),
                        payments: this.payments.map((p) => ({
                            payment_method_id:  p.payment_method_id,
                            amount:             p.amount,
                            tendered_amount:    p.tendered_amount,
                            reference:          p.reference,
                            gateway_payment_id: p.gateway_payment_id ?? null,
                            gateway_status:     p.gateway_status ?? null,
                        })),
                    });

                    this._removeRow(this.order.id);
                    this.$store.toasts?.push({ type: 'success', message: data.message });
                    this.closePay();
                } catch (e) {
                    this.error = e?.message || 'Couldn\'t take that payment.';
                } finally {
                    this.submitting = false;
                }
            },

            /** The paid order is gone server-side — reload the current page so it
             *  leaves the queue and the pager re-counts, without a full reload so
             *  the cashier goes straight to the next one. */
            _removeRow() {
                this.dtInvalidate();
                this._dtLoad({ bustCache: true });
            },

            _uuid() {
                return (window.crypto && typeof window.crypto.randomUUID === 'function')
                    ? window.crypto.randomUUID()
                    : 'ko-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10);
            },
        },
    );
}
