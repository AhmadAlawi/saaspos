/**
 * System-wide client-side validators for email + phone inputs.
 *
 * On every full-page form submit we sweep the form for:
 *   - `<input type="email">` with a non-empty value
 *   - phone-flavoured inputs: `inputmode="tel"`, `type="tel"`,
 *     class `js-phone`, or name in PHONE_NAMES
 *
 * Each value is checked against a practical regex. Server-side
 * validation remains authoritative — this exists purely to catch the
 * obvious typos before the round-trip.
 *
 * Failures are batched and shown via the global toast store as a
 * single multi-line toast (matches feedback-validation-errors memory:
 * NO inline error text under fields). The field's `.field` parent gets
 * a `.has-error` class so the red ring shows; clearing happens on
 * input.
 *
 * Opt-out hooks:
 *   - `data-no-validate` on the form or input — skipped entirely
 *   - the form already calling `preventDefault()` (Alpine `@submit.prevent`,
 *     AJAX forms) is skipped — we listen in the capture phase and run
 *     BEFORE submit-loader, but never on already-prevented submits.
 *
 * Why capture phase: so we can `preventDefault()` *before* the
 * submit-loader hooks attach a spinner. Submit-loader runs in the
 * bubble phase and now sees `defaultPrevented = true` → no spinner.
 */

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

// Practical phone match — strip separators then require 7-15 digits
// with an optional leading +. Tight enough to catch typos, loose
// enough to accept any real number worldwide.
const PHONE_DIGITS_RE = /^\+?\d{7,15}$/;
const PHONE_SEPARATORS_RE = /[\s\-().]/g;

// Field names that are always phone-flavoured even without an inputmode.
const PHONE_NAMES = new Set(['phone', 'mobile', 'whatsapp_phone', 'contact_phone', 'work_phone']);

function isPhoneInput(el) {
    if (el.type === 'tel') return true;
    if (el.getAttribute('inputmode') === 'tel') return true;
    if (el.classList.contains('js-phone')) return true;
    if (PHONE_NAMES.has(el.name)) return true;
    return false;
}

function fieldLabelFor(input) {
    // The wrapping `.field` label's `.field-label` is the canonical
    // label across this app. Fall back to placeholder / name.
    const wrapper = input.closest('.field');
    const labelEl = wrapper?.querySelector('.field-label');
    const txt = (labelEl?.textContent || '').trim();
    return txt || input.getAttribute('placeholder') || input.name || 'Field';
}

function markError(input, on) {
    const wrapper = input.closest('.field');
    if (!wrapper) return;
    wrapper.classList.toggle('has-error', !!on);
}

function pushToast(messages) {
    const store = window.Alpine?.store?.('toasts');
    if (store && typeof store.push === 'function') {
        store.push({ type: 'error', messages, duration: 0 });
        return;
    }
    // Toast store not ready — fall back to a single alert so the user
    // isn't left guessing. Should virtually never fire because admin.js
    // registers stores before any form is rendered.
    // eslint-disable-next-line no-alert
    alert(messages.join('\n'));
}

/** Clears the wrapper error mark when the user fixes the value. */
function bindClearOnInput(input) {
    if (input.dataset.fmvBound) return;
    input.dataset.fmvBound = '1';
    input.addEventListener('input', () => markError(input, false));
}

export function registerFormValidators() {
    document.addEventListener('submit', (e) => {
        if (e.defaultPrevented) return;

        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.hasAttribute('data-no-validate')) return;

        const errors = [];
        const offenders = [];

        // Emails.
        form.querySelectorAll('input[type="email"]').forEach((el) => {
            if (el.disabled || el.hasAttribute('data-no-validate')) return;
            const v = (el.value || '').trim();
            if (v === '') { markError(el, false); return; }
            if (!EMAIL_RE.test(v)) {
                errors.push(`${fieldLabelFor(el)}: invalid email address`);
                offenders.push(el);
            } else {
                markError(el, false);
            }
        });

        // Phones (anywhere it looks like a phone field).
        // When intl-tel-input is attached we delegate to its
        // `isValidNumber()` (libphonenumber-backed); else fall back to
        // the loose regex below.
        form.querySelectorAll('input').forEach((el) => {
            if (el.disabled || el.hasAttribute('data-no-validate')) return;
            if (!isPhoneInput(el)) return;
            const raw = (el.value || '').trim();
            if (raw === '') { markError(el, false); return; }

            const iti = el._iti;
            let ok;
            if (iti && typeof iti.isValidNumber === 'function') {
                ok = iti.isValidNumber();
            } else {
                const cleaned = raw.replace(PHONE_SEPARATORS_RE, '');
                ok = PHONE_DIGITS_RE.test(cleaned);
            }

            if (!ok) {
                errors.push(`${fieldLabelFor(el)}: invalid phone number`);
                offenders.push(el);
            } else {
                markError(el, false);
            }
        });

        if (errors.length === 0) return;

        e.preventDefault();
        e.stopPropagation();

        offenders.forEach((el) => { markError(el, true); bindClearOnInput(el); });
        // Focus the first offender so keyboard users land on it.
        offenders[0]?.focus({ preventScroll: false });

        pushToast(errors);
    }, true); // capture phase — run before submit-loader
}
