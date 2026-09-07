<?php

namespace App\Services\Payments;

use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Cash tender — the canonical synchronous payment. The cashier physically
 * has the money before the sale completes; nothing to verify, nothing to
 * call externally. Refunds are tracked but executed by the cashier
 * handing back cash from the till.
 */
class CashGateway implements PaymentGateway
{
    public function code(): string  { return 'cash'; }
    public function label(): string { return __('sales.payment_methods.cash'); }

    public function createOrder(string $amount, Sale $sale): array
    {
        return [];
    }

    public function verify(SalePayment $payment): bool
    {
        return true;
    }

    public function refund(SalePayment $payment, string $amount): bool
    {
        return true;
    }

    /* Synchronous tenders don't have a hosted-payment session. The
     * interface methods exist so the cashier controller can dispatch
     * uniformly; callers should never reach these for cash. */

    public function startPayment(string $amountMinor, string $currency, array $context): array
    {
        throw new RuntimeException('Cash is a synchronous tender; startPayment is not supported.');
    }

    public function pollStatus(string $sessionId, PaymentMethod $method): array
    {
        return ['status' => 'paid'];
    }

    public function handleWebhook(Request $request, PaymentMethod $method): array
    {
        throw new RuntimeException('Cash gateway has no webhook surface.');
    }
}
