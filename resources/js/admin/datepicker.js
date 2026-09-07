import flatpickr from 'flatpickr';

/**
 * Flatpickr bootstrap — upgrades every `<input class="js-datepicker">`
 * on the page into a themed date picker.
 *
 *   <input type="text" class="pos-input js-datepicker" name="expiry_date">
 *
 * Why not an Alpine `x-data` factory: subtle ordering issues with
 * nested x-data scopes (the input lives inside `productEditor`'s
 * scope) meant the factory's `init()` wasn't reliably firing in
 * every browser. A plain `DOMContentLoaded` scan is dumber but
 * never races Alpine's directive pipeline.
 *
 * Re-scan on each call so dynamically-inserted inputs (e.g. via
 * AJAX swap) pick up the picker too. `flatpickr()` is idempotent —
 * an input already wrapped gets its existing instance back.
 *
 * Every picker also gets a YEAR DROPDOWN (system-wide convention) —
 * see {@link attachYearDropdown}: flatpickr's default year input
 * stays typeable, and a small chevron button beside it opens an
 * overlay list of years for fast jumps.
 */

/**
 * Mount a visible year-picker dropdown next to flatpickr's year input.
 *
 * The year input itself stays typeable (flatpickr's default). The
 * chevron button below opens a scrollable popover of selectable years
 * (current − 60 to current + 5). Picking a year calls
 * `instance.changeYear(y)` and the calendar re-renders.
 *
 * Implementation note: an earlier version used `<datalist>` on the
 * year input — the native dropdown chevron is invisible on
 * `type="number"` inputs because the spinner arrows occupy the same
 * slot. The explicit button + popover here is unambiguous on every
 * engine.
 */
function attachYearDropdown(instance) {
    const yearInput = instance.currentYearElement;
    if (!yearInput || yearInput.dataset.fpYearMounted === '1') return;
    yearInput.dataset.fpYearMounted = '1';

    const wrap = yearInput.parentNode;             // .numInputWrapper
    if (!wrap) return;

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'fp-year-chevron';
    btn.setAttribute('aria-label', 'Pick year');
    btn.tabIndex = -1;
    btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="10" height="10"><path d="m6 9 6 6 6-6"/></svg>';

    const list = document.createElement('div');
    list.className = 'fp-year-list';
    list.hidden = true;

    const buildOptions = () => {
        list.innerHTML = '';
        const current = instance.currentYear;
        for (let y = current + 5; y >= current - 60; y--) {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'fp-year-opt';
            if (y === current) item.classList.add('is-active');
            item.textContent = String(y);
            item.addEventListener('click', (e) => {
                e.preventDefault();
                instance.changeYear(y);
                list.hidden = true;
                btn.classList.remove('is-open');
            });
            list.appendChild(item);
        }
        const active = list.querySelector('.fp-year-opt.is-active');
        if (active) active.scrollIntoView({ block: 'center' });
    };

    btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const opening = list.hidden;
        if (opening) buildOptions();
        list.hidden = !opening;
        btn.classList.toggle('is-open', opening);
    });

    // Close when clicking anywhere else in the calendar.
    instance.calendarContainer.addEventListener('click', (e) => {
        if (e.target === btn || btn.contains(e.target) || list.contains(e.target)) return;
        list.hidden = true;
        btn.classList.remove('is-open');
    });

    wrap.appendChild(btn);
    wrap.appendChild(list);
    wrap.classList.add('fp-year-with-dropdown-wrap');
}

/**
 * Mount a themed month-picker that replaces flatpickr's native
 * `<select>` dropdown (which shows the browser's raw, unstyleable
 * list). Same chevron + popover pattern as `attachYearDropdown`.
 *
 * The native `<select>` stays in the DOM (hidden) — flatpickr reads
 * from it on internal state changes. We update its value when the
 * user picks a month so flatpickr's existing event flow still fires.
 */
function attachMonthDropdown(instance) {
    const sel = instance.calendarContainer?.querySelector('.flatpickr-monthDropdown-months');
    if (!sel || sel.dataset.fpMonthMounted === '1') return;
    sel.dataset.fpMonthMounted = '1';

    const months = instance.l10n.months.longhand;
    const header = sel.parentNode;
    if (!header) return;

    sel.classList.add('fp-month-native-hidden');

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'fp-month-btn';
    btn.tabIndex = -1;
    btn.innerHTML =
        '<span class="fp-month-label"></span>' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="10" height="10"><path d="m6 9 6 6 6-6"/></svg>';
    const label = btn.querySelector('.fp-month-label');

    const list = document.createElement('div');
    list.className = 'fp-month-list';
    list.hidden = true;

    const syncLabel = () => {
        label.textContent = months[instance.currentMonth] ?? '';
    };

    const buildOptions = () => {
        list.innerHTML = '';
        months.forEach((name, i) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'fp-month-opt';
            if (i === instance.currentMonth) item.classList.add('is-active');
            item.textContent = name;
            item.addEventListener('click', (e) => {
                e.preventDefault();
                instance.changeMonth(i, false);
                syncLabel();
                list.hidden = true;
                btn.classList.remove('is-open');
            });
            list.appendChild(item);
        });
    };

    btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const opening = list.hidden;
        if (opening) buildOptions();
        list.hidden = !opening;
        btn.classList.toggle('is-open', opening);
    });

    instance.calendarContainer.addEventListener('click', (e) => {
        if (e.target === btn || btn.contains(e.target) || list.contains(e.target)) return;
        list.hidden = true;
        btn.classList.remove('is-open');
    });

    // Keep our label in sync when the user navigates via < > arrows
    // or changes the year (flatpickr re-renders the calendar then).
    instance.config.onMonthChange.push(syncLabel);
    instance.config.onYearChange.push(syncLabel);
    syncLabel();

    header.insertBefore(btn, sel);
    header.appendChild(list);
    header.classList.add('fp-month-with-dropdown-wrap');
}

export function registerDatepickers() {
    const apply = () => {
        document.querySelectorAll('input.js-datepicker:not([data-fp-mounted])').forEach((el) => {
            const config = {
                dateFormat:    'Y-m-d',
                allowInput:    true,
                disableMobile: true,
                weekNumbers:   false,
                onReady: [(_dates, _str, instance) => {
                    attachYearDropdown(instance);
                    attachMonthDropdown(instance);
                }],
            };
            if (el.dataset.minDate) config.minDate = el.dataset.minDate;
            if (el.dataset.maxDate) config.maxDate = el.dataset.maxDate;

            // Filter-form usage: opt into auto-submit-on-pick so the
            // page reloads with the new range as soon as the user
            // chooses a date, no extra "Apply" button needed.
            //   <input … data-fp-submit-on-change="1">
            if (el.dataset.fpSubmitOnChange === '1') {
                // `requestSubmit()` (not `submit()`) so that any
                // `@submit.prevent` handler on the form runs — pages
                // that AJAX-refresh their table on filter change rely
                // on the submit event firing. Falls back to .submit()
                // on the rare browser that lacks requestSubmit.
                const submitForm = (form) => {
                    if (!form) return;
                    if (typeof form.requestSubmit === 'function') form.requestSubmit();
                    else form.submit();
                };
                config.onChange = (_dates, _str, instance) => {
                    submitForm(instance.input.form);
                };
                // Manual-clear path: when the user select-all-deletes
                // the field (or hits the keyboard Backspace until
                // empty), flatpickr doesn't reliably fire onChange
                // because it never re-parses an empty string. Catch
                // the blur-with-empty-value case ourselves so the
                // filter resets without making the cashier hunt for
                // the "Reset filter" button.
                //
                // Guard: only submit when the corresponding query
                // param is currently set — otherwise blurring an
                // empty field on a fresh page would submit-loop.
                el.addEventListener('blur', () => {
                    if (el.value !== '' || !el.name) return;
                    const params = new URLSearchParams(window.location.search);
                    const prev   = params.get(el.name);
                    if (prev !== null && prev !== '') {
                        submitForm(el.form);
                    }
                });
            }

            flatpickr(el, config);
            el.setAttribute('data-fp-mounted', '1');
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', apply, { once: true });
    } else {
        apply();
    }

    // Watch the body for any new `.js-datepicker` inputs added
    // dynamically (Alpine x-for, AJAX swap, modal mount, etc.) and
    // upgrade them too. `apply()` is idempotent — it filters out
    // already-mounted inputs via `:not([data-fp-mounted])` — so
    // re-running on every batch of mutations is safe and cheap.
    //
    // Debounce via rAF so a single Alpine cycle that inserts many
    // rows triggers only one re-scan instead of one per node.
    const observer = new MutationObserver(() => {
        if (observer._scheduled) return;
        observer._scheduled = true;
        requestAnimationFrame(() => {
            observer._scheduled = false;
            apply();
        });
    });
    const start = () => {
        if (document.body) observer.observe(document.body, { childList: true, subtree: true });
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
}
