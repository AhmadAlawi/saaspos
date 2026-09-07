/**
 * Weighing-scale barcode decoder.
 *
 * Scale-printed barcodes (EAN-13 / UPC-A) embed an item code (PLU) and a
 * measured value — either the WEIGHT or the total PRICE — inside the digits.
 * The exact layout varies by scale brand, so the template is fully
 * configurable from Settings → Scale and shaped like:
 *
 *   [prefix][plu(plu_length)][skip(value_offset)][value(value_length)][…check]
 *
 * Example (prefix "2", plu 5, value 5, decimals 3, weight):
 *   2 12345 01500 8   →  PLU 12345, weight 1.500 kg
 *
 * The trailing digits (the EAN-13 check digit, and any internal price-check
 * digit) are ignored — the hardware scanner already validates the EAN check,
 * and `value_offset` lets the admin skip an internal check digit that sits
 * between the PLU and the value (common on US UPC-A "type 2" labels).
 *
 * Returns `null` when the barcode isn't a scale barcode for this template
 * (wrong prefix, non-numeric, too short, or a disabled config) so the caller
 * can fall through to a normal product lookup.
 */

/** Clamp a config value to a non-negative integer. */
function toInt(v) {
    const n = parseInt(v, 10);
    return Number.isFinite(n) && n > 0 ? n : 0;
}

/**
 * @param {string} raw   The scanned barcode.
 * @param {object} cfg   Company::scale() shape.
 * @returns {{plu: number, value: number, embed: 'weight'|'price'} | null}
 */
export function decodeScaleBarcode(raw, cfg) {
    if (!cfg || !cfg.enabled) return null;

    const code = String(raw == null ? '' : raw).trim();
    // Scale barcodes are all digits. A non-numeric scan (SKU, alnum barcode)
    // is never a scale barcode.
    if (!/^\d+$/.test(code)) return null;

    const prefix = String(cfg.prefix == null ? '' : cfg.prefix);
    if (prefix === '' || !code.startsWith(prefix)) return null;

    const pluLen   = toInt(cfg.plu_length);
    const valLen   = toInt(cfg.value_length);
    const offset   = Math.max(0, parseInt(cfg.value_offset, 10) || 0);
    const decimals = Math.max(0, parseInt(cfg.value_decimals, 10) || 0);
    if (pluLen === 0 || valLen === 0) return null;

    let i = prefix.length;
    const pluRaw = code.slice(i, i + pluLen);
    i += pluLen + offset;
    const valRaw = code.slice(i, i + valLen);

    // The scan must be long enough to carry both fields in full.
    if (pluRaw.length < pluLen || valRaw.length < valLen) return null;

    const plu   = parseInt(pluRaw, 10);
    const value = parseInt(valRaw, 10) / Math.pow(10, decimals);
    if (!Number.isFinite(plu) || !Number.isFinite(value)) return null;

    return {
        plu,
        value,
        embed: cfg.embed_type === 'price' ? 'price' : 'weight',
    };
}
