/**
 * Tiny "item added" sound for the cashier screen. Synthesised with the Web
 * Audio API (a short two-tone blip) so there's no audio file to ship and it
 * works fully offline. The AudioContext is created lazily on the first beep —
 * which always follows a user gesture (a tap/scan adds to cart), so browser
 * autoplay policies are satisfied.
 *
 * Fails silent on anything unexpected — a checkout must never break because a
 * sound couldn't play.
 */
let ctx = null;

function audioContext() {
    if (ctx) return ctx;
    const AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) return null;
    try { ctx = new AC(); } catch { ctx = null; }
    return ctx;
}

export function playAddBeep() {
    const c = audioContext();
    if (!c) return;
    try {
        if (c.state === 'suspended') c.resume().catch(() => {});

        const now  = c.currentTime;
        const osc  = c.createOscillator();
        const gain = c.createGain();

        osc.type = 'sine';
        osc.frequency.setValueAtTime(880, now);                       // A5
        osc.frequency.exponentialRampToValueAtTime(1320, now + 0.05); // quick rise → "blip"

        // Fast attack, short decay — a crisp, unobtrusive tick.
        gain.gain.setValueAtTime(0.0001, now);
        gain.gain.exponentialRampToValueAtTime(0.18, now + 0.012);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.13);

        osc.connect(gain).connect(c.destination);
        osc.start(now);
        osc.stop(now + 0.15);
    } catch { /* never let audio break the sale */ }
}
