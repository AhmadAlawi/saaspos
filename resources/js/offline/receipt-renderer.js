/**
 * Client-side receipt renderer (offline-sync doc §13.2).
 *
 * A smaller cousin of the server-side `sales.receipt` Blade: it composes a
 * complete, self-styled receipt document from a sale snapshot captured at
 * ring-up time plus the company/store receipt config bootstrapped into the
 * cashier page. This is what lets an OFFLINE sale print a receipt without a
 * server round-trip — the browser-print bridge writes the returned HTML into
 * a hidden iframe and fires the print dialog.
 *
 * Deliberate deviations from the online receipt (doc §13.3):
 *   - The sale number is the provisional `OFF-XXXXXXXX`; a footer note says
 *     the final number lands on sync.
 *   - The Code 128 barcode + QR *graphics* are skipped (the server renders
 *     them from PHP libs we don't ship client-side) — the number prints as
 *     mono text instead.
 *   - Only browser-print HTML is produced; the ESC/POS byte path stays
 *     server-only for now.
 *
 * The receipt stylesheet is imported as a raw string so the thermal `@page`
 * sizing + all `.rcpt-*` classes stay a single source of truth with the
 * online receipt.
 */
import receiptCss from '../../css/receipt.css?raw';

/** HTML-escape a value for safe interpolation into the receipt markup. */
function esc(value) {
    if (value === null || value === undefined) return '';
    return String(value).replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

/** Trim trailing zeros off a quantity string ("2.0000" → "2"). */
function fmtQty(q) {
    const s = String(q ?? '');
    return s.indexOf('.') === -1 ? s : s.replace(/0+$/, '').replace(/\.$/, '');
}

/**
 * @param {object} snapshot  captured at ring-up (see cashier-page.buildOfflineReceiptSnapshot)
 * @param {object} cfg       the `receipt` config bootstrapped into the page
 * @param {(v:number|string)=>string} money  the page's money formatter
 * @returns {string}  a full, printable HTML document
 */
export function renderOfflineReceiptHtml(snapshot, cfg, money) {
    cfg = cfg || {};
    const fmt      = typeof money === 'function' ? money : (v) => String(v ?? '');
    const paper    = cfg.paper || '80mm';
    const store    = cfg.store || {};
    const company  = cfg.company || {};
    const show     = cfg.show || {};
    const L        = cfg.labels || {};
    const lang     = document.documentElement.getAttribute('lang') || 'en';
    const dir      = document.documentElement.getAttribute('dir') || 'ltr';
    const container = paper === 'a4' ? 'receipt-a4' : 'receipt-thermal';

    const parts = [];

    // ── Logo / brand ─────────────────────────────────────────────
    if (show.logo && company.logo_url) {
        parts.push(`<div class="rcpt-logo-img"><img src="${esc(company.logo_url)}" alt=""></div>`);
    } else if (show.logo && company.display_app_name) {
        parts.push(`<div class="rcpt-logo">${esc(company.display_app_name)}</div>`);
    }

    // ── Store identity ───────────────────────────────────────────
    const addrLines = [
        (store.address_line1 || '').trim(),
        (store.address_line2 || '').trim(),
        [store.city, store.state, store.postal_code].filter(Boolean).join(', '),
    ].filter(Boolean);
    let storeBlock = `<div class="rcpt-store"><div class="rcpt-store-name">${esc(store.name)}</div>`;
    for (const line of addrLines) storeBlock += `<div class="rcpt-store-addr">${esc(line)}</div>`;
    if (store.phone) storeBlock += `<div class="rcpt-store-addr">${esc(store.phone)}</div>`;
    if (company.tax_registration_number) {
        storeBlock += `<div class="rcpt-store-tax">${esc(L.gstin || 'GSTIN')}: ${esc(company.tax_registration_number)}</div>`;
    }
    storeBlock += '</div>';
    parts.push(storeBlock);

    if (company.receipt_header) {
        parts.push(`<div class="rcpt-header">${esc(company.receipt_header)}</div>`);
    }

    // ── Meta: provisional number + datetime ──────────────────────
    parts.push(
        `<div class="rcpt-meta">` +
        `<div><strong>${esc(snapshot.number)}</strong></div>` +
        `<div>${esc(snapshot.datetime)}</div>` +
        `</div>`,
    );

    if (show.customer && snapshot.customer && snapshot.customer.name) {
        const phone = snapshot.customer.phone ? ` · ${esc(snapshot.customer.phone)}` : '';
        parts.push(`<div class="rcpt-line">${esc(L.customer || 'Customer')}: ${esc(snapshot.customer.name)}${phone}</div>`);
    }
    if (show.cashier && snapshot.cashier_name) {
        parts.push(`<div class="rcpt-line">${esc(L.cashier || 'Cashier')}: ${esc(snapshot.cashier_name)}</div>`);
    }

    parts.push('<div class="rcpt-rule"></div>');

    // ── Items ────────────────────────────────────────────────────
    let items = '<div class="rcpt-items">';
    for (const it of (snapshot.items || [])) {
        items += '<div class="rcpt-item">';
        const variant = it.variant_label ? ` <span class="rcpt-item-variant">· ${esc(it.variant_label)}</span>` : '';
        items += `<span class="rcpt-item-name">${esc(it.name)}${variant}</span>`;
        if (show.sku && it.sku) {
            items += `<span class="rcpt-item-code">${esc(L.sku || 'SKU')}: ${esc(it.sku)}</span>`;
        }
        if (show.hsn && it.hsn) {
            items += `<span class="rcpt-item-code">${esc(L.hsn || 'HSN')}: ${esc(it.hsn)}</span>`;
        }
        items += `<span class="rcpt-item-qty">${esc(fmtQty(it.quantity))} × ${esc(fmt(it.unit_price))}</span>`;
        items += `<span class="rcpt-item-total">${esc(fmt(it.line_total))}</span>`;
        if (Number(it.tax_amount) > 0) {
            const incl = it.tax_inclusive ? ` <span class="rcpt-item-tax-incl">${esc(L.tax_incl || '(incl.)')}</span>` : '';
            items += `<span class="rcpt-item-tax">${esc(L.tax || 'Tax')} ${esc(fmt(it.tax_amount))}${incl}</span>`;
        }
        if (it.batch_number) {
            const expiry = it.batch_expiry ? ` · Exp ${esc(String(it.batch_expiry).slice(0, 10))}` : '';
            items += `<span class="rcpt-item-note">Batch ${esc(it.batch_number)}${expiry}</span>`;
        }
        if (it.note) {
            items += `<span class="rcpt-item-note">${esc(it.note)}</span>`;
        }
        for (const k of (it.kit_items || [])) {
            const kv = k.variant_label ? ` · ${esc(k.variant_label)}` : '';
            items += `<span class="rcpt-item-note rcpt-item-kit">+ ${esc(fmtQty(k.quantity))}× ${esc(k.name || '—')}${kv}</span>`;
        }
        items += '</div>';
    }
    items += '</div>';
    parts.push(items);

    parts.push('<div class="rcpt-rule"></div>');

    // ── Totals ───────────────────────────────────────────────────
    const t = snapshot.totals || {};
    let totals = '<div class="rcpt-totals">';
    totals += `<div><span>${esc(L.subtotal || 'Subtotal')}</span><span>${esc(fmt(t.gross_subtotal))}</span></div>`;
    if (Number(t.discount_total) > 0) {
        totals += `<div><span>${esc(L.discount || 'Discount')}</span><span>−${esc(fmt(t.discount_total))}</span></div>`;
    }
    if (Number(t.tax_total) > 0) {
        totals += `<div class="rcpt-tax-incl"><span>${esc(L.tax_included || 'Tax (already included)')}</span><span>${esc(fmt(t.tax_total))}</span></div>`;
    }
    totals += `<div class="rcpt-total"><span>${esc(L.grand_total || 'Total')}</span><span>${esc(fmt(t.grand_total))}</span></div>`;

    for (const p of (snapshot.payments || [])) {
        totals += `<div><span>${esc(p.name)}</span><span>${esc(fmt(p.amount))}</span></div>`;
        if (Number(p.tendered_amount) > Number(p.amount)) {
            totals += `<div class="rcpt-tendered"><span>${esc(L.tendered || 'Tendered')}</span><span>${esc(fmt(p.tendered_amount))}</span></div>`;
        }
    }
    if (Number(t.change_returned) > 0) {
        totals += `<div><span>${esc(L.change || 'Change')}</span><span>${esc(fmt(t.change_returned))}</span></div>`;
    }
    totals += '</div>';
    parts.push(totals);

    parts.push('<div class="rcpt-rule"></div>');

    if (company.receipt_footer) {
        parts.push(`<div class="rcpt-footer">${esc(company.receipt_footer)}</div>`);
    }

    // Number as mono text (offline stands in for the online barcode/QR).
    parts.push(`<div class="rcpt-barcode-number mono">${esc(snapshot.number)}</div>`);

    // Provisional note — the one thing that distinguishes an offline receipt.
    parts.push(`<div class="rcpt-return">${esc(L.provisional || 'Provisional receipt — final number assigned on sync.')}</div>`);

    if (company.receipt_return_policy) {
        parts.push(`<div class="rcpt-return">${esc(company.receipt_return_policy)}</div>`);
    }

    const title = `${esc(snapshot.number)}`;

    return `<!DOCTYPE html>
<html lang="${esc(lang)}" dir="${esc(dir)}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>${title}</title>
<style>${receiptCss}</style>
</head>
<body class="receipt-body receipt-paper-${esc(paper)}">
<div class="receipt-toolbar" role="toolbar">
<span class="receipt-tool-spacer"></span>
<button type="button" class="receipt-tool-btn" onclick="window.print()">🖨</button>
</div>
<div class="receipt ${container}">
${parts.join('\n')}
</div>
</body>
</html>`;
}
