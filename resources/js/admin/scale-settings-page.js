/**
 * Alpine factory for the Scale settings page.
 *
 * Two-way binds the barcode-template fields and offers a LIVE decoder
 * preview: the admin pastes/scans a sample barcode and immediately sees how
 * the current settings would read it. Uses the exact same decoder the
 * cashier runs ({@link ../cashier/scale-barcode.js}) so the preview can
 * never disagree with the till.
 */
import { decodeScaleBarcode } from '../cashier/scale-barcode.js';

export function scaleSettingsPage(initial = {}) {
    return {
        cfg: {
            prefix:         String(initial.prefix ?? '2'),
            plu_length:     Number(initial.plu_length ?? 5),
            value_offset:   Number(initial.value_offset ?? 0),
            value_length:   Number(initial.value_length ?? 5),
            value_decimals: Number(initial.value_decimals ?? 3),
            embed_type:     initial.embed_type === 'price' ? 'price' : 'weight',
        },

        // Sample barcode the admin types to test the template.
        sample: String(initial.sample ?? ''),

        /** Decode the sample against the live form values (always "enabled"
         *  here — the preview should work even while the feature is off). */
        get decoded() {
            if (this.sample.trim() === '') return null;
            return decodeScaleBarcode(this.sample, { ...this.cfg, enabled: true });
        },

        /** Formatted value for the preview line ("1.500" / "12.34"). */
        get decodedValue() {
            const d = this.decoded;
            if (!d) return '';
            return d.value.toFixed(Number(this.cfg.value_decimals) || 0);
        },

        /** A worked example barcode built from the current template, so the
         *  help text shows something concrete that always parses. */
        get exampleCode() {
            const prefix = String(this.cfg.prefix || '');
            const plu    = this._fill('12345', this.cfg.plu_length);
            const skip   = '0'.repeat(Math.max(0, Number(this.cfg.value_offset) || 0));
            const val    = this._fill('1500', this.cfg.value_length);
            return prefix + plu + skip + val;
        },

        _fill(seed, len) {
            const n = Math.max(0, Number(len) || 0);
            return (seed.repeat(n).slice(0, n)).padStart(n, '0');
        },
    };
}
