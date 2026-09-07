<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Unified Payment Gateways settings — every provider on a single page.
 *
 * The view renders one card per provider (Stripe today; Razorpay,
 * Paystack, Flutterwave, Mercado Pago slot in as new cards). Each
 * card's form POSTs back here with `provider=<code>` and the
 * controller dispatches to the right per-provider update path.
 *
 * Why one page, not five separate pages: the admin's mental model is
 * "configure my payment gateways", not "configure my Stripe THEN my
 * Razorpay THEN...". A single screen also makes it obvious at a
 * glance which providers are wired and which are dark.
 *
 * Credentials live in the `payment_methods.provider_credentials` JSON
 * column. The find-or-create pattern means a fresh install can save
 * Stripe before a Stripe-coded `payment_methods` row exists.
 */
class PaymentGatewaysSettingsController extends Controller
{
    use RespondsJsonOrRedirect;

    /**
     * Per-provider metadata. Extending to a new provider = one entry
     * here + a card in the view. The MVP-only-Stripe state is the
     * map below containing one entry.
     *
     * @var array<string, array{
     *     label: string,
     *     code: string,
     *     type: string,
     *     fields: array<int, string>,
     * }>
     */
    private const PROVIDERS = [
        'stripe' => [
            'label'  => 'Stripe',
            'code'   => 'stripe',
            'type'   => 'card_gateway',
            'fields' => ['mode', 'publishable_key', 'secret_key', 'webhook_secret'],
        ],
        'razorpay' => [
            'label'  => 'Razorpay',
            'code'   => 'razorpay',
            'type'   => 'card_gateway',
            'fields' => ['mode', 'key_id', 'key_secret', 'webhook_secret'],
        ],
        'paystack' => [
            'label'  => 'Paystack',
            'code'   => 'paystack',
            'type'   => 'card_gateway',
            // Paystack signs webhooks with the secret_key itself — there
            // is no separate webhook secret to store.
            'fields' => ['mode', 'public_key', 'secret_key'],
        ],
        'flutterwave' => [
            'label'  => 'Flutterwave',
            'code'   => 'flutterwave',
            'type'   => 'card_gateway',
            // Flutterwave's webhook_secret is a free-form "secret hash"
            // the merchant sets in their dashboard — NOT HMAC.
            'fields' => ['mode', 'public_key', 'secret_key', 'webhook_secret'],
        ],
        'mercado_pago' => [
            'label'  => 'Mercado Pago',
            'code'   => 'mercado_pago',
            'type'   => 'card_gateway',
            // Mercado Pago calls the secret credential `access_token`
            // (not secret_key). webhook_secret is a separate value
            // generated when configuring the webhook in their dashboard.
            'fields' => ['mode', 'public_key', 'access_token', 'webhook_secret'],
        ],
    ];

    public function edit(Request $request): View
    {
        $this->authorize('settings.view');

        // Pull every wired-or-not provider into a uniform shape the
        // view loops over for the tabs sidebar.
        $rows = [];
        foreach (self::PROVIDERS as $code => $meta) {
            $method = $this->methodRow($code, $meta);
            $rows[$code] = [
                'meta'   => $meta,
                'method' => $method,
                // On the public demo, never ship the real gateway keys to the
                // browser — mask every credential except the non-secret `mode`.
                // Writes are blocked in update() too, so the masked values can
                // never be saved back over the originals.
                'creds'  => $this->displayCreds($method->provider_credentials ?? []),
            ];
        }

        // The full provider roster — wired + upcoming. Drives the
        // tabs sidebar so the admin sees the roadmap at a glance.
        // `wired` flag lets the right pane render either the form
        // or the "coming soon" placeholder.
        $tabs = [
            ['code' => 'stripe',       'label' => 'Stripe',       'wired' => true],
            ['code' => 'razorpay',     'label' => 'Razorpay',     'wired' => true],
            ['code' => 'paystack',     'label' => 'Paystack',     'wired' => true],
            ['code' => 'flutterwave',  'label' => 'Flutterwave',  'wired' => true],
            ['code' => 'mercado_pago', 'label' => 'Mercado Pago', 'wired' => true],
        ];

        // Active tab from `?tab=`, defaulting to the first wired one.
        $active = (string) $request->query('tab', 'stripe');
        if (! collect($tabs)->pluck('code')->contains($active)) {
            $active = 'stripe';
        }

        return view('admin.settings.payment-gateways', [
            'rows'   => $rows,
            'tabs'   => $tabs,
            'active' => $active,
        ]);
    }

    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.view');

        // Read-only on the public demo: visitors must never be able to read,
        // overwrite or wipe the real gateway keys (the form is also locked +
        // masked in the view).
        if (pos_is_demo()) {
            return $this->jsonOrError(
                $request,
                __('settings.demo.locked'),
                route('admin.settings.payment-gateways.edit'),
            );
        }

        $provider = (string) $request->input('provider', '');
        if (!isset(self::PROVIDERS[$provider])) {
            abort(404, 'Unknown payment gateway.');
        }

        return match ($provider) {
            'stripe'       => $this->updateStripe($request),
            'razorpay'     => $this->updateRazorpay($request),
            'paystack'     => $this->updatePaystack($request),
            'flutterwave'  => $this->updateFlutterwave($request),
            'mercado_pago' => $this->updateMercadoPago($request),
            default        => abort(404, 'Provider not implemented.'),
        };
    }

    private function updateStripe(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'is_active'       => ['sometimes', 'boolean'],
            'mode'            => ['required', 'in:test,live'],
            'publishable_key' => ['nullable', 'string', 'max:191'],
            'secret_key'      => ['nullable', 'string', 'max:191'],
            'webhook_secret'  => ['nullable', 'string', 'max:191'],
        ]);

        $method = $this->methodRow('stripe', self::PROVIDERS['stripe']);

        // Friendly default: the row is seeded inactive, so filling
        // in the secret_key for the first time auto-activates it —
        // otherwise admins save keys, miss the Active checkbox, and
        // wonder why the customer chooser shows "no methods".
        $wasConfigured = ! empty($method->provider_credentials['secret_key'] ?? null);
        $nowConfigured = ! empty($data['secret_key'] ?? null);
        $isActive      = (bool) $request->boolean('is_active', true);
        if (! $wasConfigured && $nowConfigured) {
            $isActive = true;
        }

        $method->fill([
            'is_active' => $isActive,
        ]);
        $method->provider_credentials = array_filter([
            'mode'            => $data['mode'],
            'publishable_key' => $data['publishable_key'] ?? null,
            'secret_key'      => $data['secret_key']      ?? null,
            'webhook_secret'  => $data['webhook_secret']  ?? null,
        ], fn ($v) => $v !== null && $v !== '');
        $method->save();

        return $this->jsonOrRedirect(
            $request,
            __('settings.gateways.flash.saved', ['provider' => 'Stripe']),
            route('admin.settings.payment-gateways.edit'),
        );
    }

    private function updateRazorpay(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'is_active'             => ['sometimes', 'boolean'],
            'mode'                  => ['required', 'in:test,live'],
            'key_id'                => ['nullable', 'string', 'max:191'],
            'key_secret'            => ['nullable', 'string', 'max:191'],
            'webhook_secret'        => ['nullable', 'string', 'max:191'],
            'international_enabled'  => ['sometimes', 'boolean'],
        ]);

        $method = $this->methodRow('razorpay', self::PROVIDERS['razorpay']);

        // Same friendly default as updateStripe(): the row is seeded
        // inactive, so filling in `key_secret` for the first time
        // auto-activates it.
        $wasConfigured = ! empty($method->provider_credentials['key_secret'] ?? null);
        $nowConfigured = ! empty($data['key_secret'] ?? null);
        $isActive      = (bool) $request->boolean('is_active', true);
        if (! $wasConfigured && $nowConfigured) {
            $isActive = true;
        }

        $method->fill([
            'is_active' => $isActive,
        ]);
        $method->provider_credentials = array_filter([
            'mode'                 => $data['mode'],
            'key_id'               => $data['key_id']         ?? null,
            'key_secret'           => $data['key_secret']     ?? null,
            'webhook_secret'       => $data['webhook_secret'] ?? null,
            // Merchant confirms International Payments is activated on their
            // Razorpay account — gates non-INR currencies on the pay page.
            'international_enabled' => $request->boolean('international_enabled') ?: null,
        ], fn ($v) => $v !== null && $v !== '');
        $method->save();

        return $this->jsonOrRedirect(
            $request,
            __('settings.gateways.flash.saved', ['provider' => 'Razorpay']),
            route('admin.settings.payment-gateways.edit'),
        );
    }

    private function updatePaystack(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'is_active'  => ['sometimes', 'boolean'],
            'mode'       => ['required', 'in:test,live'],
            'public_key' => ['nullable', 'string', 'max:191'],
            'secret_key' => ['nullable', 'string', 'max:191'],
        ]);

        $method = $this->methodRow('paystack', self::PROVIDERS['paystack']);

        $wasConfigured = ! empty($method->provider_credentials['secret_key'] ?? null);
        $nowConfigured = ! empty($data['secret_key'] ?? null);
        $isActive      = (bool) $request->boolean('is_active', true);
        if (! $wasConfigured && $nowConfigured) {
            $isActive = true;
        }

        $method->fill([
            'is_active' => $isActive,
        ]);
        $method->provider_credentials = array_filter([
            'mode'       => $data['mode'],
            'public_key' => $data['public_key'] ?? null,
            'secret_key' => $data['secret_key'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
        $method->save();

        return $this->jsonOrRedirect(
            $request,
            __('settings.gateways.flash.saved', ['provider' => 'Paystack']),
            route('admin.settings.payment-gateways.edit'),
        );
    }

    private function updateFlutterwave(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'is_active'      => ['sometimes', 'boolean'],
            'mode'           => ['required', 'in:test,live'],
            'public_key'     => ['nullable', 'string', 'max:191'],
            'secret_key'     => ['nullable', 'string', 'max:191'],
            'webhook_secret' => ['nullable', 'string', 'max:191'],
        ]);

        $method = $this->methodRow('flutterwave', self::PROVIDERS['flutterwave']);

        $wasConfigured = ! empty($method->provider_credentials['secret_key'] ?? null);
        $nowConfigured = ! empty($data['secret_key'] ?? null);
        $isActive      = (bool) $request->boolean('is_active', true);
        if (! $wasConfigured && $nowConfigured) {
            $isActive = true;
        }

        $method->fill([
            'is_active' => $isActive,
        ]);
        $method->provider_credentials = array_filter([
            'mode'           => $data['mode'],
            'public_key'     => $data['public_key']     ?? null,
            'secret_key'     => $data['secret_key']     ?? null,
            'webhook_secret' => $data['webhook_secret'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
        $method->save();

        return $this->jsonOrRedirect(
            $request,
            __('settings.gateways.flash.saved', ['provider' => 'Flutterwave']),
            route('admin.settings.payment-gateways.edit'),
        );
    }

    private function updateMercadoPago(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'is_active'      => ['sometimes', 'boolean'],
            'mode'           => ['required', 'in:test,live'],
            'public_key'     => ['nullable', 'string', 'max:191'],
            'access_token'   => ['nullable', 'string', 'max:191'],
            'webhook_secret' => ['nullable', 'string', 'max:191'],
        ]);

        $method = $this->methodRow('mercado_pago', self::PROVIDERS['mercado_pago']);

        // Same auto-activate-on-first-save trap fix as the others —
        // for Mercado Pago the "secret" credential is `access_token`.
        $wasConfigured = ! empty($method->provider_credentials['access_token'] ?? null);
        $nowConfigured = ! empty($data['access_token'] ?? null);
        $isActive      = (bool) $request->boolean('is_active', true);
        if (! $wasConfigured && $nowConfigured) {
            $isActive = true;
        }

        $method->fill([
            'is_active' => $isActive,
        ]);
        $method->provider_credentials = array_filter([
            'mode'           => $data['mode'],
            'public_key'     => $data['public_key']     ?? null,
            'access_token'   => $data['access_token']   ?? null,
            'webhook_secret' => $data['webhook_secret'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
        $method->save();

        return $this->jsonOrRedirect(
            $request,
            __('settings.gateways.flash.saved', ['provider' => 'Mercado Pago']),
            route('admin.settings.payment-gateways.edit'),
        );
    }

    /**
     * Credentials as they should appear in the form. Unchanged on a real
     * install; on the demo every value but `mode` is masked so real keys
     * never reach the browser.
     *
     * @param  array<string,mixed>  $creds
     * @return array<string,mixed>
     */
    private function displayCreds(array $creds): array
    {
        if (! pos_is_demo()) {
            return $creds;
        }

        foreach ($creds as $key => $value) {
            if ($key !== 'mode') {
                $creds[$key] = mask_secret((string) $value);
            }
        }

        return $creds;
    }

    /**
     * Find-or-create a `payment_methods` row for the given provider.
     * Inactive on first creation — admin flips it on after entering keys.
     */
    private function methodRow(string $code, array $meta): PaymentMethod
    {
        $method = PaymentMethod::query()->where('code', $code)->first();
        if ($method) return $method;

        return PaymentMethod::create([
            'code'                => $code,
            'name'                => $meta['label'],
            'type'                => $meta['type'],
            'provider'            => $code,
            'requires_reference'  => false,
            'opens_cash_drawer'   => false,
            'is_active'           => false,
            'sort_order'          => 100,
            'provider_credentials'=> [],
        ]);
    }
}
