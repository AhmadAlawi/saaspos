/**
 * Shared Alpine factory for AJAX auth forms (forgot-password, reset-password)
 * so submitting them no longer does a full-page reload.
 *
 * Auth pages load `app.js`, which exposes the `$http` magic + the global
 * submit-loader but NOT the admin toasts store — so results surface in the
 * form's OWN reactive alert areas (`errorMsg` / `statusMsg`), mirroring the
 * inline pattern already used by login.blade.php.
 *
 * The submit-loader (lib/submit-loader.js) leaves us alone: `@submit.prevent`
 * flags the event as handled, and the button ships its own `.animate-spin`.
 *
 * @param {object}  opts
 * @param {'status'|'redirect'} opts.mode
 *   - 'status'   → show `data.status` in a success alert, stay on the page
 *                  (forgot-password: "reset link sent").
 *   - 'redirect' → navigate to `data.redirect` on success
 *                  (reset-password: back to login, status flashed server-side).
 */
export function authForm({ mode = 'status' } = {}) {
    return {
        submitting: false,
        errorMsg:   '',
        statusMsg:  '',

        async submit(evt) {
            if (this.submitting) return;
            this.submitting = true;
            this.errorMsg   = '';
            this.statusMsg  = '';

            try {
                const { data } = await this.$http.post(evt.target.action, new FormData(evt.target));

                if (mode === 'redirect') {
                    // Keep the button locked through the navigation.
                    window.location.href = data.redirect || '/login';
                    return;
                }

                this.statusMsg  = data.status || '';
                this.submitting = false;
            } catch (e) {
                // The http interceptor always sets a friendly `message`; on a
                // 422 it also unwraps `errors` into `{field: [msg, …]}`.
                this.errorMsg = (e.errors && Object.values(e.errors).flat()[0]) || e.message || '';
                if (e.errors) this.$http.applyValidationErrors(evt.target, e.errors);
                this.submitting = false;
            }
        },
    };
}
