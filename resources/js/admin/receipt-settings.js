/**
 * Alpine factory for the Receipt template settings form.
 *
 * Holds every printable field two-way bound to the inputs and exposes
 * helpers the preview uses to width / show / hide sections. The preview
 * is purely a visual mirror — it does not validate or persist anything.
 */
export function receiptSettings(opts = {}) {
    return {
        paperSize:        opts.paperSize        ?? '80mm',
        showLogo:         opts.showLogo         ?? true,
        showCustomer:     opts.showCustomer     ?? true,
        showCashier:      opts.showCashier      ?? true,
        showTaxBreakdown: opts.showTaxBreakdown ?? true,
        showBarcode:      opts.showBarcode      ?? true,
        showQr:           opts.showQr           ?? false,
        showSku:          opts.showSku          ?? false,
        showHsn:          opts.showHsn          ?? false,
        showHsnSummary:   opts.showHsnSummary   ?? false,
        header:           opts.header           ?? '',
        footer:           opts.footer           ?? '',
        returnPolicy:     opts.returnPolicy     ?? '',

        /** Style for the preview frame — width tied to the paper size. */
        get previewStyle() {
            const w = this.paperSize === '58mm' ? '220px'
                    : this.paperSize === 'a4'    ? '380px'
                    : '300px';
            return `--rcpt-w: ${w}`;
        },
    };
}
