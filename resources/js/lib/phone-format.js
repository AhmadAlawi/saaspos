/**
 * Presentation formatter for stored phone numbers — the JS twin of
 * App\Support\PhoneFormatter::pretty(). Numbers are stored as E.164
 * (digits with an optional leading `+`, e.g. `+916359302924`); for display
 * we want a space between the country calling code and the national number
 * (`+91 6359302924`).
 *
 * ITU calling codes are a prefix-free set, so a greedy 1- / 2- / 3-digit
 * match is unambiguous. Numbers without a leading `+` (country unknown) are
 * returned unchanged. Keep this in sync with the PHP formatter.
 *
 * Exposes `window.posFormatPhone(value)` and the Alpine magic `$formatPhone`.
 */

const ONE_DIGIT = ['1', '7'];
const TWO_DIGIT = new Set([
    '20', '27', '30', '31', '32', '33', '34', '36', '39', '40', '41', '43',
    '44', '45', '46', '47', '48', '49', '51', '52', '53', '54', '55', '56',
    '57', '58', '60', '61', '62', '63', '64', '65', '66', '81', '82', '84',
    '86', '90', '91', '92', '93', '94', '95', '98',
]);

function callingCodeLength(digits) {
    if (ONE_DIGIT.includes(digits.slice(0, 1))) return 1;
    if (TWO_DIGIT.has(digits.slice(0, 2)))      return 2;
    return Math.min(3, digits.length);
}

export function posFormatPhone(value) {
    const v = (value ?? '').toString().trim();
    if (v === '') return '';
    if (!v.startsWith('+')) return v;        // country unknown — leave as-is

    const digits = v.slice(1);
    if (!/^\d+$/.test(digits)) return v;     // not clean E.164 — leave as-is

    const ccLen = callingCodeLength(digits);
    const cc    = digits.slice(0, ccLen);
    const rest  = digits.slice(ccLen);

    return rest === '' ? `+${cc}` : `+${cc} ${rest}`;
}

export function registerFormatPhone(Alpine) {
    window.posFormatPhone = posFormatPhone;
    if (Alpine?.magic) {
        Alpine.magic('formatPhone', () => posFormatPhone);
    }
}
