/**
 * Updates settings page — "Check for updates" + the live "update available"
 * banner, plus the install wizard (pre-flight checklist → Install → progress
 * overlay). The channel / auto-check form is a plain data-ajax-form (PATCH);
 * only the check + install need JS.
 *
 * Install runs synchronously server-side (InstallUpdate does the pre-update
 * backup + rollback itself), so the install request disables the axios timeout
 * and guards against the tab closing mid-update.
 */
export function updatesPage(initial = {}) {
    return {
        urls: initial.urls || {},
        zipOnly: initial.zipOnly || '',

        busy: false,
        available: !!initial.available,
        version: initial.version || null,
        release: initial.release || null,
        lastChecked: initial.lastChecked || null,
        feedError: initial.feedError || null,

        // install wizard
        installStep: null,        // null | 'preflight' | 'installing' | 'done' | 'failed'
        installMode: 'auto',      // 'auto' (feed) | 'manual' (uploaded zip)
        checks: [],
        canInstall: false,
        installBusy: false,
        installError: '',
        _guard: null,
        _redirect: null,

        // manual upload
        manualToken: null,
        manualBusy: false,
        manualProgress: 0,     // 0-100 while a chunked upload is in flight
        manualFileName: '',
        manualDragOver: false,
        _manualFile: null,

        init() {
            this._guard = (e) => {
                e.preventDefault();
                e.returnValue = '';
            };
        },

        async checkNow() {
            if (this.busy) return;
            this.busy = true;

            try {
                const { data } = await this.$http.post(this.urls.check);
                this.available = !!data.available;
                this.version = data.version;
                this.release = data.release;
                this.lastChecked = data.last_checked;
                this.feedError = data.ok ? null : data.message;

                this.$store.toasts.push({
                    type: data.ok ? (data.available ? 'info' : 'success') : 'warning',
                    message: data.message,
                    duration: 5000,
                });
            } catch (e) {
                this.$store.toasts.push({ type: 'error', message: e.message || '', duration: 6000 });
            } finally {
                this.busy = false;
            }
        },

        async skipVersion() {
            if (this.busy || !this.version) return;
            this.busy = true;

            try {
                const { data } = await this.$http.post(this.urls.skip, { version: this.version });
                this.available = false;
                this.$store.toasts.push({ type: 'success', message: data.message || '', duration: 4000 });
            } catch (e) {
                this.$store.toasts.push({ type: 'error', message: e.message || '', duration: 6000 });
            } finally {
                this.busy = false;
            }
        },

        async openInstall() {
            this.installMode = 'auto';
            this.installStep = 'preflight';
            this.installBusy = true;
            this.checks = [];
            this.canInstall = false;
            this.installError = '';

            try {
                const { data } = await this.$http.get(this.urls.preflight);
                this.checks = data.checks || [];
                this.canInstall = !!data.can_install;
            } catch (e) {
                this.installStep = null;
                this.$store.toasts.push({ type: 'error', message: e.message || '', duration: 6000 });
            } finally {
                this.installBusy = false;
            }
        },

        manualPick() {
            this.$refs.manualInput?.click();
        },

        manualOnFile(event) {
            this._setManualFile(event.target.files?.[0] || null);
        },

        manualOnDrop(event) {
            this.manualDragOver = false;
            this._setManualFile(event.dataTransfer?.files?.[0] || null);
        },

        _setManualFile(file) {
            if (file && !/\.zip$/i.test(file.name)) {
                this.$store.toasts.push({ type: 'warning', message: this.zipOnly || 'Please choose a .zip file.', duration: 4000 });
                return;
            }
            this._manualFile = file;
            this.manualFileName = file?.name || '';
        },

        async manualUpload() {
            if (!this._manualFile || this.manualBusy) return;
            this.manualBusy = true;
            this.manualProgress = 0;

            // Upload the package in small chunks. A big update zip (with
            // vendor/) blows past the per-request size and time limits many
            // hosts — and Cloudflare — enforce; slicing it into ~3 MB pieces
            // keeps every request tiny, so it works regardless of host limits.
            const file      = this._manualFile;
            const chunkSize = 3 * 1024 * 1024;
            const total     = Math.max(1, Math.ceil(file.size / chunkSize));
            const uploadId  = (window.crypto?.randomUUID?.()
                || (Date.now().toString(36) + Math.random().toString(36).slice(2)))
                .replace(/[^a-zA-Z0-9_-]/g, '');

            try {
                let last = null;
                for (let index = 0; index < total; index++) {
                    const start = index * chunkSize;
                    const blob  = file.slice(start, Math.min(start + chunkSize, file.size));

                    const body = new FormData();
                    body.append('upload_id', uploadId);
                    body.append('index', index);
                    body.append('total', total);
                    body.append('file', blob, file.name);

                    const { data } = await this.$http.post(this.urls.manualChunk, body, { timeout: 0 });
                    last = data;
                    this.manualProgress = Math.round(((index + 1) / total) * 100);
                }

                // The final chunk's response stages the package (same shape as
                // the old whole-file upload).
                this.installMode = 'manual';
                this.manualToken = last.token;
                this.version = last.version;
                this.release = null;          // a manual package has no release notes
                this.checks = last.checks || [];
                this.canInstall = !!last.can_install;
                this.installStep = 'preflight';
            } catch (e) {
                this.$store.toasts.push({ type: 'error', message: e.message || '', duration: 6000 });
            } finally {
                this.manualBusy = false;
            }
        },

        cancelInstall() {
            if (this.installStep === 'installing') return; // can't cancel mid-flight
            this.installStep = null;
        },

        async runInstall() {
            if (!this.canInstall || this.installBusy) return;
            this.installStep = 'installing';
            this.installBusy = true;
            this.installError = '';
            window.addEventListener('beforeunload', this._guard);

            try {
                const url = this.installMode === 'manual' ? this.urls.manualInstall : this.urls.install;
                const body = this.installMode === 'manual'
                    ? { token: this.manualToken }
                    : { version: this.version };
                const { data } = await this.$http.post(url, body, { timeout: 0 });
                window.removeEventListener('beforeunload', this._guard);
                this.installStep = 'done';
                this._redirect = data.redirect || this.urls.updates;
                setTimeout(() => { window.location.href = this._redirect; }, 2200);
            } catch (e) {
                window.removeEventListener('beforeunload', this._guard);
                this.installError = e.message || '';
                this.installStep = 'failed';
            } finally {
                this.installBusy = false;
            }
        },

        reload() {
            window.location.href = this._redirect || this.urls.updates;
        },
    };
}
