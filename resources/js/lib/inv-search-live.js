/**
 * Live-as-you-type search for `.inv-search` filter inputs.
 *
 * Any `<input>` inside an element with class `inv-search` (the system
 * filter pattern used by Customers, Suppliers, every Inventory screen,
 * etc.) auto-submits its form after the user pauses typing.
 *
 * Companion to [[search-enter-submit]] — Enter still works for
 * keyboard users; this just makes the same query fire automatically.
 *
 * Debounce window: 400 ms. Long enough to avoid a server hit per
 * keystroke; short enough to feel instant. Tuned with intl-tel-input
 * users in mind — the dropdown's search-within-list is local and
 * doesn't traverse this handler.
 *
 * Skips:
 *   - inputs with `data-no-live-search` (opt-out per input)
 *   - empty-to-empty transitions when nothing was previously submitted
 *     (e.g. clearing back to '' when the URL has no `q` already) —
 *     avoids a needless reload when the user clears the box
 *   - IME composition in progress (`isComposing` — CJK typing)
 *
 * Implementation:
 *   - Listens on `input` (capture phase), not `keyup` — captures paste
 *     and programmatic value changes too.
 *   - Uses a Map<form, timeoutId> so multiple inputs in the same form
 *     coalesce into one submit.
 */

const DEBOUNCE_MS = 400;
const timers = new WeakMap();

function lastSubmittedValue(form, input) {
    // Read the value that was originally rendered into the input.
    // If the current value equals that, the form already represents
    // what's on the server — no need to resubmit.
    return input.defaultValue ?? '';
}

function shouldSkip(input) {
    if (input.hasAttribute('data-no-live-search')) return true;
    if (input.disabled) return true;
    // No `name` → it's a client-side filter box (e.g. an `x-model="search"`
    // data-table search). Submitting the form does nothing useful and would
    // wipe the client-side filter, so leave it alone.
    if (!input.name) return true;
    // Inside the intl-tel-input dropdown's own search input?
    // That one isn't ours to auto-submit.
    if (input.closest('.iti__dropdown-content')) return true;
    return false;
}

export function registerInvSearchLive() {
    document.addEventListener('input', (e) => {
        const target = e.target;
        if (!(target instanceof HTMLInputElement)) return;
        if (e.isComposing) return;

        // Only inputs nested inside `.inv-search`.
        if (!target.closest('.inv-search')) return;
        if (shouldSkip(target)) return;

        const form = target.form;
        if (!form) return;

        // Idle → empty cleanup. If the input is empty AND it was already
        // empty server-side (no `q` was rendered into it), there's
        // nothing to do — skip the wasted round-trip.
        const current  = (target.value || '').trim();
        const previous = (lastSubmittedValue(form, target) || '').trim();
        if (current === '' && previous === '') return;

        const prev = timers.get(form);
        if (prev) clearTimeout(prev);

        timers.set(form, setTimeout(() => {
            timers.delete(form);
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        }, DEBOUNCE_MS));
    }, true);
}
