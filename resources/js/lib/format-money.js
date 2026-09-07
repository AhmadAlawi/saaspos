/**
 * System-wide money formatter — mirrors PHP's `format_money()`.
 *
 * Reads the currency registry the admin layout renders into two meta
 * tags on every page:
 *   <meta name="pos-currencies" content='[{code,symbol,decimals,...}]'>
 *   <meta name="pos-base-currency" content="USD">
 *
 * Exposes `window.posFormatMoney(amount, currencyCode?)` — falls back
 * to the base currency when no code is given. Honors per-currency
 * `decimals`, `thousands_separator`, `decimal_separator`, and
 * `symbol_first` (placement before vs after the digits).
 *
 * Why a global instead of per-factory plumbing: every reactive amount
 * in the system needs the same formatter — totals previews, line
 * totals, refund calculators, cart subtotals, etc. Centralising it
 * means changing the format (e.g. switching to Indian lakh/crore
 * grouping) is a one-file edit.
 */

const DEFAULT = {
    code: 'USD',
    symbol: '$',
    symbol_first: true,
    decimals: 2,
    thousands_separator: ',',
    decimal_separator: '.',
};

let _registry = null;        // { 'USD': {…format…}, 'INR': {…}, … }
let _baseCode  = 'USD';

function loadRegistry() {
    try {
        const raw = document.querySelector('meta[name="pos-currencies"]')?.content;
        const list = raw ? JSON.parse(raw) : [];
        _registry = {};
        for (const c of list) {
            _registry[c.code] = {
                code:                c.code,
                symbol:              c.symbol ?? c.code,
                symbol_first:        c.symbol_first ?? true,
                decimals:            c.decimals ?? 2,
                thousands_separator: c.thousands_separator ?? ',',
                decimal_separator:   c.decimal_separator   ?? '.',
            };
        }
        _baseCode = document.querySelector('meta[name="pos-base-currency"]')?.content || 'USD';
    } catch (e) {
        _registry = {};
        _baseCode = 'USD';
    }
}

function getFormat(code) {
    if (_registry === null) loadRegistry();
    return _registry[code] || _registry[_baseCode] || DEFAULT;
}

function format(amount, fmt) {
    const n = Number(amount);
    const value = Number.isFinite(n) ? n : 0;
    const fixed = value.toFixed(fmt.decimals);
    const [intPart, fracPart] = fixed.split('.');
    // Simple 3-digit grouping. Indian lakh/crore grouping isn't modelled
    // yet — see roadmap-deferred memory.
    const grouped = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, fmt.thousands_separator);
    const num     = fmt.decimals > 0 ? grouped + fmt.decimal_separator + fracPart : grouped;
    return fmt.symbol_first ? fmt.symbol + num : num + ' ' + fmt.symbol;
}

/**
 * Format a number as a money string in the named currency (or the
 * company's base currency when no code is given).
 *
 *   posFormatMoney(1234.5)          // "$1,234.50"
 *   posFormatMoney(1234.5, 'INR')   // "₹1,234.50"
 *   posFormatMoney(1234.5, 'EUR')   // "1,234.50 €"   (when symbol_first=false)
 */
export function posFormatMoney(amount, currencyCode = null) {
    return format(amount, getFormat(currencyCode || _baseCode));
}

/**
 * Format a quantity (no currency symbol) using the base currency's
 * decimal precision, thousands separator, and decimal separator.
 *
 * This makes quantity displays (stock levels, transfer quantities, etc.)
 * visually consistent with money displays throughout the system.
 *
 *   posFormatQty(80)        // "80.00"   (when decimals=2)
 *   posFormatQty(1.5)       // "1.50"
 *   posFormatQty(1234.5)    // "1,234.50"
 */
export function posFormatQty(amount) {
    const fmt = getFormat(_baseCode);
    const n     = Number(amount);
    const value = Number.isFinite(n) ? n : 0;
    const fixed = value.toFixed(fmt.decimals);
    const [intPart, fracPart] = fixed.split('.');
    const grouped = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, fmt.thousands_separator);
    return fmt.decimals > 0 ? grouped + fmt.decimal_separator + fracPart : grouped;
}

/** Hot-reload the registry (e.g. after the user changes Settings → Currency mid-session). */
export function reloadCurrencyRegistry() {
    _registry = null;
    loadRegistry();
}

/** Install the formatters as globals + Alpine magics. */
export function registerFormatMoney(Alpine) {
    loadRegistry();
    window.posFormatMoney = posFormatMoney;
    window.posFormatQty   = posFormatQty;
    if (Alpine?.magic) {
        Alpine.magic('formatMoney', () => posFormatMoney);
        Alpine.magic('formatQty',   () => posFormatQty);
    }
}
