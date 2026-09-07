/**
 * Alpine factory for Accounting → Opening Balances.
 *
 * Purely presentational: sums the entered amounts on each account's natural
 * side and surfaces the running "Opening Balance Equity" plug so the user can
 * see the books approach balance. The real entry is built server-side by
 * {@see \App\Actions\Accounting\PostOpeningBalances} — this just mirrors the
 * arithmetic live. Submits through the global `data-ajax-form` handler.
 *
 * @param {{amounts?: Object, sides?: Object}} config
 *   amounts — account_id => initial amount string
 *   sides   — account_id => 'debit' | 'credit' (natural side)
 */
export function openingBalances({ amounts = {}, sides = {} } = {}) {
    return {
        amounts,
        sides,
        debitTotal:  0,
        creditTotal: 0,

        init() {
            this.recompute();
        },

        recompute() {
            let dr = 0;
            let cr = 0;
            for (const id in this.amounts) {
                const amt = parseFloat(this.amounts[id]);
                if (!amt || amt <= 0) continue;
                if (this.sides[id] === 'debit') dr += amt;
                else cr += amt;
            }
            this.debitTotal  = dr;
            this.creditTotal = cr;
        },

        /** Debit-heavy → positive (credited to OBE); credit-heavy → negative. */
        get plug() {
            return Math.round((this.debitTotal - this.creditTotal) * 10000) / 10000;
        },

        get plugAbs() {
            return Math.abs(this.plug);
        },

        get balanced() {
            return this.plugAbs < 0.00005;
        },
    };
}
