/**
 * Kiosk feedback sounds — synthesized, not sampled.
 *
 * A self-order kiosk needs an audible "yes, that went in the bag" the moment a
 * tile is tapped: the shopper's eyes are on the product grid, not the cart bar.
 *
 * We generate the tones with the Web Audio API rather than shipping .mp3 files
 * because (a) the kiosk runs offline from a service worker and an un-cached
 * audio asset would silently fail, (b) it keeps the bundle free of binaries a
 * self-hosted install has to license, and (c) two oscillators cost nothing.
 *
 * Browsers refuse to start an AudioContext outside a user gesture, so the
 * context is created lazily on the first tap (which is exactly when the first
 * sound is wanted) and resumed if the browser suspended it.
 */

let ctx = null;
let enabled = true;

/** Toggle every kiosk sound (terminal config: `kiosk_config.sound_enabled`). */
export function setSoundEnabled(on) {
    enabled = !!on;
}

/** Lazily build (and un-suspend) the shared AudioContext. */
function audio() {
    if (!enabled) return null;
    try {
        const Ctor = window.AudioContext || window.webkitAudioContext;
        if (!Ctor) return null;
        if (!ctx) ctx = new Ctor();
        // Autoplay policy parks the context until a gesture resumes it.
        if (ctx.state === 'suspended') ctx.resume();
        return ctx;
    } catch (e) {
        return null;   // no audio hardware / blocked — stay silent, never throw
    }
}

/**
 * One short shaped tone. The gain ramps rather than switching, because a hard
 * gate on a sine wave produces an audible click at both ends.
 */
function tone(freq, startAt, duration, peak = 0.18) {
    const ac = audio();
    if (!ac) return;

    const osc = ac.createOscillator();
    const gain = ac.createGain();
    osc.type = 'sine';
    osc.frequency.setValueAtTime(freq, startAt);

    gain.gain.setValueAtTime(0.0001, startAt);
    gain.gain.exponentialRampToValueAtTime(peak, startAt + 0.012);
    gain.gain.exponentialRampToValueAtTime(0.0001, startAt + duration);

    osc.connect(gain).connect(ac.destination);
    osc.start(startAt);
    osc.stop(startAt + duration + 0.02);
}

/** Product added to the cart — a bright two-note rise. */
export function playAdd() {
    const ac = audio();
    if (!ac) return;
    const t = ac.currentTime;
    tone(880, t, 0.09);            // A5
    tone(1318.5, t + 0.07, 0.11);  // E6
}

/** Something the shopper can't have (out of stock, required field). */
export function playReject() {
    const ac = audio();
    if (!ac) return;
    const t = ac.currentTime;
    tone(220, t, 0.16, 0.14);
    tone(165, t + 0.1, 0.18, 0.14);
}

/** Order placed / payment taken — a three-note confirmation. */
export function playSuccess() {
    const ac = audio();
    if (!ac) return;
    const t = ac.currentTime;
    tone(659.3, t, 0.1);            // E5
    tone(880, t + 0.09, 0.1);       // A5
    tone(1174.7, t + 0.18, 0.22);   // D6
}
