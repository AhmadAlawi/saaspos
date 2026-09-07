/**
 * Programmatically delete via axios (Laravel `_method=DELETE` spoof
 * over POST + CSRF — same wire shape as a classic form, but no full
 * reload). Used by list-table delete buttons so the server's
 * `jsonOrRedirect` flow handles success and domain errors (e.g.
 * "product has sales", "last store") uniformly.
 *
 * Typically called from a confirm dialog's `onConfirm` — either directly
 * or via the `$deleteForm` Alpine magic:
 *   onConfirm: () => $deleteForm('/admin/stores/7')
 *
 * Follows the same response contract as `submitForm`:
 *   - 2xx with redirect → navigate
 *   - 2xx without redirect → reload (so the row disappears from the list)
 *   - 422 → toast the server message
 *   - 5xx → http.js auto-toasts
 */
import { submitForm } from './submit-form.js';
import { posPost } from './http.js';

export function submitDeleteForm(action) {
    return submitForm(action, { _method: 'DELETE' });
}

/**
 * Row-aware DELETE — for list tables that should remove the deleted
 * row in place instead of reloading the page. The button is expected
 * to live inside the `<tr>` (or any element matching `rowSelector`,
 * default `[data-dt-row]`). On 2xx the closest matching ancestor is
 * removed from the DOM and the host's `refreshDataTable()` is called
 * so paginator/empty-state stay in sync. The server's `data.redirect`
 * is intentionally IGNORED — the whole point is "no page navigation".
 *
 *   onConfirm: () => $deleteRow('/admin/suppliers/7', $event.target)
 *
 * Errors fall through to the same toast pipeline as `$deleteForm`.
 */
export async function submitDeleteRow(action, eventTarget, rowSelector = '[data-dt-row]') {
    const fd = new FormData();
    fd.append('_method', 'DELETE');

    let data;
    try {
        ({ data } = await posPost(action, fd));
    } catch (e) {
        const store = window.Alpine?.store?.('toasts');
        if (e?.status === 422 && store?.push) {
            store.push({ type: 'error', message: e?.message || 'Action failed.' });
        }
        throw e;
    }

    const store = window.Alpine?.store?.('toasts');
    if (data?.message && store?.push) {
        store.push({ type: 'success', message: data.message });
    }

    // Find + drop the row. The event target may be the icon inside the
    // button (Lucide SVG); walk up to the row via closest().
    const row = eventTarget?.closest?.(rowSelector);
    if (row) {
        const host = row.closest('[x-data]');
        row.remove();
        // If the table's Alpine host exposes `refreshDataTable` (every
        // dataTable() factory does), call it so the pager + count + the
        // "no rows" empty state recompute against the new DOM.
        const data = host?.__x?.$data;
        if (data && typeof data.refreshDataTable === 'function') {
            data.refreshDataTable();
        }
    }
}
