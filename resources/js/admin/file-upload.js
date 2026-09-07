/**
 * Alpine factory for a single styled file picker (generic files —
 * PDF / image / document), used where a plain `<input type="file">`
 * would look out of place against the design system.
 *
 * Mirrors the image-upload control's behaviour but for non-image files:
 *   - click or drag-and-drop a file onto the dashed zone
 *   - the chosen file shows as a chip with its name + size + a clear (×)
 *   - client-side size guard (the server validates again)
 *
 * The native input still lives in the DOM (visually hidden) so the file
 * submits with the surrounding multipart form exactly as before — no
 * upload endpoint, no JSON, no per-field wiring.
 */
export function fileUpload(opts = {}) {
    const maxSizeKb = Number(opts.maxSizeKb) || 0;

    return {
        fileName: '',
        fileSize: '',
        isDragOver: false,
        error: '',

        get hasFile() {
            return this.fileName !== '';
        },

        pick() {
            this.$refs.input.click();
        },

        onFileChange(e) {
            const file = e.target.files?.[0] ?? null;
            this._accept(file);
        },

        onDrop(e) {
            this.isDragOver = false;
            const file = e.dataTransfer?.files?.[0] ?? null;
            if (!file) return;

            // Push the dropped file into the native input so it submits
            // with the form (drag-drop doesn't populate <input> on its own).
            const dt = new DataTransfer();
            dt.items.add(file);
            this.$refs.input.files = dt.files;
            this._accept(file);
        },

        clear() {
            this.$refs.input.value = '';
            this.fileName = '';
            this.fileSize = '';
            this.error = '';
        },

        _accept(file) {
            this.error = '';
            if (!file) {
                this.fileName = '';
                this.fileSize = '';
                return;
            }
            if (maxSizeKb > 0 && file.size > maxSizeKb * 1024) {
                this.clear();
                this.error = (opts.tooLargeMessage || 'File is too large.')
                    .replace(':size', this._formatSize(maxSizeKb * 1024));
                return;
            }
            this.fileName = file.name;
            this.fileSize = this._formatSize(file.size);
        },

        _formatSize(bytes) {
            if (bytes >= 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
            if (bytes >= 1024) return Math.round(bytes / 1024) + ' KB';
            return bytes + ' B';
        },
    };
}
