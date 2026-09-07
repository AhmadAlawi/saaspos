/**
 * Alpine factory for the product label-print wizard
 * (docs/features/hardware.md §6.3). Builds a list of products + per-row
 * quantities, then submits to the printable-sheet endpoint (which opens
 * in a new tab for printing). No AJAX — the sheet is a full HTML page the
 * browser prints.
 */
export function labelWizard({ defaultLayout = 'a4-24' } = {}) {
    return {
        rows:   [],   // { id, label, qty }
        layout: defaultLayout,
        fields: { name: true, sku: true, price: true, barcode: true },
        barcodeInput:    '',
        barcodeNotFound: false,

        get hasRows()  { return this.rows.length > 0; },
        get anyField() { return Object.values(this.fields).some(Boolean); },
        get canGenerate() { return this.hasRows && this.anyField; },

        /** Total label count across all rows (for the live summary). */
        get totalLabels() {
            return this.rows.reduce((sum, r) => sum + (parseInt(r.qty, 10) || 0), 0);
        },

        /** Add the product chosen in the remote picker, then reset it. */
        addFromPicker(evt) {
            const sel = evt.target;
            const id = sel.value;
            if (!id) return;

            const label = sel.selectedOptions?.[0]?.textContent?.trim() || `#${id}`;
            if (!this.rows.some((r) => String(r.id) === String(id))) {
                this.rows.push({ id, label, qty: 30 });
            }

            // Clear the picker so the next pick starts fresh (TomSelect-aware).
            if (sel.tomselect) sel.tomselect.clear();
            else sel.value = '';
        },

        /** Exact barcode/sku lookup on Enter — no dropdown, one hit or a miss. */
        async addFromBarcode(evt) {
            const code = this.barcodeInput.trim();
            if (!code) return;

            try {
                const { data } = await window.posGet('/admin/products/lookup-barcode', { barcode: code });
                this.barcodeNotFound = false;
                if (!this.rows.some((r) => String(r.id) === String(data.value))) {
                    this.rows.push({ id: data.value, label: data.label, qty: 30 });
                }
            } catch (e) {
                this.barcodeNotFound = true;
            }

            this.barcodeInput = '';
            evt.target.focus();
        },

        removeRow(id) {
            this.rows = this.rows.filter((r) => String(r.id) !== String(id));
        },
    };
}
