<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\PosPaymentSession;
use App\Services\Payments\Flutterwave\FlutterwaveGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Flutterwave webhook receiver — `POST /webhooks/flutterwave`.
 *
 * Public route (no auth, no CSRF — see VerifyCsrfToken middleware
 * `$except` list). Flutterwave authenticates webhooks by echoing
 * the merchant's configured "secret hash" string in the `verif-hash`
 * header — see FlutterwaveSignatureVerifier.
 *
 * Mirrors Stripe/Razorpay/Paystack controllers — logs every event
 * to `payment_webhook_log` for audit, and marks `PosPaymentSession`
 * paid on `charge.completed` with status `successful`.
 */
class FlutterwaveWebhookController extends Controller
{
    public function __invoke(Request $request, FlutterwaveGateway $gateway): JsonResponse
    {
        $method = PaymentMethod::query()
            ->where('provider', 'flutterwave')
            ->where('is_active', true)
            ->first();

        if (!$method) {
            return response()->json(['error' => 'Flutterwave not configured.'], 400);
        }

        try {
            $event = $gateway->handleWebhook($request, $method);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        DB::table('payment_webhook_log')->updateOrInsert(
            ['provider' => 'flutterwave', 'gateway_event_id' => $event['event_id']],
            [
                'event_type'   => $event['event_type'],
                'payload'      => \json_encode($event['raw']),
                'processed'    => true,
                'result'       => 'logged',
                'received_at'  => now(),
                'processed_at' => now(),
            ],
        );

        // Mark the related PosPaymentSession paid when Flutterwave
        // confirms a charge.completed with status `successful`. The
        // cashier polling will also see this when its 2s tick lands.
        // We correlate by tx_ref (our session uuid).
        if (
            $event['event_type'] === 'charge.completed'
            && ($event['status'] ?? null) === 'successful'
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
