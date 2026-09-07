import TomSelect from 'tom-select';
// Built-in plugin: moves the search input from the trigger field into
// the dropdown panel header. Without this, users have to discover that
// the trigger itself doubles as a text input — not obvious.
import 'tom-select/dist/js/plugins/dropdown_input.js';
import { posGet } from '../lib/http.js';

/**
 * Alpine factory that upgrades a native <select> with TomSelect.
 *
 * Drop it on any styled <select class="pos-input"> to add:
 *   - search-as-you-type (no library setup needed)
 *   - capped dropdown — only N options render at once; the rest
 *     appear once the user filters them down (good for lists with
 *     hundreds of rows)
 *   - keyboard nav, ARIA, indented option text preserved
 *
 *   <select x-data="enhancedSelect()" name="parent_id" x-model="form.parent_id">
 *     <option value="">…</option>
 *     <option value="5">Snacks</option>
 *   </select>
 *
 * Default `maxOptions` is 20 — change via opts:
 *   x-data="enhancedSelect({ maxOptions: 50 })"
 *
 * The closed-state UI is themed in resources/css/components/select.css
 * to look pixel-identical to a native `select.pos-input`, so visually
 * nothing changes; only the open dropdown gets the search input.
 */
export function enhancedSelect(overrides = {}) {
    // `value` is OUR option (not a TomSelect config key): the initial value
    // to display. Needed because an x-model bound to JS state (e.g. an
    // address row's country, a customer group on the edit screen) is set on
    // the native <select> but TomSelect won't reflect it on its own — so we
    // push it in explicitly after init. Server-rendered <option selected>
    // works without this; this covers the JS-driven cases.
    const { value: initialValue, ...tsOverrides } = overrides;

    return {
        ts: null,

        init() {
            if (this.$el.tomselect) return;                  // idempotent

            // Multi-select picks up the `remove_button` plugin so each
            // chip ships with an × control. Single-select skips it (a
            // chip-style × on the single value would be visual noise).
            const isMulti = this.$el.multiple || this.$el.hasAttribute('multiple');
            const plugins = isMulti
                ? ['dropdown_input', 'remove_button']
                : ['dropdown_input'];

            // A select that lives inside a horizontal-scroll table wrapper
            // (`.dt-scroll`, e.g. the purchase-line Tax column) can't let its
            // dropdown escape: `overflow-x: auto` makes the browser clip the
            // Y axis too (CSS spec — a non-`visible` axis forces the other to
            // compute as `auto`), so the panel gets chopped off. Portaling the
            // panel to <body> lifts it out of that clip. Skip if the caller
            // already passed an explicit dropdownParent.
            const inScrollWrap = !!this.$el.closest('.dt-scroll');
            const usingBodyParent =
                tsOverrides.dropdownParent === 'body' ||
                (inScrollWrap && tsOverrides.dropdownParent === undefined);

            const config = {
                allowEmptyOption: true,
                maxOptions:       20,                        // cap the visible list
                searchField:      ['text'],                  // search the option label
                sortField:        { field: '$order' },       // preserve server-rendered order
                plugins,
                placeholder:      'Search…',                 // shown in the dropdown's search input
                ...(usingBodyParent ? { dropdownParent: 'body' } : {}),
                ...tsOverrides,
            };

            config.render = { ...(config.render || {}) };

            // Options carrying a `data-due` (e.g. the customer-payment picker
            // showing each customer's outstanding balance) get that suffix
            // rendered in a distinct colour so the owed amount stands out from
            // the name. The `·` separator stays default-coloured.
            const dueSuffix = (data, escape) => data.due
                ? ` · <span class="ts-opt-due">${escape(data.due)}</span>`
                : '';
            const hasDue = Array.from(this.$el.options).some((o) => o.dataset.due !== undefined);

            // Auto-detect tree selects: any option with data-depth gets
            // a custom renderer that applies CSS-based indentation instead
            // of the unreliable &nbsp; approach (TomSelect trims leading
            // whitespace from option text during initialization).
            const hasTree = Array.from(this.$el.options).some((o) => o.dataset.depth !== undefined);
            if (hasTree && !config.render.option) {
                config.render.option = (data, escape) => {
                    const depth  = parseInt(data.depth ?? 0, 10);
                    const padPx  = depth > 0 ? depth * 20 + 10 : 10;
                    const arrow  = depth > 0 ? '<span class="ts-tree-arrow">↳</span> ' : '';
                    return `<div class="option" style="padding-inline-start:${padPx}px">${arrow}${escape(data.text)}</div>`;
                };
            } else if (hasDue && !config.render.option) {
                config.render.option = (data, escape) =>
                    `<div class="option">${escape(data.text)}${dueSuffix(data, escape)}</div>`;
            }

            // Show the full label on hover. The closed control truncates in
            // narrow columns (e.g. the purchase items' Tax column), so a
            // title attribute surfaces the complete text. The due suffix (if
            // any) is mirrored onto the selected item so the control matches
            // the dropdown row.
            if (!config.render.item) {
                config.render.item = (data, escape) => {
                    const text = data.text ?? '';
                    return `<div class="item" title="${escape(text)}">${escape(text)}${dueSuffix(data, escape)}</div>`;
                };
            }

            this.ts = new TomSelect(this.$el, config);

            // Reflect a JS-driven initial value (x-model bound to state) that
            // TomSelect can't read off the native <select> on its own. Silent
            // (second arg) so it doesn't fire a change and clobber x-model.
            if (initialValue !== undefined && initialValue !== null && initialValue !== '') {
                this.ts.setValue(String(initialValue), true);
            }

            // Flip the dropdown UP when there isn't room below. A select near
            // the page bottom (e.g. the last field on a form) otherwise opens
            // downward past the viewport, and the browser grows the scroll
            // area to fit the absolutely-positioned panel — leaving a blank
            // gap below the footer. `.ts-dropdown-up` repositions it above.
            const ts = this.ts;
            const decideDirection = () => {
                const rect = ts.control.getBoundingClientRect();
                const ddH  = ts.dropdown?.offsetHeight || 260;
                const below = window.innerHeight - rect.bottom;
                const above = rect.top;
                const flipUp = below < ddH && above > below;

                if (usingBodyParent) {
                    // The panel is portaled to <body> and positioned by
                    // TomSelect's positionDropdown() (always below the control).
                    // Override the inline `top` to flip it above when there's no
                    // room below, keeping it viewport-anchored to the control.
                    // Spacing is baked into `top`, so drop the CSS margin-top.
                    ts.dropdown.style.marginTop = '0';
                    ts.dropdown.style.top = flipUp
                        ? `${rect.top + window.scrollY - ddH - 4}px`
                        : `${rect.bottom + window.scrollY + 4}px`;
                    return;
                }

                ts.wrapper.classList.toggle('ts-dropdown-up', flipUp);
            };
            ts.on('dropdown_open', () => requestAnimationFrame(decideDirection));
            ts.on('dropdown_close', () => ts.wrapper.classList.remove('ts-dropdown-up'));

            // Mirror the selected label onto the control as a title so the
            // full text shows on hover when it's ellipsis-truncated in a
            // narrow field (e.g. the purchase items' Tax column).
            const syncTitle = () => {
                const item = ts.control.querySelector('.item');
                ts.control.title = item ? item.textContent.trim() : '';
            };
            ts.on('change', syncTitle);
            syncTitle();

            // Options rendered by a child `<template x-for>` do NOT exist yet:
            // Alpine initialises this element's own directives before it walks
            // the subtree, so TomSelect just snapshotted an empty list and the
            // dropdown would open permanently empty. Re-read once the children
            // have rendered. A no-op for server-rendered options.
            this.$nextTick(() => {
                if (!this.ts) return;
                this.ts.sync();
                // x-model wrote its value into a select that had no matching
                // option at the time; re-apply it now the options exist.
                // Silent, so it doesn't write back through x-model and loop.
                const v = this.$el.value;
                if (v && String(this.ts.getValue()) !== String(v)) {
                    this.ts.setValue(v, true);
                }
            });
        },

        /**
         * Re-read the native <select>'s options and re-apply a value.
         *
         * TomSelect snapshots the option list at init. When the options are
         * rendered by an Alpine `<template x-for>` (refund reasons, the shift
         * gate's terminals, a kit line's variants…) they either don't exist
         * yet at init, or they change later — and the dropdown would keep
         * showing the stale set. `ts.sync()` re-reads options + optgroups +
         * the disabled/readonly state straight off the element.
         *
         * Drive it from `x-effect`, touching the source array so Alpine
         * re-runs it whenever the options change:
         *
         *   <select x-data="enhancedSelect()"
         *           x-effect="refundReasons.length, syncOptions(refundReasonId)"
         *           x-model.number="refundReasonId">
         *
         * `setValue(…, true)` is silent — it skips TomSelect's change callback,
         * which would write back through x-model and start a reactive loop.
         */
        syncOptions(value) {
            if (!this.ts) return;
            this.ts.sync();
            const v = (value === null || value === undefined) ? '' : String(value);
            if (String(this.ts.getValue()) !== v) {
                this.ts.setValue(v, true);
            }
        },

        destroy() {
            this.ts?.destroy();
            this.ts = null;
        },
    };
}

/**
 * Remote (server-backed) searchable select — the system-wide pattern for
 * picking one row from a large table (products, customers, suppliers, …)
 * WITHOUT preloading the whole list into the page.
 *
 *   - Loads the first page (≈25 rows) only when the field is focused.
 *   - Searches server-side as the user types, debounced (`loadThrottle`).
 *   - Seeds the currently-selected row's label so edit screens render the
 *     chosen value instantly, no fetch required.
 *
 * The endpoint must return a JSON array of `{ value, label, ... }`
 * objects (extra fields are preserved and passed to `onResults`).
 *
 *   <select x-data="remoteSelect({
 *               url: '{{ route('admin.products.search') }}',
 *               value: k.component_product_id,   // current value (edit)
 *               label: k.component_label,        // current label (edit)
 *               exclude: 5,                       // optional id to omit
 *               onResults: (rows) => cacheSomething(rows),
 *           })"
 *           x-model="k.component_product_id"
 *           :name="`kit_items[${i}][component_product_id]`"
 *           class="pos-input"></select>
 *
 * Closed-state styling is identical to `enhancedSelect` (see select.css),
 * so remote and local selects look the same across the system.
 */
export function remoteSelect(opts = {}) {
    return {
        ts: null,

        init() {
            if (this.$el.tomselect) return;

            const config = {
                valueField:   'value',
                labelField:   'label',
                // Empty on purpose: the server already filtered these rows
                // (by name/sku/barcode for products). A non-empty
                // searchField makes TomSelect's own sifter re-filter the
                // server's response against that field before ever adding
                // it to `options` — confirmed via a standalone repro that a
                // barcode match gets silently dropped because the display
                // label ("Name (SKU)") never contains the barcode. Empty
                // searchField skips that re-filter entirely and trusts the
                // server's result set as-is (verified: does NOT mean "match
                // nothing" here, despite that being the tempting reading of
                // the option name — confirmed with a live repro before and
                // after this exact change).
                searchField:  [],
                loadThrottle: opts.throttle ?? 300,       // debounce: one request per typing pause
                maxOptions:   opts.maxOptions ?? 25,
                preload:      'focus',                    // fetch the first page on focus, not page load
                plugins:      ['dropdown_input'],
                placeholder:  opts.placeholder ?? 'Search…',
                allowEmptyOption: true,
                // `dropdownParent: 'body'` portals the dropdown panel out
                // of its host so it escapes modal/overflow:hidden ancestors.
                // Used by the delete-with-move modal. Default unchanged for
                // inline form selects.
                ...(opts.dropdownParent ? { dropdownParent: opts.dropdownParent } : {}),
                load: (query, callback) => {
                    const params = {};
                    if (query) params.q = query;
                    if (opts.exclude) params.exclude = opts.exclude;

                    posGet(opts.url, Object.keys(params).length ? params : null)
                        .then(({ data }) => {
                            const list = Array.isArray(data) ? data : [];
                            if (typeof opts.onResults === 'function') opts.onResults(list);
                            callback(list);
                        })
                        .catch(() => callback());
                },
            };

            // Seed the current selection so an edit screen shows its label
            // without waiting for a fetch.
            if (opts.value !== undefined && opts.value !== null && opts.value !== '' && opts.label) {
                config.options = [{ value: String(opts.value), label: String(opts.label) }];
                config.items   = [String(opts.value)];
            }

            this.ts = new TomSelect(this.$el, config);
        },

        destroy() {
            this.ts?.destroy();
            this.ts = null;
        },
    };
}
