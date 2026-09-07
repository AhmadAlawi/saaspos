/**
 * Public price-check page (`pricing/check.blade.php`) — scan a barcode
 * with the phone's camera, or type it, and show just the product name
 * + selling price. Standalone public bundle — no admin http.js/axios,
 * plain fetch only (same convention as pay.js / pay-wallet.js).
 *
 * The camera module is dynamically imported, same lazy-load reasoning
 * as the cashier's camera scanner: ZXing is heavy and most visits here
 * will just tap-scan once, so it shouldn't bloat the initial page load.
 */
import { cameraScanSupported } from './hardware/camera-scanner.js';

const root = document.querySelector('[data-pricing-root]');
if (root) {
    const lookupUrl   = root.dataset.lookupUrl;
    const videoWrap    = root.querySelector('[data-video-wrap]');
    const videoEl       = root.querySelector('[data-video]');
    const startBtn      = root.querySelector('[data-start-scan]');
    const stopBtn        = root.querySelector('[data-stop-scan]');
    const cameraErrorEl = root.querySelector('[data-camera-error]');
    const manualForm    = root.querySelector('[data-manual-form]');
    const resultEl       = root.querySelector('[data-result]');
    const resultNameEl  = root.querySelector('[data-result-name]');
    const resultPriceEl = root.querySelector('[data-result-price]');
    const notFoundEl     = root.querySelector('[data-not-found]');
    const scanAgainBtn  = root.querySelector('[data-scan-again]');

    let scanner = null;

    function resetPanels() {
        resultEl.hidden = true;
        notFoundEl.hidden = true;
        cameraErrorEl.hidden = true;
    }

    async function stopScanning() {
        scanner?.stop();
        scanner = null;
        videoWrap.hidden = true;
        stopBtn.hidden = true;
        startBtn.hidden = false;
    }

    async function startScanning() {
        resetPanels();
        if (!cameraScanSupported()) {
            cameraErrorEl.textContent = cameraErrorEl.dataset.unsupported;
            cameraErrorEl.hidden = false;
            return;
        }

        videoWrap.hidden = false;
        startBtn.hidden = true;
        stopBtn.hidden = false;

        try {
            const { CameraBarcodeScanner } = await import('./hardware/camera-scanner.js');
            scanner = new CameraBarcodeScanner(videoEl);
            await scanner.start((text) => onScan(text));
        } catch (e) {
            await stopScanning();
            cameraErrorEl.textContent = cameraErrorEl.dataset.denied;
            cameraErrorEl.hidden = false;
        }
    }

    async function onScan(text) {
        const code = String(text || '').trim();
        if (!code) return;
        await stopScanning();
        await lookup(code);
    }

    async function lookup(code) {
        resetPanels();
        try {
            const res = await fetch(`${lookupUrl}?code=${encodeURIComponent(code)}`, {
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();
            if (data?.found) {
                resultNameEl.textContent = data.name;
                resultPriceEl.textContent = data.price;
                resultEl.hidden = false;
            } else {
                notFoundEl.hidden = false;
            }
        } catch (_) {
            notFoundEl.hidden = false;
        }
    }

    startBtn.addEventListener('click', startScanning);
    stopBtn.addEventListener('click', stopScanning);

    manualForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const code = new FormData(manualForm).get('code');
        if (code && String(code).trim()) lookup(String(code).trim());
    });

    scanAgainBtn.addEventListener('click', () => {
        resetPanels();
        manualForm.reset();
    });
}
