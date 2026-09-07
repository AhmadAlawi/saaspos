/**
 * Dashboard "Get your store ready" checklist card.
 *
 * Owns exactly one behaviour: dismissing the card. The dismissal is persisted
 * company-wide server-side, so we hide it optimistically and let the POST
 * settle in the background — a failed dismiss just means the card returns on
 * the next load, which is the safe direction to fail.
 */
import { posPost } from '../lib/http.js';

export function setupChecklist(dismissUrl = '') {
    return {
        visible: true,

        async dismiss() {
            this.visible = false;
            if (!dismissUrl) return;
            try {
                await posPost(dismissUrl, {});
            } catch (e) {
                /* best-effort — the card simply reappears next visit */
            }
        },
    };
}
