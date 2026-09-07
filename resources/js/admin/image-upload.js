/**
 * Alpine factory for the <x-admin.image-upload> Blade component.
 *
 * Holds the file-picker state for ONE image input: the visible preview
 * URL, drag-over feedback, and an inline error message. Submits as part
 * of the surrounding form via:
 *
 *   - `<input type="file" name="{name}">`  (the file itself)
 *   - `<input type="hidden" name="{name}_remove" value="1">`
 *       (only when the user clicked Remove — tells the server to wipe
 *        the previously-stored file)
 *
 * Validation is intentionally client-side only (size + mime) so the
 * user gets immediate feedback. The server still re-validates via
 * Laravel's `image` + `max:` rules — these checks just avoid the
 * round-trip for the obvious cases.
 *
 * Important: the previewUrl from `URL.createObjectURL(file)` is a
 * blob: URL that lives for the lifetime of the document. We don't
 * revoke it on remove() — the cost is negligible and keeping it lets
 * the user undo the pick by clicking the original preview again.
 */
export function imageUpload({
    initialUrl = null,
    maxSizeKb  = 1024,
    accept     = 'image/*',
} = {}) {
    return {
        // Image currently shown in the drop zone. May be:
        //   - the server-stored URL (on first paint of an edit form)
        //   - a blob: URL after the user picked / dropped a new file
        //   - null when the user clicked Remove
        previewUrl: initialUrl,
        isDragOver: false,
        error:      '',
        // True once the user has clicked Remove. We emit a
        // `{name}_remove=1` flag so the server knows to delete the
        // stored file even though the file input itself is empty.
        removed:    false,

        accept,
        maxBytes: maxSizeKb * 1024,

        /** Programmatic file picker — wired up by the click target. */
        pick() {
            this.$refs.fileInput?.click();
        },

        /** Common entry point for both the <input @change> and the drop handler. */
        _ingest(file) {
            if (!file) return;

            // mime check — `accept` may be a comma list, e.g. "image/png,image/jpeg".
            const accepted = this.accept.split(',').map((s) => s.trim()).filter(Boolean);
            const matches  = accepted.some((pattern) => {
                if (pattern === 'image/*') return file.type.startsWith('image/');
                return file.type === pattern;
            });
            if (!matches) {
                this.error = 'That file type isn\'t supported.';
                return;
            }

            if (file.size > this.maxBytes) {
                this.error = `Image is larger than ${Math.round(this.maxBytes / 1024)} KB.`;
                return;
            }

            this.error      = '';
            this.previewUrl = URL.createObjectURL(file);
            this.removed    = false;
        },

        onFileChange(evt) {
            const file = evt.target.files?.[0];
            this._ingest(file);
        },

        onDrop(evt) {
            this.isDragOver = false;
            const file = evt.dataTransfer?.files?.[0];
            if (!file) return;

            // Push the dropped file into the real <input> so it submits
            // with the surrounding <form> exactly like a manual pick.
            const dt = new DataTransfer();
            dt.items.add(file);
            this.$refs.fileInput.files = dt.files;
            this._ingest(file);
        },

        remove() {
            this.previewUrl = null;
            this.removed    = true;
            this.error      = '';
            if (this.$refs.fileInput) this.$refs.fileInput.value = '';
        },

        /**
         * Imperative reset — called by parent components when the form
         * switches to a different model (e.g. user clicks a different
         * brand row). Wipes every transient picker bit (blob preview,
         * picked file, removed flag, error message, drag-over highlight)
         * and re-seeds the preview from the new model's stored URL.
         */
        sync(url) {
            this.previewUrl = url ?? null;
            this.removed    = false;
            this.error      = '';
            this.isDragOver = false;
            if (this.$refs.fileInput) this.$refs.fileInput.value = '';
        },
    };
}
