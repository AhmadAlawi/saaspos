/**
 * Programmatically POST to `action` via axios, with Laravel's CSRF token
 * (added by the http.js interceptor) plus any extra fields. The general-
 * purpose sibling of `submitDeleteForm` for confirm-driven actions like
 * Post / Approve / Send / Publish.
 *
 * Typically called from a confirm dialog's `onConfirm` — either directly
 * or via the `$submitForm` Alpine magic:
 *   onConfirm: () => $submitForm('/admin/inventory/adjustments/7/post')
 *
 * Behaviour:
 *   - 2xx with `data.message` → success toast
 *   - 2xx with `data.redirect` → follow the redirect after a tiny delay
 *     (so the toast paints)
 *   - 2xx without redirect → reload the current page so server-side
 *     state changes (e.g. document moved from draft → posted) reflect
 *   - 422 → show the server message as an error toast
 *   - 5xx → http.js already toasts; we resolve silently
 *
 * Returns a Promise that resolves once the action completes. For the
 * confirm-dialog case the dialog can `await` this and its spinner stays
 * up across the round-trip.
 *
 * @param {string} action — form action URL
 * @param {Record<string, string|number>} [fields] — extra fields merged
 *        into the FormData ({_method: 'PATCH', ...})
 */
import { posPost } from './http.js';

export async function submitForm(action, fields = {}) {
    const fd = new FormData();
    for (const [k, v] of Object.entries(fields)) {
        fd.append(k, String(v));
    }

    let data;
    try {
        ({ data } = await posPost(action, fd));
    } catch (e) {
        const store = window.Alpine?.store?.('toasts');
        if (e?.status === 422 && store?.push) {
            store.push({ type: 'error', message: e?.message || 'Action failed.' });
        }
        // 5xx already surfaced globally by lib/http.js.
        // Re-throw so the confirm dialog can hold its spinner / surface the failure.
        throw e;
    }

    const store = window.Alpine?.store?.('toasts');
    if (data?.message && store?.push) {
        store.push({ type: 'success', message: data.message });
    }

    if (data?.redirect) {
        setTimeout(() => { window.location.href = data.redirect; }, 50);
        // Hold the caller (confirm-dialog spinner) pending until navigation.
        return new Promise(() => {});
    }

    // No redirect → reload so the page reflects the new server state.
    window.location.reload();
    return new Promise(() => {});
}
