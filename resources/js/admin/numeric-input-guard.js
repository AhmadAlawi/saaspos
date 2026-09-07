/**
 * System-wide guard for numeric `.pos-input` fields.
 *
 * Forbids:
 *   - Leading minus sign (negative values)
 *   - 'e' / 'E' (scientific notation — confuses cashiers, accountants, etc.)
 *   - Pasted text that resolves to a negative number
 *
 * Why not just `min="0"`: the HTML5 attribute prevents form *submission*
 * with an invalid value but doesn't stop the user from TYPING a negative
 * number — they see `-12` in the field until they tab away. This guard
 * blocks the offending keystroke / paste up-front so the field never
 * displays an invalid value in the first place.
 *
 * Auto-registered against every `<input type="number">` that carries the
 * `.pos-input` class — covers the entire admin form surface without
 * each page wiring it up individually.
 */
export function registerNumericInputGuard() {
    const matches = (el) =>
        el instanceof HTMLInputElement
        && el.type === 'number'
        && el.classList.contains('pos-input');

    // Block the offending keystrokes BEFORE the value lands in the field.
    document.addEventListener('keydown', (e) => {
        if (! matches(e.target)) return;
        if (e.key === '-' || e.key === '+' || e.key === 'e' || e.key === 'E') {
            e.preventDefault();
        }
    });

    // Catch paste / autofill that bypasses keydown. If the resulting
    // numeric value is negative, snap to 0 so reports / arithmetic
    // downstream never see a negative price / quantity. If it exceeds the
    // field's declared `max` (e.g. a barcode pasted/scanned into a qty
    // field — 13 digits vs a DECIMAL(15,4) column), clear it: clamping
    // would silently store a wrong huge number, so reject and let them retype.
    document.addEventListener('input', (e) => {
        if (! matches(e.target)) return;
        const v = e.target.value;
        if (v === '' || v === '-' || v === '.') return;
        const n = parseFloat(v);
        if (! Number.isFinite(n)) return;
        if (n < 0) {
            e.target.value = '0';
            return;
        }
        const max = parseFloat(e.target.getAttribute('max') ?? '');
        if (Number.isFinite(max) && n > max) {
            e.target.value = '';
        }
    });

    // Wheel scrolling on a focused number input quietly mutates its
    // value — annoying for accidentally-hovered fields. Blur on wheel
    // so the value can't change without a deliberate click first.
    document.addEventListener('wheel', (e) => {
        if (! matches(e.target)) return;
        if (document.activeElement === e.target) e.target.blur();
    }, { passive: true });
}
