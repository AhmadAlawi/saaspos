/**
 * AJAX driver for the installer's database step.
 *
 * Submits the credentials to `install.database.save` via fetch so a failed
 * connection test surfaces inline — the page never reloads and the typed
 * values are preserved. On success the server returns the next step's URL
 * and we navigate to it (the chunked migrate/seed page takes over there).
 *
 * Progressive enhancement: if JS is off the form still POSTs normally and
 * the controller falls back to a redirect / validation-error round-trip.
 */
export function installerDbForm({ actionUrl, genericError }) {
    return {
        submitting: false,
        error: null,

        async submit(event) {
            if (this.submitting) return;
            this.submitting = true;
            this.error = null;

            const form  = event.target;
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            try {
                const res = await fetch(actionUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                    body: new FormData(form),
                });

                let data = {};
                try { data = await res.json(); } catch (e) { /* non-JSON body */ }

                if (res.ok && data.ok && data.redirect) {
                    // Keep the button spinning through the navigation.
                    window.location.assign(data.redirect);
                    return;
                }

                this.error = data.error || genericError;
                this.submitting = false;
            } catch (e) {
                this.error = genericError;
                this.submitting = false;
            }
        },
    };
}
