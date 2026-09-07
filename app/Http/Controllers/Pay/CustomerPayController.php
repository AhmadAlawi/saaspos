<?php

namespace App\Http\Controllers\Pay;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\PosPaymentSession;
use App\Services\Payments\Flutterwave\FlutterwaveGateway;
use App\Services\Payments\MercadoPago\MercadoPagoGateway;
use App\Services\Payments\Paystack\PaystackGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\Razorpay\RazorpayGateway;
use App\Services\Payments\Stripe\StripeGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use RuntimeException;

/**
 * Customer-facing payment chooser — what scans of the cashier's QR
 * land on. Public route (no auth) because the customer is not a
 * logged-in user; the session UUID is the un-guessable token.
 *
 *   GET  /pay/pos/{uuid}              — chooser page
 *   POST /pay/pos/{uuid}/select       — customer picks a provider
 *                                       → server creates gateway
 *                                         session → redirect to it
 *   GET  /pay/pos/{uuid}/return       — gateway-success return URL
 *
 * Mobile-friendly standalone layout — NO admin chrome, NO cashier
 * chrome. Anyone with the URL can pay; only the cashier sees the
 * resulting confirmation.
 */
class CustomerPayController extends Controller
{
    /** Providers currently wired through this controller. Extend
     *  alongside `gatewayFor()` + `credsLookComplete()` as new
     *  gateways land. */
    private const WIRED_PROVIDERS = ['stripe', 'razorpay', 'paystack', 'flutterwave', 'mercado_pago'];

    public function show(string $uuid): RedirectResponse|View|Response
    {
        $session = PosPaymentSession::query()->where('uuid', $uuid)->first();
        if (! $session) {
            return response()->view('pay.invalid', [], 404);
        }
        if ($session->isExpired()) {
            $session->forceFill(['status' => PosPaymentSession::STATUS_EXPIRED])->save();
        }
        // A terminal session is no longer scan-able. The most common
        // case is the cashier hitting "Cancel & switch to cash" while
        // the customer still has the QR open — the chooser must show a
        // dead-link page instead of live gateway tiles. (Also covers
        // already-paid / failed / expired.) Mirrors the guard in
        // select() so a stale tile-tap can never reach a gateway.
        if ($session->isTerminal()) {
            return response()->view('pay.invalid', [], 410);   // gone
        }

        // List every configured + active gateway as a chooser tile.
        // Three filter passes:
        //   1. Drop tiles with incomplete credentials (no point
        //      advertising a dead tile).
        //      Stripe + Razorpay take any currency; the regional
        //      gateways (Paystack / Flutterwave / Mercado Pago) only
        //      settle their supported set, so a tile that can't handle
        //      this currency would dead-end at the gateway. Track what
        //      we hide so the view can surface a hint.
        //   2. Honour the session's allow-list. A kiosk stamps the gateways
        //      its terminal was configured with; the cashier's generic QR
        //      leaves it null and every gateway stays on offer.
        $configured = PaymentMethod::query()
            ->whereIn('provider', self::WIRED_PROVIDERS)
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'code', 'name', 'provider', 'provider_credentials'])
            ->filter(fn (PaymentMethod $m) => $this->credsLookComplete($m))
            ->filter(fn (PaymentMethod $m) => $session->allowsMethod((int) $m->id));

        [$methods, $hiddenForCurrency] = $configured->partition(
            fn (PaymentMethod $m) => $this->methodSupportsCurrency($m, (string) $session->currency),
        );

        // Restricted to a single gateway → the chooser is a dead step, so send
        // the customer straight there. Only from `pending`: a refresh of an
        // already-`selected` session must not open a second gateway session.
        if ($session->allowedMethodIds() !== []
            && $methods->count() === 1
            && $session->status === PosPaymentSession::STATUS_PENDING) {
            $method = $methods->first();

            // Apple Pay / Google Pay button on the cashier — render the
            // minimal Payment Request Button page directly instead of
            // redirecting to Stripe's hosted Checkout.
            if ($method->provider === 'stripe' && $session->wallet_brand) {
                return $this->startWithWallet($session, $method);
            }

            return $this->startWithMethod($session, $method);
        }

        return view('pay.pos', [
            'session'             => $session,
            'methods'             => $methods->values(),
            'hidden_for_currency' => $hiddenForCurrency->values(),
            'company'             => Company::current() ?? new Company(),
        ]);
    }

    /**
     * Public, auth-free status poll for the customer's open pay page.
     * The page's `pay.js` hits this every few seconds so it can react
     * live when the cashier cancels, the TTL lapses, or the charge is
     * paid elsewhere — reloading to show the server-rendered terminal
     * state. Lazy-expires the row like the cashier-side status endpoint.
     */
    public function status(string $uuid, \App\Actions\Payments\ReconcilePosPaymentSession $reconcile): JsonResponse
    {
        $session = PosPaymentSession::query()->where('uuid', $uuid)->first();
        if (! $session) {
            return response()->json(['status' => 'expired', 'terminal' => true, 'paid' => false]);
        }
        // Confirm with the gateway before answering — same reasoning as the
        // cashier/kiosk status endpoint: don't depend on the webhook or the
        // return redirect alone to notice a completed payment.
        $reconcile($session);
        if ($session->isExpired()) {
            $session->forceFill(['status' => PosPaymentSession::STATUS_EXPIRED])->save();
        }

        return response()->json([
            'status'   => $session->status,
            'terminal' => $session->isTerminal(),
            'paid'     => $session->isPaid(),
        ]);
    }

    public function select(Request $request, string $uuid): RedirectResponse|View|Response
    {
        $data = $request->validate([
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
        ]);

        $session = PosPaymentSession::query()->where('uuid', $uuid)->first();
        if (! $session || $session->isTerminal() || $session->isExpired()) {
            return response()->view('pay.invalid', [], 410);    // gone
        }

        $method = PaymentMethod::query()->findOrFail($data['payment_method_id']);

        // Never let a hand-crafted POST charge through a gateway this session
        // isn't allowed to use — the tiles are filtered in show(), but the
        // endpoint is public, so the allow-list is re-checked here.
        if (! $session->allowsMethod((int) $method->id)) {
            return response()->view('pay.invalid', [], 403);
        }

        return $this->startWithMethod($session, $method);
    }

    /**
     * Open a gateway session for `$method` and redirect the customer to it.
     *
     * Shared by the chooser's POST and by show()'s auto-forward when a kiosk
     * session allows exactly one gateway. Every failure path lands the
     * customer on the friendly invalid-link page rather than a raw 5xx.
     */
    private function startWithMethod(PosPaymentSession $session, PaymentMethod $method): RedirectResponse|View|Response
    {
        // Stamp the method onto the session before we go talk to the
        // gateway — that way the cashier's polling can already show
        // "<Provider> selected — waiting for customer to pay".
        $session->forceFill([
            'status'            => PosPaymentSession::STATUS_SELECTED,
            'payment_method_id' => $method->id,
        ])->save();

        try {
            $gateway     = $this->gatewayFor($method);
            $amountMinor = $this->toMinor((string) $session->amount, $session->currency);
            $started     = $gateway->startPayment(
                amountMinor: (string) $amountMinor,
                currency:    $session->currency,
                context: [
                    'local_uuid'     => $session->uuid,
                    'description'    => 'POS sale',
                    'payment_method' => $method,
                    'return_url'     => $this->returnUrlFor($method->provider, $session->uuid),
                ],
            );
            $session->forceFill([
                'gateway_session_id' => $started['session_id'] ?? null,
            ])->save();
            return redirect()->away($started['url']);
        } catch (\Throwable $e) {
            // Anything thrown by the gateway lands here: gateway-side
            // RuntimeException (Stripe/Razorpay/Paystack/Flutterwave/
            // Mercado Pago error envelopes), Laravel's
            // ConnectionException (origin can't reach the provider's
            // host), or a bare TypeError (synthesized email etc.).
            // We log the full trace for the admin and show the
            // customer a friendly invalid-link screen — never let an
            // unhandled exception bubble up to a Cloudflare 502.
            report($e);
            $session->forceFill([
                'status'          => PosPaymentSession::STATUS_FAILED,
                'failure_message' => $e->getMessage(),
            ])->save();
            // 400, not 502 — when origin returns a 5xx, Cloudflare
            // intercepts it and shows its own "Bad gateway" page so
            // the customer never sees our actual error message.
            return response()->view('pay.invalid', ['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Apple Pay / Google Pay — renders `pay.pos_wallet`, a minimal page
     * with just a Stripe Payment Request Button, instead of redirecting
     * to Stripe's hosted Checkout. Creates a PaymentIntent (not a
     * Checkout Session) via `StripeGateway::startWalletPayment()`, which
     * isn't part of the generic `PaymentGateway` contract since there's
     * no `url` to redirect to here.
     */
    private function startWithWallet(PosPaymentSession $session, PaymentMethod $method): View|Response
    {
        $session->forceFill([
            'status'            => PosPaymentSession::STATUS_SELECTED,
            'payment_method_id' => $method->id,
        ])->save();

        try {
            $amountMinor = $this->toMinor((string) $session->amount, $session->currency);
            /** @var StripeGateway $gateway */
            $gateway = app(StripeGateway::class);
            $started = $gateway->startWalletPayment(
                amountMinor: (string) $amountMinor,
                currency:    $session->currency,
                context: [
                    'local_uuid'     => $session->uuid,
                    'payment_method' => $method,
                ],
            );

            if ($started['client_secret'] === '' || $started['publishable_key'] === '') {
                throw new RuntimeException('Stripe did not return a usable PaymentIntent.');
            }

            $session->forceFill([
                'gateway_session_id' => $started['payment_intent_id'] ?: null,
            ])->save();

            return view('pay.pos_wallet', [
                'session'         => $session,
                'method'          => $method,
                'clientSecret'    => $started['client_secret'],
                'publishableKey'  => $started['publishable_key'],
                'walletBrand'     => $session->wallet_brand,
                'amountMinor'     => $amountMinor,
                'company'         => Company::current() ?? new Company(),
            ]);
        } catch (\Throwable $e) {
            report($e);
            $session->forceFill([
                'status'          => PosPaymentSession::STATUS_FAILED,
                'failure_message' => $e->getMessage(),
            ])->save();
            return response()->view('pay.invalid', ['error' => $e->getMessage()], 400);
        }
    }

    public function return(Request $request, string $uuid): View
    {
        $session  = PosPaymentSession::query()->where('uuid', $uuid)->firstOrFail();
        $provider = (string) $request->query('provider', '');

        // Re-fetch from the gateway to confirm — never trust the
        // bare redirect. Razorpay also signs callback query params,
        // but the polling round-trip handles edge cases (refund-then-
        // return, network dropout, etc.) the static signature won't.
        if (\in_array($provider, self::WIRED_PROVIDERS, true) && $session->gateway_session_id && $session->paymentMethod) {
            try {
                $gateway = $this->gatewayFor($session->paymentMethod);
                $status  = $gateway->pollStatus($session->gateway_session_id, $session->paymentMethod);
                if (($status['status'] ?? '') === 'paid' && !$session->isPaid()) {
                    $session->forceFill([
                        'status'             => PosPaymentSession::STATUS_PAID,
                        'gateway_payment_id' => $status['payment_id'] ?? null,
                        'paid_at'            => now(),
                    ])->save();
                }
            } catch (RuntimeException $e) {
                // Cashier polling + webhook will still figure it out;
                // we just don't paint "paid" prematurely.
            }
        }

        return view('pay.return', [
            'session' => $session->fresh(),
        ]);
    }

    /* ── Helpers ──────────────────────────────────────────────── */

    /** Per-provider dispatch — one line per new gateway. */
    private function gatewayFor(PaymentMethod $method): PaymentGateway
    {
        return match ($method->provider) {
            'stripe'       => app(StripeGateway::class),
            'razorpay'     => app(RazorpayGateway::class),
            'paystack'     => app(PaystackGateway::class),
            'flutterwave'  => app(FlutterwaveGateway::class),
            'mercado_pago' => app(MercadoPagoGateway::class),
            default        => throw new RuntimeException(
                "No gateway wired for provider '{$method->provider}'."
            ),
        };
    }

    /** Per-provider return URL template — Stripe interpolates the
     *  session id, Razorpay/Paystack just echo our query params. */
    private function returnUrlFor(string $provider, string $sessionUuid): string
    {
        $base = route('pay.pos.return', $sessionUuid);
        return match ($provider) {
            'stripe'       => $base . '?provider=stripe&session={CHECKOUT_SESSION_ID}',
            'razorpay'     => $base . '?provider=razorpay',
            'paystack'     => $base . '?provider=paystack',
            'flutterwave'  => $base . '?provider=flutterwave',
            'mercado_pago' => $base . '?provider=mercado_pago',
            default        => $base,
        };
    }

    /** Per-provider credential completeness check. */
    private function credsLookComplete(PaymentMethod $m): bool
    {
        $creds = $m->provider_credentials ?? [];
        return match ($m->provider) {
            'stripe'      => !empty($creds['secret_key'] ?? null),
            'razorpay'    => !empty($creds['key_id']     ?? null) && !empty($creds['key_secret'] ?? null),
            'paystack'    => !empty($creds['secret_key'] ?? null),
            // Flutterwave + Mercado Pago only need their API credential to
            // appear at checkout — same bar as Stripe/Paystack. Payment
            // confirmation runs through the return-redirect poll
            // (`return()` → `pollStatus()`), which re-fetches status from
            // the gateway using that credential, so a webhook is NOT
            // required to take a payment. `webhook_secret` is strongly
            // recommended (faster, survives a dropped return redirect) and
            // still gates webhook verification — an unsigned webhook is
            // rejected when no secret is set — but it must not block the
            // tile, or a fully-credentialed gateway silently never shows.
            'flutterwave'  => !empty($creds['secret_key']   ?? null),
            'mercado_pago' => !empty($creds['access_token'] ?? null),
            default        => false,
        };
    }

    /**
     * Will this provider/mode plausibly accept a payment in the
     * session's currency? Used to hide tiles that would otherwise
     * dead-end at the gateway:
     *
     *   - Stripe: any currency (multi-currency Checkout is standard).
     *   - Razorpay: any currency — Razorpay supports INR plus 100+
     *     international currencies (USD included) in BOTH test and live
     *     mode. Test mode accepts USD via Payment Links with test cards;
     *     live mode needs International Payments activated on the
     *     merchant's account, but that's their call, not ours to police.
     */
    private function methodSupportsCurrency(PaymentMethod $m, string $sessionCurrency): bool
    {
        $sessionCurrency = \strtoupper($sessionCurrency);
        $creds           = $m->provider_credentials ?? [];

        return match ($m->provider) {
            'stripe'   => true,
            // Razorpay always takes INR. Other currencies need
            // International Payments activated on the merchant's Razorpay
            // account (it rejects international cards otherwise) — we can't
            // detect that, so it's a merchant-confirmed settings toggle.
            'razorpay' => $sessionCurrency === 'INR' || ! empty($creds['international_enabled']),
            // Paystack settles in the currencies its account supports —
            // the African set plus USD — in both test and live mode.
            // Whitelist that set (rather than NGN-only in test) so a USD
            // or GHS/ZAR/KES session shows the tile; anything outside it
            // would dead-end at "Currency not supported by merchant".
            'paystack' => \in_array($sessionCurrency, ['NGN', 'GHS', 'ZAR', 'KES', 'USD'], true),
            // Flutterwave's strength is wide pan-African + USD/EUR/GBP
            // coverage. Whitelist the well-supported set; uncommon
            // currencies will be filtered out so the customer never
            // taps a tile that ends in a Flutterwave error.
            'flutterwave' => \in_array($sessionCurrency, [
                'NGN', 'KES', 'GHS', 'ZAR', 'UGX', 'TZS', 'RWF',
                'MWK', 'ZMW', 'XAF', 'XOF', 'USD', 'EUR', 'GBP',
            ], true),
            // Mercado Pago supports payments in the local currencies
            // of the Latin American markets it operates in plus USD
            // for international. Each merchant account is tied to one
            // collector country, so the wrong currency hard-fails.
            'mercado_pago' => \in_array($sessionCurrency, [
                'ARS', 'BRL', 'MXN', 'CLP', 'COP', 'PEN',
                'UYU', 'VES', 'BOB', 'USD',
            ], true),
            default    => false,
        };
    }

    /** Decimal-string → minor units. Mirrors GatewayController's helper. */
    private function toMinor(string $amount, string $currency): int
    {
        $zero  = ['JPY', 'KRW', 'VND', 'CLP', 'IDR'];
        $three = ['BHD', 'JOD', 'KWD', 'OMR', 'TND'];
        $upper = \strtoupper($currency);
        $dec   = \in_array($upper, $zero, true) ? 0 : (\in_array($upper, $three, true) ? 3 : 2);
        return (int) \bcmul($amount, \bcpow('10', (string) $dec, 0), 0);
    }
}
