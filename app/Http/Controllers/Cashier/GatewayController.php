<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Services\Payments\Flutterwave\FlutterwaveGateway;
use App\Services\Payments\MercadoPago\MercadoPagoGateway;
use App\Services\Payments\Paystack\PaystackGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\Razorpay\RazorpayGateway;
use App\Services\Payments\Stripe\StripeGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Cashier-facing payment-gateway controller — the single endpoint
 * shape the Alpine factory talks to regardless of which provider is
 * picked. Dispatch happens here by `payment_method.provider`:
 *
 *     stripe   → StripeGateway
 *     razorpay → RazorpayGateway   (Slice 2)
 *     paystack → PaystackGateway   (Slice 3)
 *     ...
 *
 * Adding a provider:
 *   1. Implement the gateway class (App\Services\Payments\<Vendor>\<Vendor>Gateway).
 *   2. Add the case in `gatewayFor()` below.
 *   3. The cashier flow + Settings UI work without any other change.
 */
class GatewayController extends Controller
{
    /**
     * Start a hosted payment session — `POST /cashier/gateways/start`.
     *
     * Body:
     *   payment_method_id (int, required)  — which payment_methods row drives this
     *   amount            (string, required) — decimal-string in store currency
     *   currency          (string, required) — ISO 4217
     *   local_uuid        (string, required) — cashier's client UUID for the cart
     *   description       (string, optional)
     *
     * Returns: { session_id, url, expires_at }
     */
    public function start(Request $request): JsonResponse
    {
        $this->authorize('create', Sale::class);

        $data = $request->validate([
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'amount'            => ['required', 'string'],
            'currency'          => ['required', 'string', 'size:3'],
            'local_uuid'        => ['required', 'string', 'max:64'],
            'description'       => ['nullable', 'string', 'max:191'],
        ]);

        $method  = PaymentMethod::query()->findOrFail($data['payment_method_id']);
        $gateway = $this->gatewayFor($method);

        try {
            $result = $gateway->startPayment(
                amountMinor: $this->toMinorString($data['amount'], $data['currency']),
                currency:    $data['currency'],
                context: [
                    'local_uuid'     => $data['local_uuid'],
                    'description'    => $data['description'] ?? null,
                    'payment_method' => $method,
                ],
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors'  => ['_action' => [$e->getMessage()]],
            ], 422);
        }

        return response()->json($result);
    }

    /**
     * Poll session status — `GET /cashier/gateways/status?session_id=X&payment_method_id=Y`.
     *
     * Returns: { status: 'pending'|'paid'|'failed', payment_id, amount_minor }
     */
    public function status(Request $request): JsonResponse
    {
        $this->authorize('create', Sale::class);

        $data = $request->validate([
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'session_id'        => ['required', 'string', 'max:191'],
        ]);

        $method  = PaymentMethod::query()->findOrFail($data['payment_method_id']);
        $gateway = $this->gatewayFor($method);

        try {
            $result = $gateway->pollStatus($data['session_id'], $method);
        } catch (RuntimeException $e) {
            // Transient errors: tell the client we don't know yet.
            return response()->json(['status' => 'unknown', 'message' => $e->getMessage()]);
        }

        return response()->json($result);
    }

    /** Per-provider dispatch — one match arm per wired gateway. */
    private function gatewayFor(PaymentMethod $method): PaymentGateway
    {
        return match ($method->provider) {
            'stripe'       => app(StripeGateway::class),
            'razorpay'     => app(RazorpayGateway::class),
            'paystack'     => app(PaystackGateway::class),
            'flutterwave'  => app(FlutterwaveGateway::class),
            'mercado_pago' => app(MercadoPagoGateway::class),
            default        => throw new RuntimeException("No gateway implementation for provider '{$method->provider}'."),
        };
    }

    /**
     * Convert decimal-string `amount` to the gateway's expected minor
     * unit, returned as a string (the gateway class casts to int).
     * We default to 2 decimals; the StripeGateway re-derives the
     * currency-specific exponent itself for the SDK call, this just
     * normalizes the wire format.
     */
    private function toMinorString(string $amount, string $currency): string
    {
        $zero  = ['JPY', 'KRW', 'VND', 'CLP', 'IDR'];
        $three = ['BHD', 'JOD', 'KWD', 'OMR', 'TND'];
        $upper = \strtoupper($currency);
        $dec   = \in_array($upper, $zero, true) ? 0 : (\in_array($upper, $three, true) ? 3 : 2);
        return \bcmul($amount, \bcpow('10', (string) $dec, 0), 0);
    }
}
