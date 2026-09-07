/**
 * Alpine factory for the Product create/edit page.
 *
 * Holds:
 *   - `tab` — which content panel is visible (General | Pricing | Inventory | Compliance)
 *   - `cost` / `selling` — two-way bound to the price inputs so the
 *     Margin / Markup / Profit KPI boxes can compute reactively
 *     while the user types.
 *   - `isActive` / `isFeatured` — two-way bound to the Status
 *     checkboxes so the header badge follows the user's edits.
 *   - `categoryId` / `taxGroupId` — two-way bound to the matching
 *     selects. When the category changes, taxGroupId is auto-filled
 *     from the chosen category's default (if any), saving the user a
 *     click on the common case where products inherit their category's
 *     tax rule.
 *
 * Form fields keep their `name` attributes, so x-model + name on the
 * same input behaves like a normal field (submits with the form AND
 * drives reactive bindings).
 */

/** Monotonic client-side id for keying kit rows in x-for (so stateful
 *  widgets like TomSelect aren't re-bound to a different row on remove). */
let _kitUidSeq = 0;
function kitUid() {
    return 'kit-' + (++_kitUidSeq);
}

export function productEditor(opts = {}) {
    return {
        tab:        opts.tab        ?? 'general',
        cost:       parseFloat(opts.cost)    || 0,
        selling:    parseFloat(opts.selling) || 0,
        isActive:   opts.isActive   ?? true,
        isFeatured: opts.isFeatured ?? false,

        // Kit auto-pricing: once the user hand-edits a price we stop auto-filling
        // it from the components sum. Seed as "overridden" when a price already
        // exists (editing a saved kit / a value typed before adding components),
        // so we never clobber it — the "Recalculate" link snaps back on demand.
        costOverridden:    (parseFloat(opts.cost)    || 0) > 0,
        sellingOverridden: (parseFloat(opts.selling) || 0) > 0,

        // Component price cache for the kit sum: keyed by productId (parent
        // price) and `productId:variantId` (variant price). Seeded for referenced
        // components; new picks add to it from the search endpoint.
        componentPriceMap: opts.componentPrices ?? {},

        // Product type — 'simple' | 'variant' | 'kit'. Drives which
        // secondary tabs (Variants, Components) are visible.
        type:       opts.type ?? 'simple',

        // Category / Tax sync state. Both stored as strings so they
        // match the underlying <option value="..."> attribute values
        // (HTML option values are strings, never numbers).
        categoryId:      opts.categoryId      != null ? String(opts.categoryId)  : '',
        taxGroupId:      opts.taxGroupId      != null ? String(opts.taxGroupId)  : '',
        // { categoryId (string) → taxGroupId (string|'') }. Looked up
        // by the watcher below. Provided by the controller.
        categoryTaxMap:  opts.categoryTaxMap  ?? {},

        /** Variant attribute DEFINITIONS — [{name, values[], _newValue}].
         *  `_newValue` is the transient "type a value, hit add" buffer for
         *  each attribute's chip input; it's never submitted. Editing any
         *  of these regenerates the matrix below. */
        variantAttributes: Array.isArray(opts.variantAttributes) ? opts.variantAttributes.map((a) => ({
            name:      a.name ?? '',
            values:    Array.isArray(a.values) ? [...a.values] : [],
            _newValue: '',
            _prevName: a.name ?? '',   // tracks the name before an edit, for rename migration
        })) : [],

        /** Generated variant matrix rows. Each is one cartesian
         *  combination of the attribute values:
         *    { id?, combo:{Color:'Red',Size:'S'}, sku, barcode,
         *      cost_price, selling_price, is_active, store_prices }
         *  The form's `name="variants[i][...]"` inputs pick them up at
         *  submit; `combo` posts as `variants[i][combo][Color]=Red`. */
        variants: Array.isArray(opts.variants) ? opts.variants.map((v) => ({
            id:            v.id            ?? null,
            combo:         v.combo         ?? {},
            sku:           v.sku           ?? '',
            barcode:       v.barcode       ?? '',
            cost_price:    v.cost_price    ?? '',
            selling_price: v.selling_price ?? '',
            mrp:           v.mrp           ?? '',
            is_active:     v.is_active !== false,
            store_prices:  v.store_prices  ?? {},
            _open:         true,    // body collapse state (UI only)
            _storesOpen:   false,   // per-store overrides collapse state (UI only)
        })) : [],

        /** Combo keys the user explicitly deleted — kept OUT when the
         *  matrix regenerates, so a removed combination (e.g. "red ·
         *  large" you don't stock) doesn't reappear after editing
         *  another attribute value. */
        excludedCombos: [],

        /** Parent SKU — seeds auto-generated variant SKUs (TSHIRT-RED-S). */
        parentSku: opts.parentSku ?? '',

        /** Active stores for the per-variant store-price grid:
         *  [{id, name, code}]. Empty when no stores configured. */
        stores: Array.isArray(opts.stores) ? opts.stores : [],

        /** Collapse state for the product-level per-store pricing rows
         *  (simple / kit products), keyed by store id. Seeded open in
         *  init(). UI only — not submitted. */
        storePricesOpen: {},

        /** Kit components payload — same shape as variants but lighter.
         *  Each row: { id?, component_product_id, component_variant_id, quantity }
         *  The Kit tab renders a row per entry; the Component column is
         *  a TomSelect of all eligible products, and the Variant column
         *  shows the picked product's variants (or "Any" if no variants). */
        kitItems: Array.isArray(opts.kitItems) ? opts.kitItems.map((k) => ({
            id:                   k.id                   ?? null,
            component_product_id: k.component_product_id != null ? String(k.component_product_id) : '',
            component_variant_id: k.component_variant_id != null ? String(k.component_variant_id) : '',
            quantity:             k.quantity             ?? '1',
            // Label of the currently-selected component (edit) so the remote
            // picker renders it without a fetch. Not submitted.
            component_label:      k.component_label      ?? '',
            // Effective unit price of the chosen component (variant price wins,
            // else parent) — a fallback for the auto-pricing sum when the price
            // cache doesn't have it. Not submitted.
            component_cost:       k.component_cost       ?? '',
            component_sell:       k.component_sell       ?? '',
            // Stable client-side key for x-for — lets each row's TomSelect
            // survive add/remove without getting re-bound to another row.
            _uid:                 kitUid(),
        })) : [],

        /** Lookup: productId → [{id, label}] of its variants. Provided
         *  by the controller so the kit-row Variant select can populate
         *  without an extra round-trip when the user picks a component
         *  product. */
        variantsByProduct: opts.variantsByProduct ?? {},

        /** Flips to true the moment the user clicks Save; the button
         *  shows a spinner and disables itself until the browser
         *  navigates away. The full-page redirect after a successful
         *  POST destroys this state automatically — no manual reset.
         *
         *  Used by the form's `@submit` listener — we do NOT prevent
         *  default, so the form posts normally. The state purely
         *  drives the button's visual feedback during the round-trip. */
        submitting: false,

        init() {
            // Fallback on initial load: if the product has no explicit
            // tax saved but its category has a default, surface the
            // category's default so the user sees what tax actually
            // applies. Without this the dropdown stays empty for every
            // edit where the product inherits tax from its category.
            //
            // Watcher only fires on category CHANGE so this initial
            // sweep handles the page-load case.
            if (!this.taxGroupId && this.categoryId) {
                const defaultTax = this.categoryTaxMap[String(this.categoryId)];
                if (defaultTax) this.taxGroupId = defaultTax;
            }

            // Pre-fill the tax rule from the category's default whenever
            // the user picks a different category.
            //
            // Behavior:
            //   - Category set, has a default tax → set taxGroupId = default.
            //   - Category set, no default tax    → blank taxGroupId.
            //   - Category cleared                → blank taxGroupId.
            //
            // The user can still override manually after — the field
            // is a normal select.
            this.$watch('categoryId', (newId) => {
                this.taxGroupId = this.categoryTaxMap[String(newId)] ?? '';
            });

            // Kit auto-pricing: fill cost / selling from the components sum as
            // components + quantities change — but only for a kit, and only
            // until the user hand-edits that price (then `*Overridden` latches
            // and the "Recalculate" link is the way back).
            this.$watch('componentsTotalCost', (total) => {
                if (this.isKitType && !this.costOverridden) {
                    this.cost = Math.round((total || 0) * 100) / 100;
                }
            });
            this.$watch('componentsTotalSell', (total) => {
                if (this.isKitType && !this.sellingOverridden) {
                    this.selling = Math.round((total || 0) * 100) / 100;
                }
            });

            // Make sure every existing variant has a store-price slot for
            // every store, so the grid's x-model bindings have something
            // to write into.
            this.ensureStorePriceSlots();

            // Product-level per-store rows default to expanded.
            this.stores.forEach((s) => {
                if (this.storePricesOpen[s.id] === undefined) this.storePricesOpen[s.id] = true;
            });

            // Track the parent SKU field so auto-generated variant SKUs
            // follow it on create (before any variant SKU is hand-edited).
            this.$watch('parentSku', () => {
                // no-op beyond keeping the value fresh; suggestSku reads it
            });
        },

        /** Profit margin as a percentage of the selling price.
         *  Returns 0 when selling is 0 (avoids divide-by-zero / NaN
         *  in the rendered output). */
        get margin() {
            if (!this.selling) return 0;
            return Math.round(((this.selling - this.cost) / this.selling) * 100);
        },

        /** Markup as a percentage of cost. Returns null when cost is
         *  0 so the template can render '∞' rather than misleading 0. */
        get markup() {
            if (!this.cost) return null;
            return Math.round(((this.selling - this.cost) / this.cost) * 100);
        },

        /** Per-unit profit, formatted to 2 decimals for the KPI cell. */
        get profit() {
            return (this.selling - this.cost).toFixed(2);
        },

        /** Tailwind class hooking the margin number's color into the
         *  same positive/danger/neutral thresholds used by the list. */
        get marginClass() {
            if (this.margin < 20)  return 'prod-margin-danger';
            if (this.margin >= 35) return 'prod-margin-positive';
            return '';
        },

        /** True when the user has selected the 'variant' type. Drives
         *  the Variants tab's visibility in the tab strip. */
        get isVariantType() { return this.type === 'variant'; },

        /** True when the user has selected the 'kit' type. Drives the
         *  Components tab's visibility once Step 2 ships. */
        get isKitType() { return this.type === 'kit'; },

        /* ── Attribute builder ──────────────────────────────────── */

        /** Add a blank attribute (e.g. "Color"). The matrix stays empty
         *  until the attribute has at least one value. */
        addAttribute() {
            this.variantAttributes.push({ name: '', values: [], _newValue: '', _prevName: '' });
        },

        /** Handle an attribute NAME change. Instead of letting the combo
         *  keys silently shift (which would orphan every existing variant
         *  and wipe their SKUs/prices), migrate the old attribute name to
         *  the new one across every variant combo + the excluded set, then
         *  regenerate. Renaming "Color" → "Colour" now preserves variants. */
        renameAttribute(index) {
            const attr    = this.variantAttributes[index];
            if (!attr) return;
            const oldName = (attr._prevName ?? '').trim();
            const newName = (attr.name ?? '').trim();

            if (oldName && newName && oldName !== newName) {
                // Migrate each variant's combo map (preserve key order).
                this.variants.forEach((v) => {
                    if (v.combo && Object.prototype.hasOwnProperty.call(v.combo, oldName)) {
                        const rebuilt = {};
                        Object.entries(v.combo).forEach(([k, val]) => {
                            rebuilt[k === oldName ? newName : k] = val;
                        });
                        v.combo = rebuilt;
                    }
                });
                // Migrate excluded combo keys ("Color=red|Size=S").
                this.excludedCombos = this.excludedCombos.map((key) =>
                    key.split('|').map((part) => {
                        const eq = part.indexOf('=');
                        if (eq === -1) return part;
                        const k = part.slice(0, eq);
                        return (k === oldName ? newName : k) + part.slice(eq);
                    }).join('|'),
                );
            }

            attr._prevName = newName;
            this.regenerateMatrix();
        },

        /** Remove an attribute and regenerate — dropping a dimension
         *  collapses the matrix accordingly. */
        removeAttribute(index) {
            this.variantAttributes.splice(index, 1);
            this.regenerateMatrix();
        },

        /** Commit the typed-in value for attribute `index` (case-insensitive
         *  dedupe), clear the buffer, regenerate. */
        addAttributeValue(index) {
            const attr = this.variantAttributes[index];
            if (!attr) return;
            const val = String(attr._newValue ?? '').trim();
            if (!val) return;
            if (!attr.values.some((v) => v.toLowerCase() === val.toLowerCase())) {
                attr.values.push(val);
            }
            attr._newValue = '';
            this.regenerateMatrix();
        },

        removeAttributeValue(attrIndex, valueIndex) {
            const attr = this.variantAttributes[attrIndex];
            if (!attr) return;
            attr.values.splice(valueIndex, 1);
            this.regenerateMatrix();
        },

        /** Attributes that actually contribute a dimension (named + ≥1
         *  value). The cartesian product runs over these only. */
        activeAttributes() {
            return this.variantAttributes.filter((a) => String(a.name).trim() !== '' && a.values.length > 0);
        },

        /** Stable identity for a combination — `Color=Red|Size=S`, in the
         *  current attribute order — used to merge regenerated combos with
         *  existing variant rows (preserving their id/SKU/prices). */
        comboKey(combo) {
            return this.activeAttributes()
                .map((a) => `${a.name}=${combo[a.name] ?? ''}`)
                .join('|');
        },

        /** Auto-suggested SKU for a combo: parent SKU + value tokens. */
        suggestSku(combo) {
            const base = String(this.parentSku || 'VAR').toUpperCase().replace(/\s+/g, '');
            const parts = this.activeAttributes()
                .map((a) => String(combo[a.name] ?? '').toUpperCase().replace(/[^A-Z0-9]+/g, ''));
            return [base, ...parts].filter(Boolean).join('-');
        },

        /** Human label for a combo row — "Red · S". */
        comboLabel(combo) {
            return Object.values(combo).join(' · ');
        },

        /** Recompute the variant matrix as the cartesian product of the
         *  active attributes, merging with existing rows by comboKey so
         *  SKUs / prices / ids survive value edits. Combos that no longer
         *  exist drop out (and get soft-deleted server-side on save). */
        regenerateMatrix() {
            const active = this.activeAttributes();

            let combos = [{}];
            active.forEach((attr) => {
                const next = [];
                combos.forEach((c) => {
                    attr.values.forEach((val) => next.push({ ...c, [attr.name]: val }));
                });
                combos = next;
            });
            if (active.length === 0) combos = [];

            const existing = {};
            this.variants.forEach((v) => { existing[this.comboKey(v.combo)] = v; });

            this.variants = combos.reduce((acc, combo) => {
                const key = active.map((a) => `${a.name}=${combo[a.name] ?? ''}`).join('|');
                // Respect explicit deletions — skip excluded combos.
                if (this.excludedCombos.includes(key)) return acc;
                if (existing[key]) {
                    acc.push({ ...existing[key], combo });
                } else {
                    acc.push({
                        id:            null,
                        combo,
                        sku:           this.suggestSku(combo),
                        barcode:       '',
                        cost_price:    '',
                        selling_price: '',
                        sale_price:    '',
                        mrp:           '',
                        is_active:     true,
                        store_prices:  {},
                        _open:         true,
                        _storesOpen:   false,
                    });
                }
                return acc;
            }, []);

            this.ensureStorePriceSlots();
        },

        /* ── Variant card collapse ──────────────────────────────── */

        expandAllVariants() {
            this.variants.forEach((v) => { v._open = true; });
        },

        collapseAllVariants() {
            this.variants.forEach((v) => { v._open = false; });
        },

        /** True if at least one variant card is collapsed — drives the
         *  header toggle's label (Expand all vs Collapse all). */
        get anyVariantCollapsed() {
            return this.variants.some((v) => !v._open);
        },

        /* ── Product-level per-store pricing collapse (simple/kit) ─── */

        expandAllStorePrices() {
            this.stores.forEach((s) => { this.storePricesOpen[s.id] = true; });
        },
        collapseAllStorePrices() {
            this.stores.forEach((s) => { this.storePricesOpen[s.id] = false; });
        },
        get anyStorePriceCollapsed() {
            return this.stores.some((s) => !this.storePricesOpen[s.id]);
        },

        /** Remove a single generated variant (e.g. a combination you
         *  don't stock). The combo is remembered in `excludedCombos` so
         *  it won't reappear when the matrix regenerates. */
        deleteVariant(index) {
            const v = this.variants[index];
            if (!v) return;
            const key = this.comboKey(v.combo);
            if (key && !this.excludedCombos.includes(key)) {
                this.excludedCombos.push(key);
            }
            this.variants.splice(index, 1);
        },

        /** Guarantee every variant has a {cost,selling,mrp} slot per store
         *  so the grid's x-model bindings always resolve. */
        ensureStorePriceSlots() {
            this.variants.forEach((v) => {
                if (!v.store_prices || typeof v.store_prices !== 'object') v.store_prices = {};
                this.stores.forEach((s) => {
                    if (!v.store_prices[s.id]) {
                        v.store_prices[s.id] = { cost_price: '', selling_price: '', mrp: '' };
                    }
                });
            });
        },

        /* ── Kit components ─────────────────────────────────────── */

        /** Append a fresh blank kit row. */
        addKitItem() {
            this.kitItems.push({
                id:                   null,
                component_product_id: '',
                component_variant_id: '',
                quantity:             '1',
                component_label:      '',
                component_cost:       '',
                component_sell:       '',
                _uid:                 kitUid(),
            });
        },

        removeKitItem(index) {
            this.kitItems.splice(index, 1);
        },

        /** Cache each searched category's default tax rule so the
         *  category→tax auto-fill works for server-loaded categories too
         *  (the remote category picker returns `{ value, label, tax_group_id }`). */
        cacheCategoryTax(rows) {
            if (!Array.isArray(rows)) return;
            rows.forEach((r) => {
                if (r && r.value != null) {
                    this.categoryTaxMap[String(r.value)] = r.tax_group_id ?? '';
                }
            });
        },

        /** Cache the variants returned by the product-search endpoint so
         *  the Variant sub-select can populate for remotely-loaded
         *  products (we no longer preload every product's variants). Each
         *  search result row carries `{ value, label, variants }`. */
        cacheComponentVariants(rows) {
            if (!Array.isArray(rows)) return;
            rows.forEach((r) => {
                if (r && r.value != null && Array.isArray(r.variants)) {
                    this.variantsByProduct[String(r.value)] = r.variants;
                }
            });
            this.cacheComponentPrices(rows);
        },

        /** Cache each search result's prices (product + per-variant) so the
         *  components sum can price a freshly-picked component with no extra
         *  round-trip. Keyed by `productId` and `productId:variantId`. */
        cacheComponentPrices(rows) {
            if (!Array.isArray(rows)) return;
            rows.forEach((r) => {
                if (!r || r.value == null) return;
                const pid = String(r.value);
                this.componentPriceMap[pid] = {
                    cost: parseFloat(r.cost_price) || 0,
                    sell: parseFloat(r.selling_price) || 0,
                };
                (r.variants ?? []).forEach((v) => {
                    if (v && v.id != null) {
                        this.componentPriceMap[`${pid}:${v.id}`] = {
                            cost: parseFloat(v.cost_price) || 0,
                            sell: parseFloat(v.selling_price) || 0,
                        };
                    }
                });
            });
        },

        /** Effective unit price of a kit row's chosen component. A variant price
         *  wins over the parent; falls back to the price seeded on the row. */
        _componentUnitPrice(k, which) {
            const pid = String(k.component_product_id || '');
            if (!pid) return 0;
            const vid = String(k.component_variant_id || '');
            const hit = (vid && this.componentPriceMap[`${pid}:${vid}`])
                || this.componentPriceMap[pid]
                || null;
            if (hit) return which === 'cost' ? hit.cost : hit.sell;
            // Fallback to the price the row was seeded with (edit / old input).
            return parseFloat(which === 'cost' ? k.component_cost : k.component_sell) || 0;
        },

        /** Σ (component cost × qty) across the kit. */
        get componentsTotalCost() {
            return this.kitItems.reduce(
                (sum, k) => sum + this._componentUnitPrice(k, 'cost') * (parseFloat(k.quantity) || 0), 0);
        },

        /** Σ (component selling price × qty) across the kit. */
        get componentsTotalSell() {
            return this.kitItems.reduce(
                (sum, k) => sum + this._componentUnitPrice(k, 'sell') * (parseFloat(k.quantity) || 0), 0);
        },

        get componentsTotalCostFormatted() { return this._money(this.componentsTotalCost); },
        get componentsTotalSellFormatted() { return this._money(this.componentsTotalSell); },

        _money(n) {
            return window.posFormatMoney ? window.posFormatMoney(n) : (parseFloat(n) || 0).toFixed(2);
        },

        /** User hand-edited a price → stop auto-filling that field. */
        markCostOverridden()    { this.costOverridden = true; },
        markSellingOverridden() { this.sellingOverridden = true; },

        /** Snap both prices back to the components sum (the "Recalculate" link). */
        recalcKitPrice() {
            this.cost    = Math.round(this.componentsTotalCost * 100) / 100;
            this.selling = Math.round(this.componentsTotalSell * 100) / 100;
            this.costOverridden    = false;
            this.sellingOverridden = false;
        },

        /** Variants available for a given kit row's currently picked
         *  component product. Returns [] when the component has no
         *  variants (a simple SKU), in which case the Variant select
         *  collapses to the "Any / parent SKU" sentinel. */
        variantsFor(componentId) {
            if (!componentId) return [];
            return this.variantsByProduct[String(componentId)] ?? [];
        },
    };
}
