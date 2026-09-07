/**
 * USB-HID barcode-scanner listener (docs/features/hardware.md §7.2).
 *
 * Almost every USB barcode scanner acts as a keyboard: it "types" the
 * barcode digits then a suffix key (Enter / Tab). We tell a scan apart
 * from a human typing by speed — a scanner fires keystrokes ~20-50ms
 * apart, a person 100-300ms — plus the trailing suffix.
 *
 * Attach it to any input (or the document) and listen for the
 * `barcode:scan` CustomEvent it dispatches with the decoded string.
 *
 *   const scanner = new BarcodeScannerListener(inputEl, { suffixKey: 'Enter' });
 *   inputEl.addEventListener('barcode:scan', (e) => handle(e.detail));
 *   // later: scanner.detach();
 */
export class BarcodeScannerListener {
    constructor(target, options = {}) {
        this.target = target;
        this.options = {
            maxIntervalMs: 50,   // gap above this resets the buffer (human typing)
            minLength: 3,        // ignore stray short bursts
            suffixKey: 'Enter',  // Enter | Tab
            preventSuffixDefault: true,
            ...options,
        };
        this.buffer = '';
        this.lastKeyTime = 0;
        this._onKeydown = this._handleKeydown.bind(this);
        this.attach();
    }

    attach() {
        this.target.addEventListener('keydown', this._onKeydown);
    }

    detach() {
        this.target.removeEventListener('keydown', this._onKeydown);
    }

    _handleKeydown(e) {
        const now = (typeof performance !== 'undefined' ? performance.now() : Date.now());
        const interval = now - this.lastKeyTime;

        // A slow keystroke means this isn't part of a scan burst — reset.
        if (interval > this.options.maxIntervalMs && this.buffer.length > 0) {
            this.buffer = '';
        }

        if (e.key === this.options.suffixKey) {
            if (this.buffer.length >= this.options.minLength) {
                if (this.options.preventSuffixDefault) e.preventDefault();
                this._emit(this.buffer);
                this.buffer = '';
            }
            return;
        }

        // Only printable single characters contribute to a barcode.
        if (e.key && e.key.length === 1) {
            this.buffer += e.key;
            this.lastKeyTime = now;
        }
    }

    _emit(barcode) {
        this.target.dispatchEvent(new CustomEvent('barcode:scan', {
            detail: barcode,
            bubbles: true,
        }));
    }
}

/**
 * Convenience: attach a listener and route scans to a callback. Returns
 * a detach function.
 */
export function onBarcodeScan(target, callback, options = {}) {
    const scanner = new BarcodeScannerListener(target, options);
    const handler = (e) => callback(e.detail, e);
    target.addEventListener('barcode:scan', handler);

    return () => {
        target.removeEventListener('barcode:scan', handler);
        scanner.detach();
    };
}
