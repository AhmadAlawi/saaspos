/**
 * Global submit-button loader.
 *
 * On any real (full-page) form submission, the triggering submit button
 * gets a spinner prepended *before its label* (e.g. "⟳ Save changes") and
 * is disabled — so every form across the system shows progress and is
 * protected from double-submits, with zero per-button markup. The label
 * stays visible; the button's own leading icon (if any) is hidden by CSS
 * so the spinner takes its place.
 *
 * Deliberately skips:
 *   - AJAX / Alpine forms that handle their own submit (`@submit.prevent`)
 *     — detected via `e.defaultPrevented` (we run in the bubble phase,
 *     after form-level handlers, so a prevented submit is already flagged).
 *   - Icon-only buttons (no visible text label) — a spinner crammed next
 *     to a lone icon looks broken, so these never get a loader.
 *   - Buttons that ship their own spinner (an `.animate-spin` element) —
 *     so bespoke loaders (confirm dialog, login, etc.) never double up.
 *   - Anything opting out with `data-no-loader` on the form or button.
 *
 * The button is disabled on the next tick so its name/value (if any) is
 * still included in the submitted payload.
 */
export function registerSubmitLoader() {
    document.addEventListener('submit', (e) => {
        // A handler called preventDefault → this form manages itself.
        if (e.defaultPrevented) return;

        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.hasAttribute('data-no-loader')) return;

        const btn = e.submitter
            || form.querySelector('button[type="submit"], button:not([type])');
        if (!btn || btn.hasAttribute('data-no-loader')) return;

        // Icon-only buttons (no visible text label — e.g. row clone/toggle
        // actions) never get a loader; a spinner beside a lone icon looks
        // broken. Detected by empty trimmed text content.
        if ((btn.textContent || '').trim() === '') return;

        // Button already manages its own spinner — leave it alone.
        if (typeof btn.querySelector === 'function' && btn.querySelector('.animate-spin')) return;
        if (btn.dataset.loading === '1') return;

        btn.dataset.loading = '1';
        btn.classList.add('is-loading');
        if (!btn.querySelector('.pos-btn-spin')) {
            // Same spinner markup as the bespoke buttons (Tailwind
            // animate-spin + currentColor), prepended before the label.
            btn.insertAdjacentHTML('afterbegin',
                '<svg class="animate-spin pos-btn-spin h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">'
                + '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>'
                + '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>'
                + '</svg>');
        }
        // Disable on the next tick so the button's name/value still posts.
        setTimeout(() => { btn.disabled = true; }, 0);
    });
}
