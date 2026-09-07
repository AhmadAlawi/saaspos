/**
 * Global `data-ajax-form` handler — the system-wide opt-in that
 * converts a synchronous Laravel form into an AJAX one with one
 * attribute.
 *
 *   <form method="POST" action="..." data-ajax-form>
 *
 * On submit:
 *   - intercepted; default POST cancelled
 *   - posted via axios (CSRF, 419, 401, 5xx handled by lib/http.js)
 *   - 2xx response → success toast (from `data.message`) + follow
 *     `data.redirect` if set
 *   - 422 response → `.has-error` painted on every field named in
 *     `errors`, multi-line error toast
 *   - the submit button gets `disabled` + an `is-submitting` class so
 *     existing button-spinner CSS lights up
 *
 * Controllers respond like:
 *   if ($request->wantsJson()) {
 *       return response()->json(['message' => __('…'), 'redirect' => route('…')]);
 *   }
 *
 * Non-AJAX entry points (tests, legacy code) still get the classic
 * redirect path — this is purely additive.
 *
 * Opt-out hooks:
 *   - omit `data-ajax-form` from a form to keep its synchronous POST
 *   - add `data-no-ajax` to override an inherited `data-ajax-form`
 *     (rare; useful for one-off cases that need a full reload)
 */

import { posPost, applyValidationErrors, flattenErrorMessages } from './http.js';

function pushToastSuccess(message) {
    const store = window.Alpine?.store?.('toasts');
    if (store?.push && message) {
        store.push({ type: 'success', message });
    }
}

function pushToastValidation(errors) {
    const store = window.Alpine?.store?.('toasts');
    if (! store?.push) return;
    const messages = flattenErrorMessages(errors);
    if (messages.length === 0) return;
    store.push({
        type:     'error',
        title:    'Please review the form',
        messages,
        duration: 0,
    });
}

/** Same spinner markup the global submit-loader prepends. Kept in
 *  sync with `resources/js/lib/submit-loader.js`. */
const SPINNER_SVG =
    '<svg class="animate-spin pos-btn-spin h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">'
    + '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>'
    + '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>'
    + '</svg>';

/** Identify *the* button the user just submitted with. We can't trust
 *  the global submit-loader to add the spinner because our handler
 *  preventDefault's synchronously before its bubble-phase listener
 *  runs. So we replicate its behaviour here. */
function findSubmitButton(formEl, event) {
    if (event?.submitter instanceof HTMLElement) return event.submitter;
    return formEl.querySelector('button[type="submit"], input[type="submit"], button:not([type])');
}

function applySpinner(btn) {
    if (!btn || btn.dataset.loading === '1') return;
    // Icon-only buttons (no label text) look broken with a spinner —
    // mirror submit-loader's rule.
    if ((btn.textContent || '').trim() === '' && btn.tagName === 'BUTTON') return;
    if (btn.querySelector?.('.animate-spin')) return;
    btn.dataset.loading = '1';
    btn.classList.add('is-loading', 'is-submitting');
    btn.insertAdjacentHTML('afterbegin', SPINNER_SVG);
}

function removeSpinner(btn) {
    if (!btn) return;
    btn.dataset.loading = '0';
    btn.classList.remove('is-loading', 'is-submitting');
    btn.querySelector?.('.pos-btn-spin')?.remove();
}

function setSubmittingState(formEl, submitting, btn = null) {
    formEl.dataset.ajaxSubmitting = submitting ? '1' : '0';
    const target = btn || formEl._ajaxSubmitBtn || findSubmitButton(formEl);
    if (!target) return;
    formEl._ajaxSubmitBtn = submitting ? target : null;
    target.disabled = submitting;
    if (submitting) applySpinner(target);
    else removeSpinner(target);
}

/** Dispatch a custom event so per-form Alpine wrappers can reset their
 *  own state (`submitting`, button labels) once the AJAX cycle ends.
 *  Listen in Blade with `@ajax-form:end="submitting = false"`. */
function emitFormEvent(formEl, name, detail = {}) {
    formEl.dispatchEvent(new CustomEvent(name, { detail, bubbles: true }));
}

async function handleSubmit(formEl, event) {
    event.preventDefault();
    if (formEl.dataset.ajaxSubmitting === '1') return;
    const btn = findSubmitButton(formEl, event);
    setSubmittingState(formEl, true, btn);
    emitFormEvent(formEl, 'ajax-form:start');

    // Clear previous validation marks before re-submitting.
    formEl.querySelectorAll('.field.has-error').forEach((el) => el.classList.remove('has-error'));

    try {
        // CRITICAL: always send multipart/x-www-form-urlencoded forms as
        // POST with the spoofed `_method` left in the body. PHP only
        // parses request body into `$_POST`/`$_FILES` when the actual
        // HTTP method is POST — sending PATCH/PUT/DELETE with FormData
        // gives Laravel an empty payload and every required field looks
        // missing. Laravel's MethodOverride middleware reads `_method`
        // from the body and re-flags the request as PATCH/PUT/DELETE
        // for routing + validation purposes.
        const fd = new FormData(formEl);

        const { data } = await posPost(formEl.action, fd);

        if (data?.message) {
            pushToastSuccess(data.message);
        }
        if (data?.redirect) {
            // Tiny delay so the success toast paints before navigation
            // — purely cosmetic.
            setTimeout(() => { window.location.href = data.redirect; }, 50);
        } else {
            // No redirect → the page stays. Drop submitting state so
            // the user can re-submit (e.g. inline-edit pickers).
            setSubmittingState(formEl, false);
            emitFormEvent(formEl, 'ajax-form:end', { ok: true, data });
        }
    } catch (e) {
        setSubmittingState(formEl, false);
        emitFormEvent(formEl, 'ajax-form:end', { ok: false, error: e });
        if (e?.status === 422) {
            applyValidationErrors(formEl, e.errors || {});
            pushToastValidation(e.errors || {});
            // Focus the first errored field for keyboard users.
            const first = formEl.querySelector('.field.has-error input, .field.has-error select, .field.has-error textarea');
            first?.focus({ preventScroll: false });
        }
        // 5xx already toasted globally. Network errors fall through silently.
    }
}

/** Install the global submit listener. Safe to call once at boot. */
export function registerAjaxForms() {
    document.addEventListener('submit', (event) => {
        const formEl = event.target;
        if (! (formEl instanceof HTMLFormElement)) return;
        if (! formEl.hasAttribute('data-ajax-form')) return;
        if (formEl.hasAttribute('data-no-ajax')) return;
        // A factory that already prevent-default's via `@submit.prevent`
        // (e.g. the supplier-payment form's manual `submit($el)` path)
        // owns the submit — don't double-handle.
        if (event.defaultPrevented) return;

        handleSubmit(formEl, event);
    });
}
