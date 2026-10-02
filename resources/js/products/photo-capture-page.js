import Alpine from 'alpinejs';
import { posPost, posDelete } from '../lib/http.js';
import { barcodeScanMixin } from '../admin/barcode-scan-mixin.js';

/**
 * Mobile floor tool: scan a barcode (HID/Bluetooth wedge scanner), see
 * the matched product + its existing photo gallery, take a photo. Each
 * photo taken is appended — never overwrites — and re-scanning the same
 * barcode later just reloads that product's gallery so more photos can
 * be added. See docs in ProductPhotoCaptureController.
 */
export function productPhotoCapture(bootstrap = {}) {
    return {
        ...barcodeScanMixin({ scanUrl: bootstrap.scan_url || null }),

        storeUrlTemplate: bootstrap.store_url_template || '', // "/product-photos/:id/photos"
        labels: bootstrap.labels || {},

        product:  null,   // { id, name, sku }
        photos:   [],     // [{ id, url }]
        uploading: false,
        error:    '',     // this page has no toast store (standalone bundle) — shown inline instead

        init() {
            this.initBarcodeScan();
            this.$nextTick(() => this.$refs.scanInput?.focus());
        },

        destroy() {
            this.destroyBarcodeScan();
        },

        /** barcodeScanMixin calls this once a scan resolves to a product row. */
        _onScanResolved(row) {
            this.error   = '';
            this.product = { id: row.product_id, name: row.name, sku: row.sku };
            this.photos  = row.photos || [];
        },

        /** barcodeScanMixin calls this on a 404 before it tries the (absent) toast store. */
        _onScanNotFound(code) {
            this.error = (this.labels.scan_not_found || 'No product found for barcode :barcode.').replace(':barcode', code);
            return true; // handled — skip the mixin's own toast attempt
        },

        /** "Done — scan next" — clear the current product, refocus the scan field. */
        doneScanNext() {
            this.product = null;
            this.photos  = [];
            this.$nextTick(() => this.$refs.scanInput?.focus());
        },

        /** Opens the phone's native camera via the hidden file input. */
        takePhoto() {
            this.$refs.photoInput?.click();
        },

        async onPhotoChosen(e) {
            const file = e.target.files?.[0];
            e.target.value = ''; // reset so choosing the same shot again still fires 'change'
            if (!file || !this.product) return;

            this.uploading = true;
            this.error = '';
            const body = new FormData();
            body.append('photo', file);

            try {
                const url = this.storeUrlTemplate.replace(':id', this.product.id);
                const { data } = await posPost(url, body);
                this.photos = [data.photo, ...this.photos];
            } catch (err) {
                this.error = err?.message || this.labels.upload_failed || "Couldn't upload that photo.";
            } finally {
                this.uploading = false;
            }
        },

        async deletePhoto(photo) {
            if (!this.product) return;
            if (!window.confirm(this.labels.delete_confirm || 'Delete this photo?')) return;

            try {
                await posDelete(`/product-photos/${this.product.id}/photos/${photo.id}`);
                this.photos = this.photos.filter((p) => p.id !== photo.id);
            } catch (err) {
                this.error = err?.message || 'Could not delete that photo.';
            }
        },
    };
}

document.addEventListener('alpine:init', () => {
    Alpine.data('productPhotoCapture', productPhotoCapture);
});

window.Alpine = Alpine;
Alpine.start();
