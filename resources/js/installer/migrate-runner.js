/**
 * Drives the installer's chunked migrate + seed step.
 *
 * Polls the `runStep` endpoint repeatedly — each call applies one small batch
 * of migrations or one seeder and returns progress — until the server reports
 * `done` and hands back the next URL. Keeping every request short is what lets
 * the install survive a shared-hosting gateway timeout.
 */
export function migrateRunner({ stepUrl, labels }) {
    return {
        progress: 0,
        detail: labels.preparing,
        error: null,
        running: false,

        async run() {
            if (this.running) return;
            this.running = true;
            this.error = null;

            try {
                // Loop until the server says we're done (or a step fails).
                // Each iteration is one short HTTP request.
                // eslint-disable-next-line no-constant-condition
                while (true) {
                    const data = await this.step();

                    if (!data.ok) {
                        this.error = data.error || labels.failedTitle;
                        break;
                    }

                    if (typeof data.progress === 'number') this.progress = data.progress;
                    if (data.detail) this.detail = data.detail;

                    if (data.done) {
                        if (data.redirect) window.location.assign(data.redirect);
                        break;
                    }
                }
            } catch (e) {
                this.error = (e && e.message) ? e.message : String(e);
            } finally {
                this.running = false;
            }
        },

        async step() {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            const res = await fetch(stepUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
            });

            if (!res.ok) {
                // 419 (CSRF), 422 (state) or a 5xx — surface a readable message.
                let msg = `HTTP ${res.status}`;
                try {
                    const body = await res.json();
                    if (body && body.error) msg = body.error;
                } catch (e) { /* non-JSON body */ }
                return { ok: false, error: msg };
            }

            return res.json();
        },
    };
}
