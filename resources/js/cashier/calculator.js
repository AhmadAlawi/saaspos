/**
 * Cashier on-screen calculator (Alt+C). Extracted from the inline x-data
 * that used to live in cashier/index.blade.php so it can grow features —
 * notably a running expression line + a persisted calculation history.
 *
 * History is kept in localStorage so it survives closing the modal and
 * page reloads during a shift. Tapping a past result loads it back into
 * the display.
 */
const HISTORY_KEY = 'pos_calc_history';
const HISTORY_MAX = 25;
const OP_SYMBOL   = { '+': '+', '-': '−', '*': '×', '/': '÷' };

export function calculator() {
    return {
        open:      false,
        display:   '0',
        prefix:    '',      // committed part of the expression, e.g. "195 + "
        operand:   null,
        operator:  null,
        newNumber: false,
        history:   [],

        /**
         * The live expression shown above the display. It's the committed
         * `prefix` plus whatever number is currently being typed, so a sum
         * reads "195 + 195" as the user enters the second operand. Empty
         * until the first operator, so it doesn't just echo the display.
         */
        get liveExpr() {
            if (this.prefix === '') return '';
            return (this.prefix + (this.newNumber ? '' : this.display)).trim();
        },

        init() {
            try {
                const raw = localStorage.getItem(HISTORY_KEY);
                this.history = raw ? JSON.parse(raw) : [];
                if (!Array.isArray(this.history)) this.history = [];
            } catch { this.history = []; }
        },

        symbol(op) { return OP_SYMBOL[op] ?? op; },

        /** Round to 8 dp to tame float noise, then stringify. */
        clean(n) {
            const v = Math.round(parseFloat(n) * 1e8) / 1e8;
            return Number.isFinite(v) ? String(v) : '0';
        },

        press(k) {
            k = String(k);

            if (k === 'C')  { this.clearAll(); return; }
            if (k === 'CE') { this.display = '0'; this.newNumber = false; return; }

            if (['+', '-', '*', '/'].includes(k)) {
                // Switching the operator before typing the next number — just
                // swap the trailing symbol, don't append a new term.
                if (this.newNumber && this.prefix !== '') {
                    this.prefix = this.prefix.replace(/[+\-×÷]\s$/u, this.symbol(k) + ' ');
                    this.operator = k;
                    return;
                }
                // Commit the just-typed number + this operator to the chain,
                // BEFORE folding the previous op into a running subtotal.
                this.prefix += this.clean(this.display) + ' ' + this.symbol(k) + ' ';
                if (this.operator !== null && !this.newNumber) this.calculate();
                this.operator  = k;
                this.operand   = parseFloat(this.display);
                this.newNumber = true;
                return;
            }

            if (k === '=') {
                if (this.operator !== null && this.operand !== null) {
                    const line = (this.prefix + this.clean(this.display)).trim();
                    this.calculate();
                    this.pushHistory(`${line} = ${this.display}`, this.display);
                    this.prefix    = '';
                    this.operator  = null;
                    this.operand   = null;
                    this.newNumber = true;
                }
                return;
            }

            if (k === '.') {
                if (this.newNumber) { this.display = '0.'; this.newNumber = false; }
                else if (!this.display.includes('.')) { this.display += '.'; }
                return;
            }

            // a digit
            if (this.display === '0' || this.newNumber) {
                this.display = k;
                this.newNumber = false;
            } else {
                this.display += k;
            }
        },

        calculate() {
            if (this.operator === null || this.operand === null) return;
            const current = parseFloat(this.display);
            let result = 0;
            if (this.operator === '+') result = this.operand + current;
            if (this.operator === '-') result = this.operand - current;
            if (this.operator === '*') result = this.operand * current;
            if (this.operator === '/') result = current === 0 ? 0 : this.operand / current;
            this.display = this.clean(result);
        },

        clearAll() {
            this.display = '0';
            this.prefix = '';
            this.operand = null;
            this.operator = null;
            this.newNumber = false;
        },

        // ── History ────────────────────────────────────────────────
        pushHistory(expr, result) {
            this.history.unshift({ expr, result: String(result) });
            if (this.history.length > HISTORY_MAX) this.history = this.history.slice(0, HISTORY_MAX);
            this.persist();
        },

        /** Load a past result back into the display to keep working with it. */
        useHistory(item) {
            this.display = String(item.result);
            this.prefix = '';
            this.operand = null;
            this.operator = null;
            this.newNumber = true;
        },

        clearHistory() {
            this.history = [];
            this.persist();
        },

        persist() {
            try { localStorage.setItem(HISTORY_KEY, JSON.stringify(this.history)); } catch { /* quota / private mode */ }
        },

        // ── Keyboard ───────────────────────────────────────────────
        handleKey(e) {
            if (!this.open) return;
            const key = e.key;
            if (/^[0-9.]$/.test(key))                       this.press(key);
            else if (['+', '-', '*', '/'].includes(key))    this.press(key);
            else if (key === 'Enter' || key === '=')        { e.preventDefault(); this.press('='); }
            else if (key === 'Backspace' || key === 'Delete') this.press('CE');
            else if (key.toLowerCase() === 'c')             this.press('C');
        },
    };
}
