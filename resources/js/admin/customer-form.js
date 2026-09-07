/**
 * Alpine factory for the Customer create / edit form.
 *
 * Responsibilities:
 *   - `addresses[]` repeater with stable `_uid` keys
 *   - `isBusiness` toggle showing/hiding the B2B section
 *   - `groupId` → auto-apply that group's `default_discount_percent`
 *     into the discount field (unless the user has already overridden it)
 *   - Default country for fresh address rows comes from the company /
 *     first store, computed server-side.
 */
export function customerForm(opts = {}) {
    let _uid = 0;
    const defaultCountry = opts.defaultCountry || '';

    const blankAddress = (raw = {}) => ({
        _uid:         ++_uid,
        label:        raw.label        ?? '',
        line1:        raw.line1        ?? '',
        line2:        raw.line2        ?? '',
        city:         raw.city         ?? '',
        state:        raw.state        ?? '',
        postal_code:  raw.postal_code  ?? '',
        country_code: raw.country_code ?? defaultCountry,
        landmark:     raw.landmark     ?? '',
        is_default:   !!raw.is_default,
    });

    // Map[groupId → defaultDiscountPercent | null] for instant lookup.
    const groupDiscounts = Object.fromEntries(
        (opts.groups ?? []).map((g) => [
            String(g.id),
            g.default_discount_percent !== null && g.default_discount_percent !== undefined
                ? String(g.default_discount_percent)
                : null,
        ]),
    );

    return {
        isBusiness: !!opts.isBusiness,
        addresses:  (opts.addresses ?? []).map(blankAddress),

        // B2B fields, kept in state so they survive the x-if toggle and can
        // be wiped when the user unchecks "This is a business customer".
        business: {
            name:   opts.business?.name   ?? '',
            gstin:  opts.business?.gstin  ?? '',
            pan:    opts.business?.pan    ?? '',
            taxReg: opts.business?.taxReg ?? '',
        },

        groupId:           opts.initialGroupId  ? String(opts.initialGroupId)  : '',
        discountPercent:   opts.initialDiscount !== null && opts.initialDiscount !== undefined
                                ? String(opts.initialDiscount) : '',

        init() {
            // Every group change overrides the discount with the new
            // group's default. The user can still type a custom value
            // after picking the group; picking another group overrides
            // again. Matches the explicit ask: "when I change customer
            // group it should apply group discount".
            this.$watch('groupId', (next) => {
                const def = groupDiscounts[next] ?? null;
                this.discountPercent = def !== null ? def : '';
            });

            // Unchecking "business" clears every B2B detail, so re-checking
            // starts clean and a save can't keep stale GSTIN/PAN/tax values.
            this.$watch('isBusiness', (on) => {
                if (!on) {
                    this.business.name = '';
                    this.business.gstin = '';
                    this.business.pan = '';
                    this.business.taxReg = '';
                }
            });
        },

        addAddress() {
            this.addresses.push(blankAddress({
                // First address auto-flags as default; subsequent ones
                // don't, so the user has to consciously choose.
                is_default: this.addresses.length === 0,
            }));
        },

        removeAddress(uid) {
            const wasDefault = this.addresses.find((a) => a._uid === uid)?.is_default;
            this.addresses = this.addresses.filter((a) => a._uid !== uid);
            // If we removed the default, promote the first remaining
            // row so there's always a fallback shipping address on a
            // saved customer with at least one row.
            if (wasDefault && this.addresses.length > 0) {
                this.addresses[0].is_default = true;
            }
        },

        /** Toggle a single row to default and clear it on the others. */
        makeDefault(uid) {
            this.addresses.forEach((a) => { a.is_default = a._uid === uid; });
        },
    };
}
