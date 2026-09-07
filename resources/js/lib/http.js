/**
 * Shared HTTP client (axios) — THE one and only way admin JS talks to
 * the server. No raw `fetch()` calls in factory code, no per-page
 * boilerplate for CSRF or JSON headers. Interceptors centralise:
 *
 *   - CSRF token injection (re-read on every request — survives token
 *     rotation between submissions on a long-lived page)
 *   - `Accept: application/json` + `X-Requested-With: XMLHttpRequest`
 *     (Laravel uses the latter to flip routes into JSON mode)
 *   - 401 → bounce to /login
 *   - 419 → CSRF expired, reload the page (Laravel rotated the token
 *     and our session is now stale)
 *   - 422 → unwrap Laravel's validation error envelope into
 *     `{field: [msg, …]}` and attach it to a uniform error shape
 *   - 5xx → push a generic toast so the user is never silent-failed
 *
 * Public surface:
 *   posGet(url, params?, opts?)   → Promise<{data, status}>
 *   posPost(url, body?, opts?)    → Promise<{data, status}>
 *   posPatch(url, body?, opts?)   → Promise<{data, status}>
 *   posPut(url, body?, opts?)     → Promise<{data, status}>
 *   posDelete(url, opts?)         → Promise<{data, status}>
 *
 * Bodies: pass a plain object for JSON. Pass a `FormData` instance
 * for file uploads — axios auto-detects and serialises with the right
 * Content-Type.
 *
 * Errors: every failure resolves to a thrown shape:
 *   { status, message, errors, raw }
 *   - `status`   — HTTP status (0 if network/abort)
 *   - `message`  — friendly message ready to show
 *   - `errors`   — `{field: [msg, …]}` on 422, `{}` otherwise
 *   - `raw`      — the original axios error for advanced uses
 *
 * The interceptors auto-toast 5xx and silently-redirect 401/419, so
 * the calling page only has to handle 200-ish and 422 explicitly.
 */

import axios from 'axios';

const csrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.content || '';

const http = axios.create({
    baseURL: '/',
    timeout: 30000,
    headers: {
        'Accept':           'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
    withCredentials: true,
});

/* ── Request interceptor: re-read CSRF on every send ─────── */
http.interceptors.request.use((config) => {
    const token = csrfToken();
    if (token) {
        config.headers['X-CSRF-TOKEN'] = token;
        // Laravel also accepts `_token` in the body for POST/PUT/PATCH.
        // Useful when the request is form-urlencoded.
    }
    return config;
});

/* ── Response interceptor: normalise errors ──────────────── */
http.interceptors.response.use(
    (response) => response,
    (error) => {
        const status   = error.response?.status ?? 0;
        const data     = error.response?.data   ?? {};
        const message  = data.message
            ?? (status === 0 ? 'Network error — check your connection.' : `Request failed (${status}).`);
        const errors   = status === 422 && data.errors ? data.errors : {};

        // 401 — auth expired. Redirect to login. Skipping toast since
        // we're about to navigate.
        if (status === 401) {
            const here = encodeURIComponent(window.location.pathname + window.location.search);
            window.location.href = `/login?next=${here}`;
            return Promise.reject({ status, message, errors, raw: error });
        }

        // 419 — CSRF/session expired. The cleanest recovery is a hard
        // reload: it pulls a fresh token AND brings the page back to a
        // known-good state. The user re-submits.
        if (status === 419) {
            window.location.reload();
            return Promise.reject({ status, message, errors, raw: error });
        }

        // 5xx — server-side blow-up. Always toast so the user isn't
        // silently failed. Pages can still inspect the rejection.
        if (status >= 500) {
            const store = window.Alpine?.store?.('toasts');
            if (store?.push) {
                store.push({ type: 'error', message, duration: 6000 });
            }
        }

        return Promise.reject({ status, message, errors, raw: error });
    },
);

/* ── Public surface ──────────────────────────────────────── */

export function posGet(url, params = null, opts = {}) {
    return http.request({ method: 'get', url, params, ...opts });
}
export function posPost(url, body = null, opts = {}) {
    return http.request({ method: 'post', url, data: body, ...opts });
}
export function posPatch(url, body = null, opts = {}) {
    return http.request({ method: 'patch', url, data: body, ...opts });
}
export function posPut(url, body = null, opts = {}) {
    return http.request({ method: 'put', url, data: body, ...opts });
}
export function posDelete(url, opts = {}) {
    return http.request({ method: 'delete', url, ...opts });
}

/** Expose the raw instance for the rare case a factory needs full
 *  axios control (request cancellation tokens, custom transformers). */
export { http };

/**
 * Fetch a page's full HTML for DOM-swapping (e.g. the AJAX table refresh).
 * Uses native fetch — NOT the JSON axios instance — because sending
 * `Accept: application/json` / `X-Requested-With: XMLHttpRequest` would
 * cause Laravel to return a JSON redirect or 401 instead of an HTML page.
 * Returns the response text, or null on network/HTTP error.
 */
export async function posGetHtml(url) {
    try {
        const r = await fetch(url, {
            method: 'GET',
            headers: { 'Accept': 'text/html' },
            credentials: 'same-origin',
        });
        return r.ok ? r.text() : null;
    } catch {
        return null;
    }
}

/**
 * Apply Laravel-style validation errors to a form, mimicking what the
 * existing system-wide form-validators.js + `.has-error` CSS produces
 * when SSR re-renders a failed form. Pages that drive their own forms
 * can call this after catching a 422.
 *
 *   try { await posPost(url, data) }
 *   catch (e) {
 *     if (e.status === 422) {
 *       applyValidationErrors(formEl, e.errors)
 *       toastErrors(e.errors)
 *     }
 *   }
 */
export function applyValidationErrors(formEl, errors) {
    if (!formEl || !errors) return;

    formEl.querySelectorAll('.has-error').forEach((el) => {
        el.classList.remove('has-error');
    });

    for (const fieldName of Object.keys(errors)) {
        // Laravel reports nested keys in dot notation (items.0.unit_cost);
        // form inputs use bracket notation (items[0][unit_cost]).
        const bracket = dotToBracketName(fieldName);

        // `data-error-for` lets a VISIBLE input claim a field whose actual
        // submitted value lives in a hidden input (e.g. the stock-adjustment
        // quantity, shown as `quantity_abs` but submitted as a signed
        // `quantity_delta`). Checked first so the highlight lands on the
        // field the user can actually see.
        const input = formEl.querySelector(`[data-error-for="${fieldName}"]`)
            || formEl.querySelector(`[data-error-for="${bracket}"]`)
            || formEl.querySelector(`[name="${fieldName}"]`)
            || formEl.querySelector(`[name="${bracket}"]`);
        if (!input) continue;

        // Prefer the `.field` wrapper (paints `.pos-input` + label red).
        // Table-cell inputs have no wrapper, so mark the control itself.
        const field = input.closest('.field');
        if (field) {
            field.classList.add('has-error');
        } else {
            input.classList.add('has-error');
        }
    }
}

/** `items.0.unit_cost` → `items[0][unit_cost]` (matches form input names). */
function dotToBracketName(name) {
    const parts = name.split('.');
    if (parts.length === 1) return name;
    return parts[0] + parts.slice(1).map((p) => `[${p}]`).join('');
}

/**
 * Flatten Laravel's `{field: [msg, …]}` shape into one array of strings
 * ready to push as a multi-line toast.
 */
export function flattenErrorMessages(errors) {
    return Object.values(errors || {}).flat().filter(Boolean);
}

/** Install the helpers as window globals + Alpine magics. */
export function registerHttpClient(Alpine) {
    window.posGet     = posGet;
    window.posPost    = posPost;
    window.posPatch   = posPatch;
    window.posPut     = posPut;
    window.posDelete  = posDelete;
    window.posHttp    = http;
    window.posGetHtml = posGetHtml;

    if (Alpine?.magic) {
        // Inside any x-data scope: $http.post(url, data), etc.
        Alpine.magic('http', () => ({
            get:    posGet,
            post:   posPost,
            patch:  posPatch,
            put:    posPut,
            delete: posDelete,
            raw:    http,
            applyValidationErrors,
            flattenErrorMessages,
        }));
    }
}
