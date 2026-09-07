/**
 * System-wide phone-input upgrade — wraps every phone-flavoured input
 * with `intl-tel-input` (flag dropdown + dial code + per-country
 * validation/formatting via libphonenumber).
 *
 * Detection signals match form-validators.js so the two layers stay
 * in sync — change one, change the other:
 *   - <input type="tel">
 *   - inputmode="tel"
 *   - class "js-phone"
 *   - name in PHONE_NAMES
 *
 * Default country comes from the `<meta name="pos-default-country">`
 * tag the admin layout renders (lowercase ISO code expected by the
 * library). Falls back to 'in'.
 *
 * On submit: the input value is overwritten with the full E.164
 * number, so the server receives e.g. `+919012345678` regardless of
 * how the user typed it. The server-side PhoneNormalizer already
 * accepts +-prefixed strings; nothing else changes.
 *
 * Dynamically-added inputs (Alpine x-for repeaters) are picked up via
 * a MutationObserver scoped to <body>, debounced to avoid thrash.
 */

import intlTelInput from 'intl-tel-input/intlTelInputWithUtils';
import 'intl-tel-input/styles';

const PHONE_NAMES = ['phone', 'mobile', 'whatsapp_phone', 'contact_phone', 'work_phone'];

function isPhoneInput(el) {
    if (!(el instanceof HTMLInputElement)) return false;
    if (el.dataset.itiAttached === '1') return false;
    if (el.hasAttribute('data-no-iti')) return false;
    if (el.type === 'tel') return true;
    if (el.getAttribute('inputmode') === 'tel') return true;
    if (el.classList.contains('js-phone')) return true;
    if (PHONE_NAMES.includes(el.name)) return true;
    return false;
}

function defaultCountry() {
    const v = document.querySelector('meta[name="pos-default-country"]')?.content;
    return (v && v.trim()) || 'in';
}

function attach(el) {
    if (!isPhoneInput(el)) return;

    // Mark before init so re-entrant MutationObserver fires don't
    // double-wrap if the lib mutates the DOM around our input.
    el.dataset.itiAttached = '1';
    el.type = 'tel'; // libphonenumber utils want this for keyboard hints

    const iti = intlTelInput(el, {
        initialCountry:           defaultCountry(),
        // Show E.164 placeholder so users get a hint of the expected shape.
        autoPlaceholder:          'aggressive',
        // The dropdown gets a search box; "United Kingdom" hits faster than scrolling.
        countrySearch:            true,
        // Drop a flag-only column on narrow forms — saves ~50px and
        // matches the .pos-input height.
        separateDialCode:         false,
        // Keep flexible matching: users can paste E.164 and the lib
        // auto-detects the country from the dial code.
        nationalMode:             false,
        formatOnDisplay:          true,
    });

    // Cache the iti instance on the element for later access (e.g.
    // by form-validators.js — we use iti.isValidNumber instead of
    // the regex when the upgrade is in place).
    el._iti = iti;

    // On the owning form's submit, swap the visible national value
    // for the E.164 form so the server receives the canonical number.
    // Skip when no value was entered (don't post '+' alone).
    const form = el.closest('form');
    if (form && !form.dataset.itiHookInstalled) {
        form.dataset.itiHookInstalled = '1';
        form.addEventListener('submit', () => {
            form.querySelectorAll('input[data-iti-attached="1"]').forEach((input) => {
                const inst = input._iti;
                if (!inst) return;
                const raw = (input.value || '').trim();
                if (raw === '') return;
                const full = inst.getNumber();
                if (full) input.value = full;
            });
        }, { capture: false });
    }
}

function scan(root = document) {
    root.querySelectorAll('input').forEach(attach);
}

/** Register the global phone-input upgrade + dynamic input observer. */
export function registerPhoneInputs() {
    // Initial sweep.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => scan(), { once: true });
    } else {
        scan();
    }

    // Watch for late-arriving inputs (Alpine x-for, modal templates, etc).
    // Debounced so a 20-row repeater doesn't run scan() 20 times.
    let queued = false;
    const observer = new MutationObserver(() => {
        if (queued) return;
        queued = true;
        queueMicrotask(() => { queued = false; scan(); });
    });
    observer.observe(document.body, { childList: true, subtree: true });
}
