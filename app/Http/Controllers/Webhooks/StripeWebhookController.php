<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\PosPaymentSession;
use App\Services\Payments\Stripe\StripeGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Stripe webhook receiver — `POST /webhooks/stripe`.
 *
 * Public route (no auth, no CSRF — see VerifyCsrfToken middleware
 * `$except` list). Stripe signs every payload; the
 * StripeSignatureVerifier rejects anything that doesn't validate.
 *
 * Currently logs every event to `payment_webhook_log` for audit. The
 * primary cashier flow uses polling (poll-status endpoint above); the
 * webhook is the second line of defense for the "cashier connection
 * drops mid-payment" case — the row in `payment_webhook_log` can be
 * reconciled later by a background job (a future slice).
 *
 * Why one webhook controller per provider (not a generic one): each
 * provider's signature header + raw-body handling is subtly
 * different (Stripe uses `Stripe-Signature`, Razorpay uses
 * `X-Razorpay-Signature`, etc.). Keeping them separate trades a
 * little duplication for clarity.
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeGateway $gateway): JsonResponse
    {
        // The Stripe-typed payment method holds the signing secret.
        // If a company runs multiple Stripe accounts (per-store
        // overrides), this would pick by store — v1 grabs the first
        // active stripe-provider method.
        $method = PaymentMethod::query()
            ->where('provider', 'stripe')
            ->where('is_active', true)
            ->first();

        if (!$method) {
            // No Stripe config means we can't verify the signature —
            // 400 so Stripe retries, allowing the admin to fix the
            // misconfiguration before the event is lost.
            return response()->json(['error' => 'Stripe not configured.'], 400);
        }

        try {
            $event = $gateway->handleWebhook($request, $method);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        // Persist for audit — `payment_webhook_log` is the table the
        // original schema reserved for this. Use the gateway event
        // id as the dedupe key; Stripe re-sends events on retry.
        DB::table('payment_webhook_log')->updateOrInsert(
            ['provider' => 'stripe', 'gateway_event_id' => $event['event_id']],
            [
                'event_type'   => $event['event_type'],
                'payload'      => \json_encode($event['raw']),
                'processed'    => true,
                'result'       => 'logged',
                'received_at'  => now(),
                'processed_at' => now(),
            ],
        );

        // Mark the related PosPaymentSession paid when Stripe confirms
        // checkout completed. This is the "cashier loses connection"
        // safety net — the cashier polling will also see the change
        // when the network returns. We correlate by the Stripe session
        // id we stamped on the PosPaymentSession during select().
        if ($event['event_type'] === 'checkout.session.completed' && !empty($event['session_id'])) {
            $session = PosPaymentSession::query()
                ->where('gateway_session_id', $event['session_id'])
                ->first();
            if ($session && ! $session->isPaid()) {
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
