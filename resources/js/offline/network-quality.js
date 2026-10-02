/**
 * Network Information API wrapper (Chromium-only — every terminal this
 * app runs on is Chrome/Edge, so no feature-detect fallback needed
 * beyond the existence check itself).
 *
 * Distinct from `connectivity.js`: that module answers "is the heartbeat
 * succeeding" (a binary/three-state reachability question). This module
 * answers "even when reachable, is the link itself slow or metered" —
 * `navigator.connection` reports the OS/browser's own read of the radio
 * (2G/3G/4G, RTT, save-data), available even on a link that's currently
 * succeeding every request, just slowly. Used to proactively back off
 * background work (catalog auto-refresh cadence/page size, image
 * prefetching) BEFORE anything actually times out or fails.
 */

function connectionInfo() {
    return (typeof navigator !== 'undefined' && (navigator.connection || navigator.mozConnection || navigator.webkitConnection)) || null;
}

/** True on 2G/slow-2g, or when the user has Data Saver on. Unknown → false (never restrict by default). */
export function isSlowConnection() {
    const c = connectionInfo();
    if (!c) return false;
    if (c.saveData) return true;
    return c.effectiveType === 'slow-2g' || c.effectiveType === '2g';
}

/** Coarse tri-state for callers that want to distinguish "unknown" from "known-fast". */
export function connectionQuality() {
    const c = connectionInfo();
    if (!c || !c.effectiveType) return 'unknown';
    if (c.effectiveType === 'slow-2g' || c.effectiveType === '2g') return 'slow';
    if (c.effectiveType === '3g') return 'moderate';
    return 'fast';
}

/** Subscribe to connection changes (network type flips, Data Saver toggled). No-op / returns a no-op unsubscribe where unsupported. */
export function onConnectionChange(fn) {
    const c = connectionInfo();
    if (!c || typeof c.addEventListener !== 'function') return () => {};
    const handler = () => fn(connectionQuality());
    c.addEventListener('change', handler);
    return () => c.removeEventListener('change', handler);
}
