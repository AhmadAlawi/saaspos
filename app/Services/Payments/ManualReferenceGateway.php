<?php

namespace App\Services\Payments;

use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Manual-reference tender — covers the very common pattern where the shop
 * already runs a card terminal / mobile-money / cheque flow separately,
 * and the cashier just types the gateway-side reference number into POS
 * so the books match. No external API call; the cashier is the source of
 * truth for "did it actually clear".
 *
 * Slice 1 ships this so non-cash payments are usable from day one. Slice 2
 * replaces *some* of these flows with real gateway tiles (Stripe / Razorpay
 * / Flutterwave) but the manual tile stays — many shops keep using their
 * existing terminal long after the POS is installed.
 */
class ManualReferenceGateway implements PaymentGateway
{
    public function code(): string  { return 'card_manual'; }
    public function label(): string { return __('sales.payment_methods.card_manual'); }

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

    public function startPayment(string $amountMinor, string $currency, array $context): array
    {
        throw new RuntimeException('Manual-reference is a synchronous tender; startPayment is not supported.');
    }

    public function pollStatus(string $sessionId, PaymentMethod $method): array
    {
        return ['status' => 'paid'];
    }

    public function handleWebhook(Request $request, PaymentMethod $method): array
    {
        throw new RuntimeException('Manual-reference gateway has no webhook surface.');
    }
}
