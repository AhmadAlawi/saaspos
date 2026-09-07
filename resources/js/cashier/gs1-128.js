/**
 * Client-side GS1-128 decoder for the offline cashier.
 *
 * Mirrors the server App\Services\Barcodes\Gs128Parser so a supermarket
 * weighing-scale label (GTIN + weight + price + expiry + batch packed
 * into one barcode) resolves at checkout even when offline. Returns
 * `null` for anything that isn't GS1-128 so the caller falls through to
 * the normal barcode/SKU/scale lookups.
 *
 * See docs/features/hardware.md §7.4-7.5.
 */

const GS = String.fromCharCode(29); // FNC1 group separator

const FIXED_2 = { '00': 18, '01': 14, '02': 14, 11: 6, 12: 6, 13: 6, 15: 6, 16: 6, 17: 6, 20: 2 };
const VARIABLE_2 = ['10', '21', '22', '30', '37'];
const MEASURE_3 = ['310', '311', '312', '313', '314', '315', '316'];
const AMOUNT_3 = ['390', '392'];
const AMOUNT_CURRENCY_3 = ['391', '393'];

function stripSymbology(s) {
    if (s.startsWith(']C1')) return s.slice(3);
    return s.replace(new RegExp(`^${GS}+`), '');
}

function matchAi(s, i) {
    const two = s.substr(i, 2);
    const three = s.substr(i, 3);
    const four = s.substr(i, 4);

    if (MEASURE_3.includes(three) && four.length === 4) return [four, 4, 6];
    if (AMOUNT_3.includes(three) && four.length === 4) return [four, 4, null];
    if (AMOUNT_CURRENCY_3.includes(three) && four.length === 4) return [four, 4, null];
    if (Object.prototype.hasOwnProperty.call(FIXED_2, two)) return [two, 2, FIXED_2[two]];
    if (VARIABLE_2.includes(two)) return [two, 2, null];
    return [null, 0, null];
}

function walk(s) {
    const ais = {};
    let i = 0;
    const len = s.length;

    while (i < len) {
        if (s[i] === GS) { i++; continue; }

        const [ai, aiLen, dataLen] = matchAi(s, i);
        if (ai === null) break;

        const start = i + aiLen;
        let data;
        if (dataLen !== null) {
            data = s.substr(start, dataLen);
            if (data.length < dataLen) break;
            i = start + dataLen;
        } else {
            const gsPos = s.indexOf(GS, start);
            const end = gsPos === -1 ? len : gsPos;
            data = s.substring(start, end);
            i = end;
        }

        if (data === '') break;
        ais[ai] = data;
    }

    return ais;
}

function parseBracketed(s) {
    const ais = {};
    const re = /\((\d{2,4})\)([^(]*)/g;
    let m;
    while ((m = re.exec(s)) !== null) {
        const data = m[2].trim();
        if (data !== '') ais[m[1]] = data;
    }
    return ais;
}

function scaled(digits, decimals) {
    if (!/^\d+$/.test(digits)) return null;
    return parseInt(digits, 10) / 10 ** decimals;
}

function toDate(yymmdd) {
    if (!/^\d{6}$/.test(yymmdd)) return null;
    const year = 2000 + parseInt(yymmdd.slice(0, 2), 10);
    const month = parseInt(yymmdd.slice(2, 4), 10);
    let day = parseInt(yymmdd.slice(4, 6), 10);
    if (month < 1 || month > 12) return null;
    if (day === 0) day = new Date(year, month, 0).getDate(); // last day of month
    if (day < 1 || day > 31) return null;
    return `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

/**
 * @param {string} raw
 * @returns {{gtin: string|null, weight: number|null, price: number|null, expiry: string|null, batch: string|null, ais: object} | null}
 */
export function decodeGs128(raw) {
    const s = String(raw == null ? '' : raw).trim();
    if (s === '') return null;

    const ais = s.includes('(') ? parseBracketed(s) : walk(stripSymbology(s));
    if (Object.keys(ais).length === 0) return null;

    let weight = null;
    let price = null;
    let expiry = null;

    for (const [ai, data] of Object.entries(ais)) {
        if (ai.startsWith('310') && ai.length === 4) {
            weight = scaled(data, parseInt(ai[3], 10));
        } else if (AMOUNT_3.includes(ai.slice(0, 3)) && ai.length === 4) {
            price = scaled(data, parseInt(ai[3], 10));
        } else if (AMOUNT_CURRENCY_3.includes(ai.slice(0, 3)) && ai.length === 4) {
            price = scaled(data.slice(3), parseInt(ai[3], 10));
        } else if (ai === '17') {
            expiry = toDate(data);
        }
    }

    return {
        gtin: ais['01'] ?? null,
        weight,
        price,
        expiry,
        batch: ais['10'] ?? null,
        ais,
    };
}

/** GTIN match helper — compares ignoring leading-zero packaging padding. */
export function gtinMatches(gtin, barcode) {
    if (!gtin || !barcode) return false;
    const norm = (v) => String(v).replace(/^0+/, '') || '0';
    return norm(gtin) === norm(barcode);
}
