import { posGet } from '../lib/http.js';

/**
 * Shared barcode-scanning behaviour for the inventory editors (adjustment,
 * transfer, take). Provides the scan input state, the resolve round-trip, and
 * the camera scanner — everything except what to DO with a resolved product.
 *
 * The host factory:
 *   - spreads this into its returned object: `...barcodeScanMixin({ scanUrl })`
 *   - calls `this.initBarcodeScan()` from its own `init()` and
 *     `this.destroyBarcodeScan()` from its own `destroy()`
 *   - implements `_onScanResolved(row)` — the only per-editor difference
 *     (adjustment/transfer add-or-bump a line; take finds a count row and bumps
 *     it). `row` is the picker shape `{product_id, variant_id, label, sku, …}`.
 *
 * A USB / Bluetooth-HID / keyboard-wedge scanner types into the focused scan
 * input and sends Enter (`@keydown.enter="onScanEnter()"`) — no HID
 * speed-detection needed. F8 focuses the field, matching the cashier screen so
 * the muscle memory is the same everywhere. The camera path (ZXing) loads
 * lazily.
 *
 * @param {{ scanUrl: ?string }} opts
 */
export function barcodeScanMixin({ scanUrl = null } = {}) {
    return {
        _scanUrl: scanUrl,

        scanQuery:       '',
        scanning:        false,
        cameraOpen:      false,
        cameraSupported: false,
        _camera:         null,
        _lastCamCode:    '',
        _lastCamAt:      0,
        _scanHotkey:     null,

        initBarcodeScan() {
            if (!this._scanUrl) return;

            // Cheap capability check; ZXing itself loads only when opened.
            import('../hardware/camera-scanner.js')
                .then(({ cameraScanSupported }) => { this.cameraSupported = cameraScanSupported(); })
                .catch(() => { this.cameraSupported = false; });

            // F8 → jump to the scan field from anywhere (not a text key, so
            // stealing focus mid-typing is harmless). Same key as the cashier
            // screen's scan shortcut — one barcode key across the whole app.
            // Global shortcuts.js only owns Cmd/Ctrl combos, so F8 is free.
            this._scanHotkey = (e) => {
                if (e.key !== 'F8') return;
                e.preventDefault();
                const el = this.$refs.scanInput;
                if (el) { el.focus(); el.select?.(); }
            };
            window.addEventListener('keydown', this._scanHotkey);
        },

        destroyBarcodeScan() {
            this._stopCamera();
            if (this._scanHotkey) window.removeEventListener('keydown', this._scanHotkey);
        },

        /** Scanner-typed or hand-typed barcode + Enter. */
        onScanEnter() {
            const code = (this.scanQuery || '').trim();
            if (!code) return;
            this.scanQuery = '';
            this._resolveScan(code);
        },

        /** Look the barcode up on the server, then hand the row to the host. */
        async _resolveScan(barcode) {
            const code = String(barcode || '').trim();
            if (!code || !this._scanUrl) return;

            this.scanning = true;
            let row = null;
            let found = false;
            try {
                ({ data: row } = await posGet(this._scanUrl, { barcode: code }));
                found = true;
            } catch (e) {
                // 404 → no product carries that barcode. Give the host a chance to
                // interpret the input first (e.g. a stock-take count typed into
                // the scan bar) before falling back to the not-found warning.
                const handled = e?.status === 404 && this._onScanNotFound?.(code);
                if (!handled) {
                    this.$store?.toasts?.push({
                        type: 'warning',
                        message: e?.message || `No product found for barcode ${code}.`,
                    });
                }
            }
            this.scanning = false;

            if (found) this._onScanResolved?.(row);

            // Focus after a scan. By default the scan field keeps focus so the
            // next scan can't land in a number field (barcode → qty overflow).
            // A host may override via `_scanFocusTarget()` — e.g. the stock take
            // focuses the located row's count field so the operator types the
            // quantity straight in (and its Enter returns focus to the scan bar).
            this.$nextTick(() => {
                const target = typeof this._scanFocusTarget === 'function' ? this._scanFocusTarget() : null;
                if (target) {
                    target.focus();
                    if (typeof target.select === 'function') target.select();
                } else {
                    this.$refs.scanInput?.focus();
                }
            });
        },

        // ── Camera scanning (ZXing) ──────────────────────────────────
        async openCamera() {
            if (!this.cameraSupported) return;
            this.cameraOpen = true;
            try {
                const { CameraBarcodeScanner } = await import('../hardware/camera-scanner.js');
                this._camera = new CameraBarcodeScanner(this.$refs.cameraVideo);
                await this._camera.start((text) => {
                    // ZXing fires every frame a barcode is in view, so dedupe the
                    // same code within a short window. (The USB path is NOT
                    // deduped — a deliberate re-scan there bumps quantity.)
                    const now = (typeof performance !== 'undefined' ? performance.now() : 0);
                    if (text === this._lastCamCode && now - this._lastCamAt < 1500) return;
                    this._lastCamCode = text;
                    this._lastCamAt   = now;
                    this._resolveScan(text);
                });
            } catch (_) {
                this.closeCamera();
                this.$store?.toasts?.push({ type: 'error', message: 'Could not start the camera.' });
            }
        },

        closeCamera() {
            this._stopCamera();
            this.cameraOpen = false;
        },

        _stopCamera() {
            try { this._camera?.stop(); } catch (_) { /* noop */ }
            this._camera = null;
        },
    };
}
