/**
 * Alpine factory for the Currency settings form.
 *
 * Holds the editable format fields (symbol, placement, decimals,
 * separators) two-way bound to the inputs, drives a live preview, and
 * pre-fills the format from the chosen currency's stored row when the
 * base-currency dropdown changes.
 *
 * The preview mirrors the server's `format_money()` exactly — simple
 * 3-digit grouping with the configured separators + placement — so what
 * the user sees here is what renders across the app.
 */
export function currencySettings(opts = {}) {
    return {
        code:        opts.code        ?? '',
        symbol:      opts.symbol      ?? '$',
        symbolFirst: opts.symbolFirst ?? true,
        decimals:    opts.decimals    ?? 2,
        thousands:   opts.thousands   ?? ',',
        decimal:     opts.decimal     ?? '.',

        /** { code → {symbol, symbol_first, decimals, thousands_separator, decimal_separator} } */
        map: opts.map ?? {},

        init() {
            // Picking a different currency loads that currency's saved
            // format as the starting point (still editable afterwards).
            this.$watch('code', (code) => {
                const m = this.map[code];
                if (!m) return;
                this.symbol      = m.symbol;
                this.symbolFirst = m.symbol_first;
                this.decimals    = m.decimals;
                this.thousands   = m.thousands_separator;
                this.decimal     = m.decimal_separator || '.';
            });
        },

        /** Live-formatted sample amount — matches format_money() output. */
        get preview() {
            const dec = Math.max(0, Math.min(4, parseInt(this.decimals, 10) || 0));
            const fixed = (1234567.5).toFixed(dec);
            let [intPart, fracPart] = fixed.split('.');
            // Group integer part in 3s with the configured separator.
            intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, this.thousands ?? '');
            const num = dec > 0 ? intPart + (this.decimal || '.') + fracPart : intPart;
            return this.symbolFirst ? this.symbol + num : num + ' ' + this.symbol;
        },
    };
}
