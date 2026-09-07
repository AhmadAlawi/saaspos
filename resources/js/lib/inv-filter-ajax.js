/**
 * AJAX filtering for the system-wide `.inv-filter` server forms — no full
 * page reload on search / filter change / reset.
 *
 * Every list screen (Sales, Purchases, every Inventory report, Sync log,
 * payments, …) renders the same shape:
 *
 *   <div x-data="dataTable({ rowsSelector: 'tbody > tr[data-dt-row]' })">
 *     <div class="card card-pad-0">
 *       <div class="dt-toolbar"> … <x-admin.dt-toolbar-actions/> </div>
 *       <form class="inv-filter" method="GET" action="…"> … </form>
 *       @if (empty) <div class="dt-empty">…</div>
 *       @else       <table>… <tr data-dt-row> …</table> <x-admin.dt-pager/>
 *     </div>
 *   </div>
 *
 * On any filter change we fetch the new URL and swap only the nodes that
 * follow the form inside its card (the table / empty-state / pager + the
 * row-count title). The form and the Alpine data-table component wrapping it
 * are left untouched, so the search box keeps focus, any client-side
 * `x-model="search"` binding stays live, and the swap works whether the
 * result set is empty or not (the server renders whichever and we drop it
 * straight in). One request; no dependency on the data-table's tbody-only
 * refresh (which bailed to a full reload when the table was empty).
 *
 * Live-search (Enter / debounced typing) and date pickers already go
 * through `requestSubmit()`, so the single submit interceptor covers them;
 * selects using the legacy inline `onchange="this.form.submit()"` bypass
 * the submit event, so we strip that and listen for `change` ourselves.
 *
 * Self-managed forms — those with their own Alpine `@submit.prevent`
 * handler (Customers, Suppliers) — are left untouched.
 */

/** A form is self-managed when the page wires its own submit handler. */
function isSelfManaged(form) {
    return form.getAttributeNames().some(
        (a) => a === '@submit' || a.startsWith('@submit.') || a.startsWith('x-on:submit'),
    );
}

function eligible(form) {
    return form instanceof HTMLFormElement
        && form.classList.contains('inv-filter')
        && !isSelfManaged(form);
}

/** Build the index URL from the form's current field values (empties dropped). */
function buildUrl(form, withValues) {
    // Strip any existing query so Reset (withValues=false) always lands on a
    // clean URL with no leftover ?q=/?status= params.
    const action = (form.getAttribute('action') || window.location.pathname).split('?')[0];
    if (!withValues) return action;
    const params = new URLSearchParams();
    new FormData(form).forEach((v, k) => {
        const s = (v ?? '').toString().trim();
        if (s !== '') params.set(k, s);
    });
    const qs = params.toString();
    return action + (qs ? '?' + qs : '');
}

/**
 * Fetch `url` and swap ONLY the nodes that follow the filter form inside its
 * card (the table / empty-state / pager). The form — and the Alpine
 * data-table component wrapping it — are left untouched, so the search box
 * keeps focus + any client-side `x-model="search"` binding stays live, and
 * the swap works whether the result set is empty or not. When `resetControls`
 * is set, the controls are first re-synced to the server defaults.
 */
async function refresh(form, url, resetControls = false) {
    const container = form.parentElement;          // the .card.card-pad-0
    if (!container || !window.posGetHtml) { window.location.href = url; return; }

    let html;
    try {
        html = await window.posGetHtml(url);
    } catch {
        window.location.href = url;
        return;
    }
    if (!html) { window.location.href = url; return; }

    const doc       = new DOMParser().parseFromString(html, 'text/html');
    const freshForm = doc.querySelector('form.inv-filter');
    if (!freshForm) { window.location.href = url; return; }

    // ── 1) Swap the table region — the critical step, identical to the
    //       apply path so reset behaves exactly like a working filter. ──

    // Refresh the row-count title (it sits in the toolbar, before the form).
    const liveTitle  = container.querySelector('.dt-toolbar-title');
    const freshTitle = freshForm.parentElement?.querySelector('.dt-toolbar-title');
    if (liveTitle && freshTitle) liveTitle.innerHTML = freshTitle.innerHTML;

    // Refresh the summary/KPI cards (they live above the filter card and show
    // numbers computed from the SAME filtered query). Plain HTML — no Alpine —
    // so an innerHTML swap is enough; no re-init needed.
    const liveCards  = document.querySelector('[data-summary-cards]');
    const freshCards = doc.querySelector('[data-summary-cards]');
    if (liveCards && freshCards) liveCards.innerHTML = freshCards.innerHTML;

    // Import the fresh nodes that follow the form (table / empty state).
    //
    // The client-side pager (`.dt-pager`) is DELIBERATELY skipped: its
    // `page` / `totalPages` bindings live in the data-table Alpine scope on
    // a parent element. Tearing it down + re-creating it via `initTree`
    // re-binds those effects outside that scope, which then throw
    // "page is not defined" when they re-run. Instead we leave the live
    // pager untouched and let the data-table mixin re-paginate it
    // reactively on the `dt:recollect` below.
    const isPager = (n) => n.classList?.contains('dt-pager');

    const newNodes = [];
    for (let n = freshForm.nextElementSibling; n; n = n.nextElementSibling) {
        if (isPager(n)) continue;
        newNodes.push(document.importNode(n, true));
    }

    // Remove the current post-form siblings (except the live pager), and
    // remember the pager so we can insert the fresh table before it.
    let livePager = null;
    for (let n = form.nextElementSibling; n;) {
        const next = n.nextElementSibling;
        if (isPager(n)) { livePager = n; } else { n.remove(); }
        n = next;
    }
    if (newNodes.length) {
        if (livePager) livePager.before(...newNodes);
        else form.after(...newNodes);
        if (window.Alpine?.initTree) {
            newNodes.forEach((node) => {
                // Defensive: a transient binding-eval error during re-init
                // must never become an Uncaught error that aborts the swap.
                try { window.Alpine.initTree(node); } catch { /* noop */ }
            });
        }
    }

    // Let the data-table mixin (wherever its x-data lives — the card or the
    // page-wide root) re-scan the new rows. Bubbles up to its listener.
    form.dispatchEvent(new CustomEvent('dt:recollect', { bubbles: true }));

    // ── 2) Re-sync the SELECT controls to the server defaults (Reset only).
    //       Cosmetic + guarded: it must never abort the swap above. ──
    if (resetControls) {
        try { syncControls(form, freshForm); } catch { /* noop */ }
    }
}

/** Reset the SELECT controls to the freshly rendered server defaults. */
function syncControls(form, fresh) {
    form.querySelectorAll('select').forEach((sel) => {
        try {
            if (!sel.name) return;
            const f = fresh.querySelector(`select[name="${CSS.escape(sel.name)}"]`);
            if (!f) return;
            const val = f.value;
            if (sel.tomselect) sel.tomselect.setValue(val, true);
            else sel.value = val;
        } catch { /* skip this control, keep resetting the rest */ }
    });
}

// True while a reset is in flight. Clearing the controls (and the later
// syncControls re-sync) can emit `change` events on TomSelect-backed
// filters; without this guard those changes would fire a SECOND, concurrent
// applyForm whose swap races the reset's swap — leaving a half-initialised
// pager node (the "page is not defined" error). The flag is cleared only
// once the reset's refresh fully settles.
let suppressChange = false;

function applyForm(form) {
    const url = buildUrl(form, true);
    window.history.replaceState(null, '', url);
    refresh(form, url, false);
}

function resetForm(form) {
    suppressChange = true;
    // Clear the text / search / date inputs SYNCHRONOUSLY (before the async
    // refresh) for two reasons:
    //   - a still-pending live-search debounce timer would otherwise read the
    //     old value and re-apply ?q= (the "needs two clicks" bug), and
    //   - a client-side `x-model="search"` box (no `name`, e.g. Low stock)
    //     needs an `input` event to actually reset its Alpine model, or the
    //     freshly loaded rows stay filtered out.
    form.querySelectorAll('input').forEach((inp) => {
        if (inp.type === 'hidden') return;
        if (inp._flatpickr) { inp._flatpickr.clear(); return; }
        inp.value = '';
        // Only nameless (client-side) inputs get an input event — dispatching
        // on a named search box would re-trigger the live-search submit.
        if (!inp.name) inp.dispatchEvent(new Event('input', { bubbles: true }));
    });

    // Clear the SELECT controls synchronously too. Without this, a
    // TomSelect/remoteSelect picker (e.g. the Sales customer filter) keeps
    // its value through the first reset, so the first click reads the stale
    // selection and the table only clears on the second click. `true` =
    // silent, so we don't re-fire the change→applyForm listener. The fetch
    // below already ignores values (buildUrl false), and syncControls
    // re-confirms each control against the freshly rendered defaults.
    form.querySelectorAll('select').forEach((sel) => {
        try {
            if (sel.tomselect) sel.tomselect.setValue('', true);
            else sel.value = '';
        } catch { /* skip this control, keep resetting the rest */ }
    });

    const url = buildUrl(form, false);
    window.history.replaceState(null, '', url);
    // Clear the guard only AFTER the refresh (incl. syncControls) settles,
    // so no change fired during the reset can spawn a competing applyForm.
    Promise.resolve(refresh(form, url, true)).finally(() => { suppressChange = false; });
}

export function registerInvFilterAjax() {
    const stripInlineSubmits = () => {
        document.querySelectorAll('form.inv-filter select').forEach((sel) => {
            const form = sel.form;
            if (!form || !eligible(form)) return;
            // Legacy inline `onchange="this.form.submit()"` bypasses the submit
            // event (and reloads). Drop it; the delegated change listener
            // below drives the AJAX refresh instead.
            sel.removeAttribute('onchange');
            sel.onchange = null;
        });
    };

    if (document.readyState !== 'loading') stripInlineSubmits();
    else document.addEventListener('DOMContentLoaded', stripInlineSubmits);

    // Submit — covers Enter, live-search debounce, and date pickers (all use
    // requestSubmit(), which fires a bubbling submit event).
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!eligible(form)) return;
        e.preventDefault();
        applyForm(form);
    });

    // Select changes (now that the inline onchange is stripped).
    document.addEventListener('change', (e) => {
        if (suppressChange) return;   // a reset is clearing controls — don't re-apply
        const el = e.target;
        if (!(el instanceof HTMLSelectElement)) return;
        const form = el.form;
        if (!form || !eligible(form)) return;
        applyForm(form);
    });

    // Reset link / button inside the filter form.
    document.addEventListener('click', (e) => {
        const reset = e.target.closest?.('.inv-filter-reset');
        if (!reset) return;
        const form = reset.closest('form.inv-filter');
        if (!form || !eligible(form)) return;
        e.preventDefault();
        resetForm(form);
    });
}
