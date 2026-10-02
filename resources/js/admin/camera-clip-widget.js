import { posGet, posPost } from '../lib/http.js';

/**
 * Async "go fetch this footage" widget — shared by the invoice camera
 * panel (Sales → show, subjectType 'sale') and the Activity Log
 * report's per-row camera button (subjectType 'activity'). Beta —
 * super-admin only, gated server-side by CameraClipController.
 *
 * Checks whether a clip request already exists for this subject (so
 * revisiting the same invoice or activity row later finds it without
 * re-fetching) — this is a read-only lookup, it never queues a fetch
 * just from being viewed. Clicking "Get video" is what actually
 * creates the request; while pending/processing this polls every 3s
 * until it lands on `ready` or `failed`.
 *
 * Two ways to trigger the initial lookup:
 *   - `x-init="init()"` — eager, for a page with exactly ONE of these
 *     (the invoice camera panel).
 *   - `open()` — lazy, for a page with MANY of these on one screen
 *     (the Activity Log report's per-row camera button): checking on
 *     page load would mean one GET per row firing at once the instant
 *     the page renders, the same worker-pool-burst problem the camera
 *     live-view grid hit earlier. `open()` only looks it up the first
 *     time that row is actually expanded.
 *
 * The ready video is NEVER autoplayed (`preload="none"`) — these
 * clips can be tens of MB and the point of the whole async redesign
 * was to stop tying up the page waiting on the NVR; a video element
 * that eagerly buffers on render would undo that.
 */
export function cameraClipWidget({ subjectType, subjectId, lookupUrl, storeUrl }) {
    return {
        clip: null,
        loading: false,
        timer: null,
        expanded: false,
        checked: false,

        /** Lazy trigger for a many-per-page usage — see doc-comment. */
        open() {
            this.expanded = !this.expanded;
            if (this.expanded && ! this.checked) this.init();
        },

        init() {
            this.checked = true;
            posGet(`${lookupUrl}?subject_type=${subjectType}&subject_id=${subjectId}`)
                .then(({ data }) => {
                    this.clip = data.clip;
                    if (this.clip && (this.clip.status === 'pending' || this.clip.status === 'processing')) {
                        this.startPolling();
                    }
                })
                .catch(() => {}); // silent — the "Get video" button still works as the fallback
        },

        request() {
            if (this.loading) return;
            this.loading = true;
            posPost(storeUrl, { subject_type: subjectType, subject_id: subjectId })
                .then(({ data }) => {
                    this.clip = data.clip;
                    this.startPolling();
                })
                .catch((e) => {
                    this.clip = { status: 'failed', error: e?.message || 'Could not request footage.' };
                })
                .finally(() => { this.loading = false; });
        },

        startPolling() {
            if (this.timer || !this.clip?.id) return;
            this.timer = setInterval(() => {
                posGet(`/admin/camera-clips/${this.clip.id}`)
                    .then(({ data }) => {
                        this.clip = data.clip;
                        if (this.clip.status === 'ready' || this.clip.status === 'failed') this.stopPolling();
                    })
                    .catch(() => this.stopPolling());
            }, 3000);
        },

        stopPolling() {
            clearInterval(this.timer);
            this.timer = null;
        },
    };
}
