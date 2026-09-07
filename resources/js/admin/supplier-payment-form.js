import { posGet, posPost, applyValidationErrors, flattenErrorMessages } from '../lib/http.js';

/**
 * Alpine factory for the Supplier-Payment create form.
 *
 * **Submit path**: axios via `posPost()` per the http-client memory
 * rule. The controller returns JSON `{message, redirect}` on success
 * or a 422 with `errors` map on failure. Page never reloads.
 *
 * Owns: header state, the open-purchases list (loaded from the JSON
 * endpoint via `posGet`), the per-row allocation map, and the reactive
 * `amount` getter.
 *
 * Two allocation paths: manual per-row, or **Auto FIFO** (user types
 * total amount, button spreads it across oldest unpaid POs).
 *
 * Server is authoritative — the action re-validates every allocation
 * against live `balance_due` and throws on inconsistencies. This form
 * is a typing assistant.
 */
export function supplierPaymentForm({
    openPurchasesUrlTemplate,   // '/admin/suppliers/__ID__/open-purchases'
    purchaseShowUrlTemplate = '', // '/admin/purchases/__ID__' — links the PO number cell to the purchase detail page
    suppliers              = [], // [{id, default_currency_code}, …]
    initialSupplierId      = '',
    initialStoreId         = '',
    initialMethodId        = '',
    initialPaymentDate     = '',
    initialReference       = '',
    initialNotes           = '',
    initialAllocationsRaw  = [], // [{purchase_id, amount}, …] — from old() after a failed submit
    initialPurchases       = [], // pre-loaded for ?supplier_id= entry point OR validation-error recovery
    initialMethodRequiresRef = {}, // {methodId: bool}
} = {}) {
    const suppliersById = Object.fromEntries(suppliers.map((s) => [String(s.id), s]));
    const refRequiredByMethod = initialMethodRequiresRef || {};

    return {
        // Exposed so the table cell can build the per-row link inline.
        purchaseShowUrlTemplate,
        supplierId:      initialSupplierId  ? String(initialSupplierId) : '',
        storeId:         initialStoreId     ? String(initialStoreId)    : '',
        methodId:        initialMethodId    ? String(initialMethodId)   : '',
        paymentDate:     initialPaymentDate || new Date().toISOString().slice(0, 10),
        reference:       initialReference   || '',
        notes:           initialNotes       || '',

        // Map of purchase_id → allocation amount (string, empty = no allocation).
        allocations:     {},

        /** Amount the user wants to pay in TOTAL via Auto-allocate.
         *  Independent of the computed `amount` (which is the sum of
         *  the per-row inputs). Lets the user type "5000" once and
         *  have FIFO spread it across oldest unpaid POs. */
        autoAmount:      '',

        // Open POs for the currently-picked supplier.
        purchases:       (initialPurchases || []).map((p) => ({ ...p, _balance: parseFloat(p.balance_due) || 0 })),

        loading:         false,

        /** Tracks which supplierId the open-purchases list was loaded
         *  for. `onSupplierChange()` short-circuits when called with
         *  the same value — that keeps the x-effect's initial tick
         *  from re-fetching (and wiping) the server-rendered list. */
        _lastFetchedSupplierId: '',

        init() {
            // Replace the whole map at once — assigning property by
            // property AFTER the proxy's tracking pass can miss Alpine's
            // reactivity in some browsers, which is why an earlier
            // `clearAllocations()` looked like a no-op.
            if (this.supplierId && initialAllocationsRaw && initialAllocationsRaw.length) {
                // Recovering from a failed submission — repopulate
                // each row from the old `allocations[i][amount]` form
                // values keyed by purchase_id.
                const map = Object.fromEntries(this.purchases.map((p) => [p.id, '']));
                for (const a of initialAllocationsRaw) {
                    if (!a || !a.purchase_id) continue;
                    map[a.purchase_id] = a.amount ?? '';
                }
                this.allocations = map;
            } else {
                this.allocations = Object.fromEntries(
                    this.purchases.map((p) => [p.id, ''])
                );
            }

            // Mark the initial supplier as "already loaded" so the
            // x-effect that wraps `onSupplierChange(supplierId)` does
            // nothing on its first synchronous tick. Without this the
            // server-rendered `initialPurchases` get wiped by an
            // immediate AJAX call that re-fetches the same list.
            this._lastFetchedSupplierId = this.supplierId || '';
        },

        /** Tracks the in-flight submit so the button can lock + spin
         *  and we never double-post on a slow network. */
        submitting:      false,

        /**
         * AJAX submit per the http-client rule. Posts via `posPost`,
         * lets the lib's interceptors handle CSRF / 419 / 5xx, and
         * pulls 422 errors out of the rejection to paint `.has-error`
         * on the right fields + push them as a multi-line toast.
         *
         * Called from `@submit.prevent="submit($el)"` on the form.
         */
        async submit(formEl) {
            if (this.submitting) return;
            this.submitting = true;

            // Clear any stale validation marks from a previous attempt.
            formEl.querySelectorAll('.field.has-error').forEach((el) => el.classList.remove('has-error'));

            try {
                // Build the payload from FormData, then drop every
                // allocation row whose amount is zero/blank — those are
                // POs the user chose to leave unsettled and must not hit
                // the server (it correctly rejects `amount=0` as
                // required+>0). We keep the per-PO server validation
                // authoritative; this is just "don't send what's empty".
                const fd = new FormData(formEl);
                this._dropEmptyAllocations(fd);

                const { data } = await posPost(formEl.action, fd);
                if (data?.message) {
                    this.$store.toasts.push({ type: 'success', message: data.message });
                }
                if (data?.redirect) {
                    window.location.href = data.redirect;
                }
            } catch (e) {
                if (e.status === 422) {
                    applyValidationErrors(formEl, e.errors);
                    const messages = flattenErrorMessages(e.errors);
                    this.$store.toasts.push({
                        type:     'error',
                        title:    'Please review the form',
                        messages: messages.length ? messages : [e.message],
                        duration: 0,
                    });
                }
                // 5xx is already toasted by the global interceptor;
                // network errors fall through silently — the spinner
                // unlocks below.
            } finally {
                this.submitting = false;
            }
        },

        /**
         * Mutates a FormData built from the form to remove every
         * `allocations[i][amount]` / `allocations[i][purchase_id]` pair
         * where the amount is empty/zero/non-positive. Walks indexes
         * 0..N — Laravel doesn't care about gaps in the array keys
         * because the FormRequest validates `allocations.*.amount`
         * regardless of index.
         */
        _dropEmptyAllocations(fd) {
            const indexes = new Set();
            for (const key of fd.keys()) {
                const m = key.match(/^allocations\[(\d+)\]/);
                if (m) indexes.add(m[1]);
            }
            for (const i of indexes) {
                const amt = parseFloat(fd.get(`allocations[${i}][amount]`));
                if (!Number.isFinite(amt) || amt <= 0) {
                    fd.delete(`allocations[${i}][amount]`);
                    fd.delete(`allocations[${i}][purchase_id]`);
                }
            }
        },

        /** Computed: sum of the per-PO allocation rows. */
        get allocatedTotal() {
            return Object.values(this.allocations).reduce((s, v) => {
                const n = parseFloat(v) || 0;
                return n > 0 ? s + n : s;
            }, 0);
        },

        /**
         * Total payment amount shipped to the server. Defaults to the
         * sum of per-PO allocations — preserving the v1 behaviour where
         * a payment is fully allocated. When the user types a larger
         * value into "Amount to allocate", that becomes the total and
         * the difference rides as supplier credit per feature doc §7.3.
         */
        get amount() {
            const typed = parseFloat(this.autoAmount) || 0;
            return Math.max(this.allocatedTotal, typed);
        },

        /** Positive when the typed total exceeds the per-PO allocations. */
        get unallocatedCredit() {
            const typed = parseFloat(this.autoAmount) || 0;
            return typed > this.allocatedTotal ? typed - this.allocatedTotal : 0;
        },

        /** Whether the current payment-method needs a reference number. */
        get requiresReference() {
            return !!refRequiredByMethod[this.methodId];
        },

        /** Currency to display the typed total + credit in. The supplier's
         *  default currency is the right anchor (same one the per-PO
         *  balance figures use). Falls back to the company currency when
         *  no supplier is picked yet. */
        get displayCurrency() {
            if (this.supplierId && suppliersById[String(this.supplierId)]) {
                return suppliersById[String(this.supplierId)].default_currency_code || '';
            }
            return '';
        },

        /** Symbol/formatter helper — defers to the global posFormatMoney. */
        money(value, code) {
            return window.posFormatMoney
                ? window.posFormatMoney(value, code)
                : Number(value || 0).toFixed(2);
        },

        async onSupplierChange(supplierId) {
            // No-op when the supplier hasn't actually changed. This is
            // what protects the server-rendered `initialPurchases` from
            // being wiped by the x-effect's first synchronous tick on
            // mount (init() pins `_lastFetchedSupplierId` to the
            // initial value). User-driven picker changes ARE different,
            // so they still fetch.
            if (String(supplierId || '') === String(this._lastFetchedSupplierId || '')) {
                return;
            }
            this._lastFetchedSupplierId = supplierId || '';
            this.supplierId  = supplierId;
            this.allocations = {};
            this.autoAmount  = '';
            this.purchases   = [];
            if (! supplierId) return;

            this.loading = true;
            try {
                // axios via posGet — interceptors handle CSRF, 401, 419,
                // 5xx-toast centrally per the http-client memory rule.
                const url = openPurchasesUrlTemplate.replace('__ID__', supplierId);
                const { data } = await posGet(url);
                const rows = Array.isArray(data) ? data : [];
                this.purchases = rows.map((p) => ({ ...p, _balance: parseFloat(p.balance_due) || 0 }));
                // Single assignment for reactivity (see init() note).
                this.allocations = Object.fromEntries(
                    this.purchases.map((p) => [p.id, ''])
                );
            } catch (e) {
                this.purchases = [];
            } finally {
                this.loading = false;
            }
        },

        /**
         * Auto-allocate `this.autoAmount` across the oldest unpaid POs
         * (FIFO). The user enters a single total in the "Amount to
         * allocate" field and clicks Auto-allocate — the function
         * fills the oldest PO completely, cascades the remainder to
         * the next, and so on until the amount is exhausted or no PO
         * is left. Anything left over leaves the user a hint via
         * `autoLeftover` (next slice will add unallocated credit).
         *
         * Replaces the allocations map in one assignment for
         * reactivity (per the init() note).
         */
        autoAllocate() {
            const target = parseFloat(this.autoAmount) || 0;
            if (target <= 0 || this.purchases.length === 0) return;

            let remaining = target;
            const next = Object.fromEntries(this.purchases.map((p) => [p.id, '']));
            for (const p of this.purchases) {
                if (remaining <= 0) break;
                const apply = Math.min(remaining, p._balance);
                if (apply > 0) {
                    next[p.id] = apply.toFixed(4);
                    remaining -= apply;
                }
            }
            this.allocations = next;
        },

        /** Leftover the user typed beyond what the open POs could absorb.
         *  Surfaced as a hint so the user understands why their entered
         *  amount and the computed total differ. */
        get autoLeftover() {
            const target = parseFloat(this.autoAmount) || 0;
            return Math.max(0, target - this.amount);
        },

        /** Clears every allocation row + the auto-amount input. */
        clearAllocations() {
            this.allocations = Object.fromEntries(
                this.purchases.map((p) => [p.id, ''])
            );
            this.autoAmount = '';
        },

        /** Pay-in-full helper per row — fills the row with the row's full balance. */
        payInFull(purchaseId, balance) {
            this.allocations[purchaseId] = (parseFloat(balance) || 0).toFixed(4);
        },
    };
}
