import { posGet, posPost, applyValidationErrors, flattenErrorMessages } from '../lib/http.js';

/**
 * Alpine factory for the Customer-Payment create form.
 *
 * Direct mirror of {@see supplierPaymentForm}; swap supplier→customer,
 * purchase→sale, and drop the per-payment store picker (customer
 * outstanding is cross-store).
 *
 * Owns: header state, the open-sales list (loaded from the JSON
 * endpoint via `posGet`), the per-row allocation map, and the reactive
 * `amount` getter. Two allocation paths: manual per-row, or Auto FIFO
 * (oldest sales first).
 *
 * Server (the action) is authoritative — re-validates every allocation
 * against live `balance_due` and throws on inconsistencies.
 */
export function customerPaymentForm({
    openSalesUrlTemplate,        // '/admin/customers/__ID__/open-sales'
    initialCustomerId      = '',
    initialMethodId        = '',
    initialPaymentDate     = '',
    initialReference       = '',
    initialNotes           = '',
    initialAllocationsRaw  = [], // [{sale_id, amount}, …] — from old() after a failed submit
    initialSales           = [], // pre-loaded for ?customer_id= entry point OR validation-error recovery
    initialMethodRequiresRef = {}, // {methodId: bool}
} = {}) {
    const refRequiredByMethod = initialMethodRequiresRef || {};

    return {
        customerId:      initialCustomerId  ? String(initialCustomerId) : '',
        methodId:        initialMethodId    ? String(initialMethodId)   : '',
        paymentDate:     initialPaymentDate || new Date().toISOString().slice(0, 10),
        reference:       initialReference   || '',
        notes:           initialNotes       || '',

        /** Map of sale_id → allocation amount (string; empty = no allocation). */
        allocations:     {},

        /** Total amount the user wants to pay — drives FIFO auto-allocate
         *  and over-tender → customer credit per Slice 6b. */
        autoAmount:      '',

        /** Open sales for the currently-picked customer. */
        sales:           (initialSales || []).map((s) => ({ ...s, _balance: parseFloat(s.balance_due) || 0 })),

        loading:         false,
        submitting:      false,

        /** Tracks which customerId the open-sales list was loaded for
         *  so the init x-effect tick doesn't blow away the
         *  server-rendered list. */
        _lastFetchedCustomerId: '',

        init() {
            // Single-assignment for reactivity. Recovery-after-422 path
            // repopulates from old() values; otherwise blank map.
            if (this.customerId && initialAllocationsRaw && initialAllocationsRaw.length) {
                const map = Object.fromEntries(this.sales.map((s) => [s.id, '']));
                for (const a of initialAllocationsRaw) {
                    if (!a || !a.sale_id) continue;
                    map[a.sale_id] = a.amount ?? '';
                }
                this.allocations = map;
            } else {
                this.allocations = Object.fromEntries(this.sales.map((s) => [s.id, '']));
            }
            this._lastFetchedCustomerId = this.customerId || '';
        },

        async submit(formEl) {
            if (this.submitting) return;
            this.submitting = true;

            formEl.querySelectorAll('.field.has-error').forEach((el) => el.classList.remove('has-error'));

            try {
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
            } finally {
                this.submitting = false;
            }
        },

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
                    fd.delete(`allocations[${i}][sale_id]`);
                }
            }
        },

        get allocatedTotal() {
            return Object.values(this.allocations).reduce((s, v) => {
                const n = parseFloat(v) || 0;
                return n > 0 ? s + n : s;
            }, 0);
        },

        /** Total payment amount shipped to the server. Mirrors supplier-side:
         *  max of (per-row sum, typed total). Over-tender lands as customer
         *  credit (sale_id=null row). */
        get amount() {
            const typed = parseFloat(this.autoAmount) || 0;
            return Math.max(this.allocatedTotal, typed);
        },

        get unallocatedCredit() {
            const typed = parseFloat(this.autoAmount) || 0;
            return typed > this.allocatedTotal ? typed - this.allocatedTotal : 0;
        },

        get requiresReference() {
            return !!refRequiredByMethod[this.methodId];
        },

        money(value, code) {
            return window.posFormatMoney
                ? window.posFormatMoney(value, code)
                : Number(value || 0).toFixed(2);
        },

        async onCustomerChange(customerId) {
            if (String(customerId || '') === String(this._lastFetchedCustomerId || '')) {
                return;
            }
            this._lastFetchedCustomerId = customerId || '';
            this.customerId  = customerId;
            this.allocations = {};
            this.autoAmount  = '';
            this.sales       = [];
            if (! customerId) return;

            this.loading = true;
            try {
                const url = openSalesUrlTemplate.replace('__ID__', customerId);
                const { data } = await posGet(url);
                const rows = Array.isArray(data) ? data : [];
                this.sales = rows.map((s) => ({ ...s, _balance: parseFloat(s.balance_due) || 0 }));
                this.allocations = Object.fromEntries(this.sales.map((s) => [s.id, '']));
            } catch (e) {
                this.sales = [];
            } finally {
                this.loading = false;
            }
        },

        /** Spread `autoAmount` across oldest unpaid sales (FIFO). */
        autoAllocate() {
            const target = parseFloat(this.autoAmount) || 0;
            if (target <= 0 || this.sales.length === 0) return;

            let remaining = target;
            const next = Object.fromEntries(this.sales.map((s) => [s.id, '']));
            for (const s of this.sales) {
                if (remaining <= 0) break;
                const apply = Math.min(remaining, s._balance);
                if (apply > 0) {
                    next[s.id] = apply.toFixed(4);
                    remaining -= apply;
                }
            }
            this.allocations = next;
        },

        get autoLeftover() {
            const target = parseFloat(this.autoAmount) || 0;
            return Math.max(0, target - this.amount);
        },

        clearAllocations() {
            this.allocations = Object.fromEntries(this.sales.map((s) => [s.id, '']));
            this.autoAmount = '';
        },

        payInFull(saleId, balance) {
            this.allocations[saleId] = (parseFloat(balance) || 0).toFixed(4);
        },
    };
}
