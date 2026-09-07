/**
 * Minimal Apple Pay / Google Pay page (`pay/pos_wallet.blade.php`) —
 * mounts Stripe's Payment Request Button directly, rather than
 * redirecting to Stripe's hosted Checkout. Stripe decides at runtime
 * which wallet (if any) the customer's device/browser supports — there
 * is no API param to force "Apple Pay only" or "Google Pay only", the
 * button just shows whatever's available. If neither is available,
 * `canMakePayment()` resolves null and we fall back to the plain
 * "Pay by card" link, which reuses the existing gateway-chooser
 * `select()` endpoint (same Checkout Session redirect as before).
 *
 * Standalone public bundle — no admin http.js/axios, plain fetch only.
 */
import { loadStripe } from '@stripe/stripe-js';

const root = document.querySelector('[data-wallet-root]');
if (root) {
    const clientSecret   = root.dataset.clientSecret;
    const publishableKey = root.dataset.publishableKey;
    const currency       = root.dataset.currency;
    const amountMinor    = parseInt(root.dataset.amountMinor, 10) || 0;
    const country        = root.dataset.country || 'US';
    const merchantName   = root.dataset.merchantName || '';

    const mountEl        = document.getElementById('payment-request-button');
    const unavailableEl  = root.querySelector('[data-wallet-unavailable]');
    const fallbackForm   = root.querySelector('[data-wallet-fallback]');

    function showFallback() {
        if (mountEl) mountEl.hidden = true;
        if (unavailableEl) unavailableEl.hidden = false;
        if (fallbackForm) fallbackForm.hidden = false;
    }

    init().catch(() => showFallback());

    async function init() {
        const stripe = await loadStripe(publishableKey);
        if (!stripe) { showFallback(); return; }

        const paymentRequest = stripe.paymentRequest({
            country,
            currency: currency.toLowerCase(),
            total: { label: merchantName, amount: amountMinor },
            requestPayerName: false,
            requestPayerEmail: false,
        });

        const canPay = await paymentRequest.canMakePayment();
        if (!canPay) { showFallback(); return; }

        const elements = stripe.elements();
        const prButton = elements.create('paymentRequestButton', { paymentRequest });
        mountEl.innerHTML = '';
        mountEl.removeAttribute('data-wallet-loading');
        prButton.mount('#payment-request-button');

        paymentRequest.on('paymentmethod', async (ev) => {
            const { error } = await stripe.confirmCardPayment(
                clientSecret,
                { payment_method: ev.paymentMethod.id },
                { handleActions: false },
            );

            if (error) {
                ev.complete('fail');
                return;
            }
            ev.complete('success');

            // Standard Stripe pattern: a wallet charge can still come back
            // requiring 3D Secure — finish that step (Stripe.js renders the
            // challenge in an overlay) before treating it as settled.
            const { paymentIntent } = await stripe.retrievePaymentIntent(clientSecret);
            if (paymentIntent && paymentIntent.status === 'requires_action') {
                const confirmResult = await stripe.confirmCardPayment(clientSecret);
                if (confirmResult.error) return;
            }

            // Server-side reconciliation (ReconcilePosPaymentSession, via
            // the same status poll below) picks up the now-`succeeded`
            // PaymentIntent and flips the session to paid — the cashier's
            // own polling reacts independently, same as every other
            // gateway. This page just waits for its own poll to notice
            // `terminal: true` and reload to the server-rendered state.
        });
    }
}

/**
 * Same polling contract as resources/js/pay.js — kept as a duplicate
 * rather than a shared import since this is a standalone public bundle
 * with no shared module layer, and the logic is ~15 lines.
 */
const statusRoot = document.querySelector('[data-pay-status-url]');
if (statusRoot) {
    const url = statusRoot.getAttribute('data-pay-status-url');
    let stopped = false;

    const poll = async () => {
        if (stopped) return;
        try {
            const res = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!res.ok) return;
            const data = await res.json();
            if (data && data.terminal) {
                stopped = true;
                clearInterval(timer);
                window.location.reload();
            }
        } catch (_) {
            /* transient — next tick retries */
        }
    };

    const timer = setInterval(poll, 3000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) poll();
    });
}
