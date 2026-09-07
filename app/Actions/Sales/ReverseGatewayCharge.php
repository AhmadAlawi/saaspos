<?php

namespace App\Actions\Sales;

use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\Log;

/**
 * Reverses a refund's original-method amount through the same payment
 * gateway the sale was captured on (Stripe, Razorpay, Paystack, …).
 *
 * Runs AFTER {@see RecordSaleReturn} has committed — an external API call
 * must never sit inside the refund's DB transaction. It's idempotent (a
 * return already marked succeeded is skipped) and never throws: a gateway
 * failure is recorded as `gateway_refund_status = failed` so the scheduled
 * retry (routes/console.php) or an admin "Retry reversal" can re-run it,
 * while the refund itself still stands.
 *
 * Only original-method refunds trigger a gateway call — cash and
 * store-credit refunds return early with no reversal.
 */
class ReverseGatewayCharge
{
    public function __construct(private readonly PaymentGatewayManager $gateways) {}

    public function __invoke(SaleReturn $return): void
    {
        // Nothing to reverse: not an original-method refund, or already done.
        if (bccomp((string) ($return->refunded_to_original_method ?? '0'), '0', 4) <= 0) {
            return;
        }
        if ($return->gateway_refund_status === SaleReturn::GATEWAY_REFUND_SUCCEEDED) {
            return;
        }

        // Any payment captured on a supported gateway. We deliberately do NOT
        // require gateway_payment_id here: a gateway payment missing its
        // reference is a real problem worth surfacing as a failed reversal
        // (the gateway will throw a clear "missing reference" error), not
        // something to skip silently.
        // Resolve each payment's gateway from its explicit `gateway_provider`
        // OR, when that wasn't stamped, from the payment method itself (a
        // method with provider=stripe IS a Stripe payment even if the cashier
        // didn't echo the provider onto the row). Anything with a payment
        // reference but no provider still counts.
        $payments = SalePayment::query()
            ->where('sale_id', $return->sale_id)
            ->where(fn ($q) => $q->whereNotNull('gateway_provider')->orWhereNotNull('gateway_payment_id'))
            ->with('paymentMethod')
            ->get()
            ->filter(fn (SalePayment $p) => $this->gateways->supports($this->providerOf($p)))
            ->values();

        // Original method wasn't a supported gateway (e.g. a manual card
        // terminal) — the money moves off-system; leave status null (n/a).
        if ($payments->isEmpty()) {
            return;
        }

        $remaining = (string) $return->refunded_to_original_method;

        try {
            foreach ($payments as $payment) {
                if (bccomp($remaining, '0', 4) <= 0) {
                    break;
                }

                // Reverse at most what this payment captured, so a split
                // tender across gateways is spread across the right charges.
                $chunk = bccomp($remaining, (string) $payment->amount, 4) > 0
                    ? (string) $payment->amount
                    : $remaining;

                $this->gateways->for($this->providerOf($payment))->refund($payment, $chunk);

                $remaining = bcsub($remaining, $chunk, 4);
            }

            $return->forceFill([
                'gateway_refund_status'   => SaleReturn::GATEWAY_REFUND_SUCCEEDED,
                'gateway_refund_attempts' => (int) $return->gateway_refund_attempts + 1,
                'gateway_refund_error'    => null,
                'gateway_refunded_at'     => now(),
            ])->save();
        } catch (\Throwable $e) {
            Log::warning('Gateway refund reversal failed', [
                'sale_return_id' => $return->id,
                'error'          => $e->getMessage(),
            ]);

            $return->forceFill([
                'gateway_refund_status'   => SaleReturn::GATEWAY_REFUND_FAILED,
                'gateway_refund_attempts' => (int) $return->gateway_refund_attempts + 1,
                'gateway_refund_error'    => \Illuminate\Support\Str::limit($e->getMessage(), 480),
            ])->save();
        }
    }

    /** The gateway that captured a payment — explicit stamp, else the method's provider. */
    private function providerOf(SalePayment $payment): ?string
    {
        return $payment->gateway_provider ?: $payment->paymentMethod?->provider;
    }
}

