<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\PosPaymentSession;
use App\Services\Payments\Paystack\PaystackGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Paystack webhook receiver — `POST /webhooks/paystack`.
 *
 * Public route (no auth, no CSRF — see VerifyCsrfToken middleware
 * `$except` list). Paystack signs every payload with the merchant's
 * secret_key; the PaystackSignatureVerifier rejects anything that
 * doesn't validate.
 *
 * Mirrors Stripe/Razorpay controllers in shape — logs every event to
 * `payment_webhook_log` for audit, and marks `PosPaymentSession` paid
 * on `charge.success`.
 */
class PaystackWebhookController extends Controller
{
    public function __invoke(Request $request, PaystackGateway $gateway): JsonResponse
    {
        $method = PaymentMethod::query()
            ->where('provider', 'paystack')
            ->where('is_active', true)
            ->first();

        if (!$method) {
            return response()->json(['error' => 'Paystack not configured.'], 400);
        }

        try {
            $event = $gateway->handleWebhook($request, $method);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        DB::table('payment_webhook_log')->updateOrInsert(
            ['provider' => 'paystack', 'gateway_event_id' => $event['event_id']],
            [
                'event_type'   => $event['event_type'],
                'payload'      => \json_encode($event['raw']),
                'processed'    => true,
                'result'       => 'logged',
                'received_at'  => now(),
                'processed_at' => now(),
            ],
        );

        // Mark the related PosPaymentSession paid when Paystack
        // confirms charge.success. The cashier polling will also
        // see this when its 2s tick lands — webhook is the safety
        // net for the connection-drop case. We correlate by the
        // Paystack reference (which IS our session uuid — see
        // PaystackGateway::startPayment).
        if ($event['event_type'] === 'charge.success' && !empty($event['session_id'])) {
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
