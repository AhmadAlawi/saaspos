import { posPost } from '../lib/http.js';

/**
 * Bulk product images — Alpine factory for /admin/products/bulk-images.
 *
 * The customer drops a folder of image files; we match each file to a product
 * by the filename it declared in the CSV import's `Image` column (server-side
 * `products.image_ref`), show a matched/unmatched preview, then upload the
 * matched files.
 *
 * Two things keep this working on shared hosting:
 *   - **Match before upload.** We send just the filenames (tiny JSON) and get
 *     back which map to products, so the preview costs no bandwidth.
 *   - **Batched upload.** Matched files go up in small groups (`maxBatch`),
 *     one request at a time, each well under `post_max_size`. Nothing runs long
 *     server-side, so no queue is needed.
 *
 * @param {{
 *   matchUrl: string, storeUrl: string, maxKb: number, maxBatch: number,
 *   accept: string[], labels: Record<string,string>,
 * }} config
 */
export function bulkProductImages(config = {}) {
    return {
        matchUrl: config.matchUrl,
        storeUrl: config.storeUrl,
        maxKb:    config.maxKb    ?? 4096,
        maxBatch: config.maxBatch ?? 50,
        accept:   config.accept   ?? ['jpg', 'jpeg', 'png', 'webp'],
        labels:   config.labels   ?? {},

        // 'idle' → 'matching' → 'ready' → 'uploading' → 'done'
        phase:   'idle',
        dragOver: false,

        // Matched rows carry an upload `status`: 'ready' | 'uploading' | 'done' | 'error'.
        matched:   [],
        unmatched: [],

        // Lowercased filename → File. The bytes never go to the match step;
        // this is where we hold them until upload.
        _files: {},

        uploadedCount: 0,
        uploadTotal:   0,

        get acceptAttr() {
            return this.accept.map((e) => '.' + e).join(',');
        },

        // ── File intake ────────────────────────────────────────────
        onDrop(event) {
            this.dragOver = false;
            this._ingest(event.dataTransfer?.files);
        },

        onPick(event) {
            this._ingest(event.target?.files);
            // Let the same folder be re-picked after a reset.
            event.target.value = '';
        },

        /** Filter to accepted image files under the size cap, then match. */
        _ingest(fileList) {
            const files = Array.from(fileList ?? []);
            if (files.length === 0) return;

            const maxBytes = this.maxKb * 1024;
            const fresh = {};
            let skipped = 0;

            for (const file of files) {
                const ext = (file.name.split('.').pop() || '').toLowerCase();
                if (!this.accept.includes(ext)) { skipped++; continue; }
                if (file.size > maxBytes)       { skipped++; continue; }
                // Keyed by lowercased basename — the server matches the same way.
                fresh[this._basename(file.name).toLowerCase()] = file;
            }

            this._files = fresh;

            if (skipped > 0) {
                this.$store.toasts?.push({
                    type: 'info',
                    message: `${skipped} file(s) were skipped (wrong type or over ${this.maxKb / 1024} MB).`,
                });
            }

            const names = Object.values(fresh).map((f) => f.name);
            if (names.length === 0) { this.reset(); return; }

            this._match(names);
        },

        // ── Match ──────────────────────────────────────────────────
        async _match(filenames) {
            this.phase = 'matching';
            this.matched = [];
            this.unmatched = [];

            let data;
            try {
                ({ data } = await posPost(this.matchUrl, { filenames }));
            } catch (e) {
                this.phase = 'idle';
                this.$store.toasts?.push({ type: 'error', message: e?.message || 'Could not match files.' });
                return;
            }

            // Keep only matches we actually hold a file for (belt-and-braces).
            this.matched = (data.matched ?? [])
                .filter((m) => this._files[this._basename(m.filename).toLowerCase()])
                .map((m) => ({ ...m, status: 'ready', error: null }));

            this.unmatched = data.unmatched ?? [];
            this.phase = 'ready';
        },

        // ── Upload ─────────────────────────────────────────────────
        async upload() {
            const pending = this.matched.filter((r) => r.status === 'ready' || r.status === 'error');
            if (pending.length === 0) return;

            this.phase = 'uploading';
            this.uploadTotal   = pending.length;
            this.uploadedCount = 0;

            // Chunk into batches so each request stays small.
            for (let i = 0; i < pending.length; i += this.maxBatch) {
                const batch = pending.slice(i, i + this.maxBatch);
                await this._uploadBatch(batch);
            }

            this.phase = 'done';

            if (this.matched.some((r) => r.status === 'error')) {
                this.$store.toasts?.push({ type: 'warning', message: this.labels.error_generic });
            }
        },

        async _uploadBatch(batch) {
            batch.forEach((r) => { r.status = 'uploading'; });

            const fd = new FormData();
            for (const row of batch) {
                const file = this._files[this._basename(row.filename).toLowerCase()];
                if (file) fd.append(`files[${row.product_id}]`, file, file.name);
            }

            let data;
            try {
                ({ data } = await posPost(this.storeUrl, fd));
            } catch (e) {
                // Whole-batch failure (413, 5xx, network). Mark each row errored
                // but keep going — a later batch may still succeed.
                batch.forEach((r) => { r.status = 'error'; r.error = e?.message || 'Upload failed'; });
                this.uploadedCount += batch.length;
                return;
            }

            const byId = {};
            for (const res of data.results ?? []) byId[res.product_id] = res;

            for (const row of batch) {
                const res = byId[row.product_id];
                if (res?.ok) {
                    row.status = 'done';
                    row.has_image = true;
                    if (res.image_url) row.image_url = res.image_url;
                } else {
                    row.status = 'error';
                    row.error = res?.error || 'Upload failed';
                }
                this.uploadedCount += 1;
            }
        },

        // ── Unmatched export ───────────────────────────────────────
        downloadUnmatched() {
            if (this.unmatched.length === 0) return;
            const lines = ['filename,reason', ...this.unmatched.map((u) => `${u.filename},${u.reason}`)];
            const blob = new Blob([lines.join('\n')], { type: 'text/csv' });
            const url  = URL.createObjectURL(blob);
            const a    = document.createElement('a');
            a.href = url;
            a.download = 'unmatched-images.csv';
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        },

        reset() {
            this.phase = 'idle';
            this.matched = [];
            this.unmatched = [];
            this._files = {};
            this.uploadedCount = 0;
            this.uploadTotal = 0;
        },

        // ── Display helpers ────────────────────────────────────────
        get summaryText() {
            return this._t('summary', { matched: this.matched.length, unmatched: this.unmatched.length });
        },
        get matchedTitle()   { return this._t('matched_title',   { count: this.matched.length }); },
        get unmatchedTitle() { return this._t('unmatched_title', { count: this.unmatched.length }); },
        get uploadButtonText() {
            const n = this.matched.filter((r) => r.status !== 'done').length || this.matched.length;
            return this._t('upload_button', { count: n });
        },
        get uploadingText() { return this._t('uploading', { done: this.uploadedCount, total: this.uploadTotal }); },
        get doneText() {
            return this._t('done_summary', { stored: this.matched.filter((r) => r.status === 'done').length });
        },
        reasonLabel(reason) {
            return reason === 'duplicate' ? this.labels.reason_duplicate : this.labels.reason_no_match;
        },

        /** Interpolate a `:placeholder` lang template with values. */
        _t(key, params) {
            let s = this.labels[key] ?? '';
            for (const [k, v] of Object.entries(params)) {
                s = s.replaceAll(':' + k, String(v));
            }
            return s;
        },

        _basename(name) {
            return String(name).replace(/^.*[\\/]/, '');
        },
    };
}
