/**
 * Customer-Facing Display (CFD) — standalone entry (Slice 1).
 *
 * A lean, presentation-only page: it holds no cart, does no pricing, and
 * makes no server calls. It subscribes to the cashier's same-machine
 * BroadcastChannel and paints whatever snapshot it last received. Bundled
 * separately from admin.js so the second screen doesn't pull the whole
 * back-office. See docs/features/customer-display.md.
 */
import Alpine from 'alpinejs';
import QRCode from 'qrcode';
import { subscribeCfd } from './cfd-broadcast.js';

export function customerDisplay(bootstrap = {}) {
    return {
        // ── Bootstrap (server-rendered) ──────────────────────────
        channel:  bootstrap.channel || 'pos-cfd:default',
        // Transport (Slice 4): 'same_machine' subscribes to the local
        // BroadcastChannel; 'separate_device' polls the relay endpoint.
        transport: bootstrap.transport || 'same_machine',
        pollUrl:   bootstrap.poll_url || null,
        welcome:  bootstrap.welcome || '',
        tagline:  bootstrap.tagline || '',
        thankyou: bootstrap.thankyou || '',
        labels:   bootstrap.labels || {},
        // When on, mirror the cashier's UPI payment QR onto the payment
        // screen (per-terminal opt-in). Off → UPI stays on the till only.
        showUpiQr: !!bootstrap.show_upi_qr,

        // Attract-media slideshow (Slice 3).
        attract:  bootstrap.attract || { media: [], seconds: 8 },
        promoIx:  0,

        // ── Live state ───────────────────────────────────────────
        state:    'idle',   // 'idle' | 'sale' | 'payment' | 'thankyou'
        snap:     { last_line: null, lines: [], totals: {}, customer: null, payment: null, thankyou: null },
        clock:    '',
        payQr:    '',       // data-URL of the pay_url QR (payment state)
        upiQr:    '',       // data-URL of the upi_pay_url QR (payment state)
        receiptQr: '',      // data-URL of the receipt_url QR (thank-you state)
        _payQrUrl: null,    // last URLs we rendered, to skip redundant regens
        _upiQrUrl: null,
        _receiptQrUrl: null,
        _lastSeq: -1,
        _unsub:   null,

        /** Newest line first — the just-scanned item sits at the top and
         *  gets the flash highlight. */
        get linesNewestFirst() {
            return [...(this.snap.lines || [])].reverse();
        },

        get itemCountLabel() {
            const n = Number(this.snap?.totals?.item_count ?? 0);
            return `${this.snap?.totals?.item_count ?? 0} ${n === 1 ? this.labels.item : this.labels.items}`;
        },

        get hasAttract() {
            return Array.isArray(this.attract.media) && this.attract.media.length > 0;
        },

        init() {
            this._tick();
            setInterval(() => this._tick(), 20000);

            // Pick the transport. A separate device can't hear the local
            // BroadcastChannel, so it polls the relay instead.
            if (this.transport === 'separate_device' && this.pollUrl) {
                this._startPolling();
            } else {
                this._unsub = subscribeCfd(this.channel, (s) => this._apply(s));
            }

            // Rotate the attract slideshow (only when there's more than one).
            if (this.hasAttract && this.attract.media.length > 1) {
                const ms = Math.max(3, Number(this.attract.seconds) || 8) * 1000;
                setInterval(() => {
                    this.promoIx = (this.promoIx + 1) % this.attract.media.length;
                }, ms);
            }
        },

        /** Apply an incoming snapshot, dropping stale / out-of-order frames. */
        _apply(s) {
            if (!s || typeof s !== 'object') return;
            if (typeof s.seq === 'number' && s.seq <= this._lastSeq) return;
            this._lastSeq = typeof s.seq === 'number' ? s.seq : this._lastSeq;
            this.snap  = s;
            this.state = ['sale', 'payment', 'thankyou'].includes(s.state) ? s.state : 'idle';
            this._maybeQr();
        },

        /** (Re)render the pay-URL and receipt-URL QRs when they change —
         *  async, so kept off the snapshot itself. The cashier sends the
         *  URL; we draw the code. */
        async _maybeQr() {
            const payUrl = this.snap?.payment?.pay_url || null;
            if (payUrl !== this._payQrUrl) {
                this._payQrUrl = payUrl;
                this.payQr = payUrl ? await this._toQr(payUrl) : '';
            }

            // UPI QR — only when this terminal opted in; otherwise the raw
            // deep link is ignored and never drawn.
            const upiUrl = this.showUpiQr ? (this.snap?.payment?.upi_pay_url || null) : null;
            if (upiUrl !== this._upiQrUrl) {
                this._upiQrUrl = upiUrl;
                this.upiQr = upiUrl ? await this._toQr(upiUrl) : '';
            }

            const receiptUrl = this.snap?.thankyou?.receipt_url || null;
            if (receiptUrl !== this._receiptQrUrl) {
                this._receiptQrUrl = receiptUrl;
                this.receiptQr = receiptUrl ? await this._toQr(receiptUrl, 200) : '';
            }
        },

        async _toQr(url, width = 320) {
            try {
                return await QRCode.toDataURL(url, { width, margin: 1, errorCorrectionLevel: 'M' });
            } catch (e) {
                return '';
            }
        },

        _tick() {
            const d = new Date();
            this.clock = d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
        },

        /** Flip light/dark on the display and remember it in the display's own
         *  key (`pos_cfd_theme`) — kept separate from the cashier's theme. The
         *  moon/sun icon swap is CSS-driven off the `.dark` class. */
        toggleTheme() {
            const isDark = document.documentElement.classList.toggle('dark');
            try { localStorage.setItem('pos_cfd_theme', isDark ? 'dark' : 'light'); } catch (e) { /* private mode */ }
        },

        /** Separate-device transport: poll the relay for the latest frame.
         *  Only applies frames that carry a seq (an empty relay is ignored,
         *  so the display holds its last state). Errors are swallowed — a
         *  dropped poll just retries on the next tick. */
        _startPolling() {
            const poll = async () => {
                try {
                    const res = await fetch(this.pollUrl, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                        cache: 'no-store',
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    if (data && typeof data.seq === 'number') this._apply(data);
                } catch (e) { /* transient / offline — retry next tick */ }
            };
            poll();
            setInterval(poll, 1500);
        },
    };
}

document.addEventListener('alpine:init', () => {
    Alpine.data('customerDisplay', customerDisplay);
});

window.Alpine = Alpine;
Alpine.start();
