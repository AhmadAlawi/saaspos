/**
 * Browser-print driver (docs/features/hardware.md §5.3, Path B).
 *
 * The universal fallback: works in every browser, including those
 * without WebUSB (Safari, Firefox). The server renders a complete,
 * self-styled receipt document (it already inlines receipt.css with the
 * thermal `@page` sizing), so we drop it into a hidden iframe and fire
 * the print dialog — no new tab, no manifest dependency.
 *
 * Note: in this path the printer only ever sees HTML, never raw ESC/POS,
 * so the cash-drawer kick can't be sent (§8.3) — the cashier opens the
 * drawer manually.
 */
export function printViaBrowserPrint(html) {
    return new Promise((resolve, reject) => {
        if (!html) { reject(new Error('No receipt HTML to print.')); return; }

        const iframe = document.createElement('iframe');
        Object.assign(iframe.style, {
            position: 'fixed', right: '0', bottom: '0',
            width: '0', height: '0', border: '0',
        });
        document.body.appendChild(iframe);

        const cleanup = () => {
            // Give the print dialog a beat to capture the document before
            // we tear the iframe down.
            setTimeout(() => {
                if (iframe.parentNode) iframe.parentNode.removeChild(iframe);
            }, 1000);
        };

        iframe.onload = () => {
            try {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
                cleanup();
                resolve();
            } catch (e) {
                cleanup();
                reject(e);
            }
        };

        const doc = iframe.contentDocument || iframe.contentWindow.document;
        doc.open();
        doc.write(html);
        doc.close();
    });
}
