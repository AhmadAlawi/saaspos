<?php

namespace App\Services\Payments;

use App\Services\Payments\Flutterwave\FlutterwaveGateway;
use App\Services\Payments\MercadoPago\MercadoPagoGateway;
use App\Services\Payments\Paystack\PaystackGateway;
use App\Services\Payments\Razorpay\RazorpayGateway;
use App\Services\Payments\Stripe\StripeGateway;

/**
 * Resolves the {@see PaymentGateway} implementation for a stored
 * `payment_methods.provider` / `sale_payments.gateway_provider` string.
 *
 * One place for the provider → gateway mapping so the cashier flow, the
 * refund reversal, and any future consumer stay in agreement. Adding a new
 * gateway is a single arm here.
 */
class PaymentGatewayManager
{
    /** @var array<string, class-string<PaymentGateway>> */
    private const MAP = [
        'stripe'       => StripeGateway::class,
        'razorpay'     => RazorpayGateway::class,
        'paystack'     => PaystackGateway::class,
        'flutterwave'  => FlutterwaveGateway::class,
        'mercado_pago' => MercadoPagoGateway::class,
    ];

    public function supports(?string $provider): bool
    {
        return $provider !== null && array_key_exists($provider, self::MAP);
    }

    /** @throws \RuntimeException when the provider has no wired gateway. */
    public function for(string $provider): PaymentGateway
    {
        $class = self::MAP[$provider]
            ?? throw new \RuntimeException("No gateway implementation for provider '{$provider}'.");

        return app($class);
    }
}
