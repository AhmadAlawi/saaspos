<?php

namespace App\Actions\Payments;

use App\Models\PaymentMethod;
use App\Models\PosPaymentSession;
use App\Services\Payments\Flutterwave\FlutterwaveGateway;
use App\Services\Payments\MercadoPago\MercadoPagoGateway;
use App\Services\Payments\Paystack\PaystackGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\Razorpay\RazorpayGateway;
use App\Services\Payments\Stripe\StripeGateway;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Actively reconcile a QR-chooser payment session against its gateway.
 *
 * The session is marked `paid` by two async signals: the provider's webhook
 * (`payment_link.paid` etc.) and the customer's return-redirect poll
 * ({@see \App\Http\Controllers\Pay\CustomerPayController::return()}). On a
 * self-hosted install neither is guaranteed — a shared-hosting box often has
 * no publicly reachable webhook URL, and a customer can close the gateway tab
 * before the return redirect fires. When both miss, the money is taken but the
 * session sits in `selected` until its TTL lapses, and the cashier/kiosk shows
 * "payment didn't go through" for a payment that actually succeeded.
 *
 * This closes that gap: whenever the cashier/kiosk/customer status poll runs
 * for a still-open session, we ask the gateway directly (`pollStatus`) and
 * promote the row to `paid` the moment the provider confirms it — so the flow
 * completes with no webhook configured at all. Poll-driven, so it costs nothing
 * until a customer is actually mid-payment.
 *
 * Conservative by design: it only ever promotes an open session to `paid`.
 * Failure/expiry stays with the existing signals (TTL lapse, start-error,
 * cashier cancel) so a transient gateway hiccup can never flip a live session
 * to failed.
 */
class ReconcilePosPaymentSession
{
    /** Providers whose `pollStatus()` is wired. Mirrors CustomerPayController. */
    private const WIRED = ['stripe', 'razorpay', 'paystack', 'flutterwave', 'mercado_pago'];

    public function __invoke(PosPaymentSession $session): PosPaymentSession
    {
        // Already resolved, or nothing to poll against (no gateway picked yet).
        if ($session->isTerminal() || ! $session->gateway_session_id) {
            return $session;
        }

        $method = $session->paymentMethod;
        if (! $method || ! \in_array($method->provider, self::WIRED, true)) {
            return $session;
        }

        // Throttle: hit the provider's API at most once every few seconds per
        // session, even though both the till and the customer page may poll on
        // a 2s tick. Cache::add is atomic add-if-absent — a no-op key expiry
        // paces the calls without a schema column.
        if (! Cache::add('pos_session_reconcile:'.$session->uuid, 1, 3)) {
            return $session;
        }

        try {
            $status = $this->gatewayFor($method)->pollStatus((string) $session->gateway_session_id, $method);
        } catch (RuntimeException) {
            return $session;   // transient — the next tick tries again
        }

        if (($status['status'] ?? 'pending') === 'paid' && ! $session->isPaid()) {
            $session->forceFill([
                'status'             => PosPaymentSession::STATUS_PAID,
                'gateway_payment_id' => $status['payment_id'] ?? $session->gateway_payment_id,
                'paid_at'            => now(),
            ])->save();
        }

        return $session;
    }

    /** Per-provider dispatch — mirrors CustomerPayController::gatewayFor(). */
    private function gatewayFor(PaymentMethod $method): PaymentGateway
    {
        return match ($method->provider) {
            'stripe'       => app(StripeGateway::class),
            'razorpay'     => app(RazorpayGateway::class),
            'paystack'     => app(PaystackGateway::class),
            'flutterwave'  => app(FlutterwaveGateway::class),
            'mercado_pago' => app(MercadoPagoGateway::class),
            default        => throw new RuntimeException("No gateway wired for provider '{$method->provider}'."),
        };
    }
}
