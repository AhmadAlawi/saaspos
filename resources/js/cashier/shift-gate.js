import { posPost } from '../lib/http.js';

/**
 * Cashier shift gate (Slices A + B).
 *
 * A blocking overlay over the cashier with up to two steps:
 *   - Step 1 (Slice B): pick the terminal this device runs, when the
 *     store has terminals and none is bound. Sets the `pos_terminal_id`
 *     cookie, then reloads so the shift + sale rows stamp with it.
 *   - Step 2 (Slice A): open a shift when the store enforces shifts.
 *     Posts the counted opening cash, then reloads so the sale flow
 *     binds to the freshly-opened shift.
 *
 * Each successful POST triggers a full reload because the gate state
 * (needsTerminal / hasOpen / the inline payload) is server-rendered.
 * Operators holding `shifts.bypass_enforcement` can skip the shift step.
 */
export function shiftGate({
    openUrl,
    canBypass = false,
    reloadUrl = null,
    needsTerminal = false,
    terminalUrl = null,
    terminals = [],
    denominations = [],
    dayRequired = false,
    dayOpen = true,
    canOpenDay = false,
    openDayUrl = null,
}) {
    return {
        visible: true,
        submitting: false,
        canBypass: !!canBypass,
        canOpenDay: !!canOpenDay,
        // Three-step flow: terminal first (if needed), then the day-open
        // gate (if the store requires one and it isn't open), then shift.
        // The day step depends on a resolved terminal — the terminal step
        // always wins while needsTerminal is true, and dayOpen is only
        // ever computed server-side against the terminal already bound.
        mode: needsTerminal ? 'terminal' : ((dayRequired && !dayOpen) ? 'day' : 'shift'),
        terminals: terminals || [],
        terminalId: (terminals && terminals.length) ? terminals[0].id : null,
        openingCash: '',
        notes: '',
        // Denomination helper (Slice C).
        denomOpen: false,
        denoms: denominations || [],
        denomCounts: {},

        get denomTotal() {
            let t = 0;
            for (const d of this.denoms) t += (Number(this.denomCounts[d]) || 0) * Number(d);
            return Number(t.toFixed(4));
        },
        denomSubtotal(d) {
            return (Number(this.denomCounts[d]) || 0) * Number(d);
        },
        applyDenomTotal() {
            this.openingCash = this.denomTotal;
        },

        init() {
            this.$nextTick(() => {
                // The terminal picker is an enhancedSelect: TomSelect hides the
                // native <select>, so focusing it directly is a silent no-op.
                // Focus the TomSelect control when one exists.
                const sel = this.$refs.terminalSelect;
                if (sel?.tomselect) {
                    sel.tomselect.focus();
                    return;
                }
                (sel || this.$refs.openingCash)?.focus();
            });
        },

        async selectTerminal() {
            if (this.submitting || !this.terminalId) return;
            this.submitting = true;
            try {
                await posPost(terminalUrl, { terminal_id: this.terminalId });
                window.location.assign(reloadUrl || window.location.href);
            } catch (e) {
                this._toastError(e);
                this.submitting = false;
            }
        },

        async openDay() {
            if (this.submitting || !this.canOpenDay) return;
            this.submitting = true;
            try {
                await posPost(openDayUrl, {});
                window.location.assign(reloadUrl || window.location.href);
            } catch (e) {
                this._toastError(e);
                this.submitting = false;
            }
        },

        async submit() {
            if (this.submitting) return;
            this.submitting = true;
            try {
                // Only send entered counts — empty inputs would fail the
                // integer rule on the server.
                const denoms = {};
                for (const d of this.denoms) {
                    const n = Number(this.denomCounts[d]);
                    if (Number.isFinite(n) && n > 0) denoms[d] = n;
                }

                await posPost(openUrl, {
                    opening_cash: this.openingCash === '' ? '0' : this.openingCash,
                    notes: this.notes || null,
                    opening_denominations: denoms,
                });
                window.location.assign(reloadUrl || window.location.href);
            } catch (e) {
                this._toastError(e);
                this.submitting = false;
            }
        },

        bypass() {
            this.visible = false;
        },

        /**
         * Surface a server error toast. The http.js interceptors only
         * auto-handle 401 / 419 / 5xx; 422 business errors (e.g. "another
         * cashier already has this terminal", "shift already open") are
         * left for the caller — so we toast them here.
         */
        _toastError(e) {
            if (e?.status === 422) {
                const msgs = Object.values(e.errors || {}).flat().filter(Boolean);
                this.$store.toasts.push({
                    type: 'error',
                    message: e.message || msgs[0] || 'Could not complete the request.',
                });
            }
        },
    };
}
