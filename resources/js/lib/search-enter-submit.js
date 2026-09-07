/**
 * Force Enter-to-submit on every `<input type="search">`.
 *
 * The system-wide filter pattern (used by Customers, Suppliers, every
 * Inventory screen, etc.) is a GET form with a `<input type="search"
 * name="q">` and no visible submit button — relying on the browser's
 * implicit-submission rule (Enter submits when the form has exactly
 * one text-like input).
 *
 * That implicit submission **disappears** as soon as
 * `enhancedSelect()` wraps another field in the same form: TomSelect
 * injects its own `<input type="text">` for in-dropdown search,
 * pushing the form past "exactly one text-like input." The visible
 * search input stops responding to Enter.
 *
 * Symptom: typing into the search box and pressing Enter does
 * nothing. Clicking a non-existent submit button is the only way to
 * filter — but there isn't one.
 *
 * This handler is the unconditional fix: any Enter inside a
 * `<input type="search">` submits the owning form, period.
 *
 * Skips:
 *   - inputs with `data-no-enter-submit` (opt-out per input)
 *   - inputs with IME composition in progress (`isComposing` —
 *     Japanese/Chinese/Korean users finalising a character)
 *   - the case where the form is not present (free-floating input)
 */
export function registerSearchEnterSubmit() {
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter' || e.isComposing) return;

        const target = e.target;
        if (!(target instanceof HTMLInputElement)) return;
        if (target.type !== 'search') return;
        if (target.hasAttribute('data-no-enter-submit')) return;
        // No `name` → client-side filter box (e.g. `x-model="search"`); there's
        // nothing to submit server-side, and doing so would wipe the filter.
        if (!target.name) return;

        const form = target.form;
        if (!form) return;

        e.preventDefault();
        // `requestSubmit` fires the submit event so the validators /
        // submit-loader / phone-input hooks still get to run. Falls
        // back to `submit()` on older engines that don't ship it.
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    });
}
