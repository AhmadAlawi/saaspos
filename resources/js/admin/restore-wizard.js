/**
 * Restore wizard — drives the 4 steps of /admin/settings/restore:
 *
 *   source → review → confirm → running → done
 *
 * The server does the real work in two calls: `validate` stages the chosen
 * source (an existing local backup OR an uploaded zip) and returns a token +
 * manifest summary; `run` performs the restore synchronously. The run call
 * disables the axios timeout because a restore can take much longer than the
 * default 30s, and guards against the tab being closed while it's in flight.
 *
 * On success the operator is logged out server-side, so we hard-redirect to
 * the sign-in page.
 */
export function restoreWizard(config = {}) {
    return {
        urls: config.urls || {},
        hasBackups: !!config.hasBackups,

        step: 'source',          // source | review | confirm | running | done
        busy: false,
        error: '',

        // source selection
        selected: null,          // backup_id of a chosen existing backup
        fileName: '',
        _file: null,

        // validate result
        token: null,
        summary: null,
        compatible: false,
        incompatibleReason: '',

        // confirm
        confirmText: '',
        preRestore: true,

        _guard: null,

        init() {
            this._guard = (e) => {
                e.preventDefault();
                e.returnValue = '';
            };
        },

        pickBackup(id) {
            this.selected = id;
            this._file = null;
            this.fileName = '';
        },

        onFile(event) {
            this._file = event.target.files?.[0] || null;
            this.fileName = this._file?.name || '';
            if (this._file) this.selected = null;
        },

        get canValidate() {
            return !this.busy && (this.selected !== null || !!this._file);
        },

        get canRun() {
            return !this.busy && this.compatible && this.confirmText.trim() === 'RESTORE';
        },

        async validateSource() {
            if (!this.canValidate) return;
            this.busy = true;
            this.error = '';

            let body;
            if (this._file) {
                body = new FormData();
                body.append('file', this._file);
            } else {
                body = { backup_id: this.selected };
            }

            try {
                const { data } = await this.$http.post(this.urls.validate, body);
                this.token = data.token;
                this.summary = data.summary;
                this.compatible = !!data.compatible;
                this.incompatibleReason = data.incompatible_reason || '';
                this.step = 'review';
            } catch (e) {
                this.error = e.message || '';
                this.$store.toasts.push({ type: 'error', message: this.error, duration: 6000 });
            } finally {
                this.busy = false;
            }
        },

        toConfirm() {
            if (!this.compatible) return;
            this.confirmText = '';
            this.step = 'confirm';
        },

        backToSource() {
            this.step = 'source';
        },

        backToReview() {
            this.step = 'review';
        },

        async run() {
            if (!this.canRun) return;
            this.busy = true;
            this.error = '';
            this.step = 'running';
            window.addEventListener('beforeunload', this._guard);

            try {
                // No timeout — a restore can run well past the default 30s.
                const { data } = await this.$http.post(
                    this.urls.run,
                    {
                        token: this.token,
                        confirmation: this.confirmText.trim(),
                        pre_restore_backup: this.preRestore,
                    },
                    { timeout: 0 },
                );
                window.removeEventListener('beforeunload', this._guard);
                this.step = 'done';
                this._redirect = data.redirect || this.urls.login;
                setTimeout(() => { window.location.href = this._redirect; }, 1800);
            } catch (e) {
                window.removeEventListener('beforeunload', this._guard);
                this.error = e.message || '';
                this.step = 'confirm';
                this.$store.toasts.push({ type: 'error', message: this.error, duration: 8000 });
            } finally {
                this.busy = false;
            }
        },

        goLogin() {
            window.location.href = this._redirect || this.urls.login;
        },

        // ── display helpers ──────────────────────────────────────────
        fmtDate(iso) {
            if (!iso) return '—';
            const d = new Date(iso);
            return Number.isNaN(d.getTime()) ? iso : d.toLocaleString();
        },

        fmtSize(bytes) {
            if (!bytes && bytes !== 0) return '—';
            return `${(bytes / 1024 / 1024).toFixed(2)} MB`;
        },
    };
}
