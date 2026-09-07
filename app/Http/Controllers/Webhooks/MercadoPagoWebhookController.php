<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\PosPaymentSession;
use App\Services\Payments\MercadoPago\MercadoPagoGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Mercado Pago webhook receiver — `POST /webhooks/mercado_pago`.
 *
 * Public route (no auth, no CSRF — see VerifyCsrfToken middleware
 * `$except` list). Mercado Pago authenticates webhooks via a signed
 * manifest (id + request-id + ts HMAC'd with the merchant's
 * webhook_secret) — see MercadoPagoSignatureVerifier.
 *
 * Mirrors the Stripe/Razorpay/Paystack/Flutterwave controllers in
 * shape, with one twist: Mercado Pago's notifications only carry the
 * payment id, so the gateway's `handleWebhook` does an extra fetch
 * to resolve the payment status before we can mark a session paid.
 * The PosPaymentSession is correlated by `external_reference`
 * (which we set to the session uuid in `startPayment`).
 */
class MercadoPagoWebhookController extends Controller
{
    public function __invoke(Request $request, MercadoPagoGateway $gateway): JsonResponse
    {
        $method = PaymentMethod::query()
            ->where('provider', 'mercado_pago')
            ->where('is_active', true)
            ->first();

        if (!$method) {
            return response()->json(['error' => 'Mercado Pago not configured.'], 400);
        }

        try {
            $event = $gateway->handleWebhook($request, $method);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        DB::table('payment_webhook_log')->updateOrInsert(
            ['provider' => 'mercado_pago', 'gateway_event_id' => $event['event_id']],
            [
                'event_type'   => $event['event_type'],
                'payload'      => \json_encode($event['raw']),
                'processed'    => true,
                'result'       => 'logged',
                'received_at'  => now(),
                'processed_at' => now(),
            ],
        );

        // Mark the related PosPaymentSession paid only when the
        // resolved payment is `approved`. session_id ← Mercado Pago
        // external_reference (= our session uuid). The cashier
        // polling will also see this when its 2s tick lands.
        if (
            ($event['status'] ?? null) === 'approved'
            && !empty($event['session_id'])
        ) {
            $session = PosPaymentSession::query()
                ->where('gateway_session_id', $event['session_id'])
                ->first();
            if ($session && !$session->isPaid()) {
                $session->forceFill([
                    'status'             => PosPaymentSession::STATUS_PAID,
                    'gateway_payment_id' => $event['payment_id'] ?? $session->gateway_payment_id,
                    'paid_at'            => now(),
                ])->save();
            }
        }

        return response()->json(['received' => true]);
    }
}
