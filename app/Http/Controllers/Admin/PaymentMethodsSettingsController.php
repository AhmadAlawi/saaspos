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
 * Manual payment methods settings — Cash, Card (record-only),
 * UPI, Bank Transfer, Cheque, etc. The admin can toggle each
 * on/off from here so the cashier only sees the methods they
 * actually use.
 *
 * Gateway-backed methods (Stripe, Razorpay, Paystack, Flutterwave,
 * Mercado Pago) are managed via /admin/settings/payment-gateways
 * and deliberately excluded from this list — they live through the
 * "Charge via QR" flow, not the manual tile list.
 */
class PaymentMethodsSettingsController extends Controller
{
    use RespondsJsonOrRedirect;

    public function edit(): View
    {
        $this->authorize('settings.view');

        // Only manual methods — gateway-backed rows (Stripe etc.)
        // get their own settings page. Same filter shape the cashier
        // uses to hide gateway rows from the manual tile list.
        // `store_credit` is excluded because v1 ships without the
        // store-credit feature — keep the row in the DB for any
        // legacy references, just don't surface it in the admin UI.
        $methods = PaymentMethod::query()
            ->where(function ($q) {
                $q->whereNull('provider')
                  ->orWhere('provider', '')
                  ->orWhere('provider', 'none');
            })
            ->where('code', '!=', 'store_credit')
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'code', 'name', 'type', 'icon', 'color', 'is_active', 'sort_order', 'requires_reference', 'provider_credentials', 'opens_cash_drawer']);

        return view('admin.settings.payment-methods', [
            'methods' => $methods,
        ]);
    }

    /**
     * Toggle the `is_active` flag on a single manual method. Returns
     * JSON for the inline toggle (no full-page reload) and a redirect
     * for the no-JS fallback.
     */
    public function toggle(Request $request, PaymentMethod $method): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.view');

        if (pos_is_demo()) {
            return $this->jsonOrError(
                $request,
                __('settings.demo.locked'),
                route('admin.settings.payment-methods.edit'),
            );
        }

        // Defend against a malicious POST flipping a gateway row
        // through this endpoint — gateway rows are managed elsewhere.
        if (!\in_array((string) $method->provider, ['', 'none'], true) && $method->provider !== null) {
            abort(403, 'Gateway-backed methods are managed under Payment Gateways.');
        }

        $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $method->is_active = (bool) $request->boolean('is_active');
        $method->save();

        return $this->jsonOrRedirect(
            $request,
            __('settings.payment_methods.flash.saved', ['name' => $method->name]),
            route('admin.settings.payment-methods.edit'),
        );
    }

    /**
     * Toggle the `opens_cash_drawer` flag — whether completing a sale
     * with this method should kick the physical drawer (see
     * EscPosFormatter::hasCashPayment()). Cash defaults to on; card and
     * others default to off but the merchant can opt any manual method
     * in, e.g. when card slips get filed in the same drawer as cash.
     */
    public function toggleDrawer(Request $request, PaymentMethod $method): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.view');

        if (pos_is_demo()) {
            return $this->jsonOrError(
                $request,
                __('settings.demo.locked'),
                route('admin.settings.payment-methods.edit'),
            );
        }

        if (!\in_array((string) $method->provider, ['', 'none'], true) && $method->provider !== null) {
            abort(403, 'Gateway-backed methods are managed under Payment Gateways.');
        }

        $request->validate([
            'opens_cash_drawer' => ['required', 'boolean'],
        ]);

        $method->opens_cash_drawer = (bool) $request->boolean('opens_cash_drawer');
        $method->save();

        return $this->jsonOrRedirect(
            $request,
            __('settings.payment_methods.flash.saved', ['name' => $method->name]),
            route('admin.settings.payment-methods.edit'),
        );
    }

    /**
     * Toggle `requires_reference` — whether the cashier must type a
     * reference number (auth code, cheque #, UPI UTR) before a payment
     * on this method can be tendered. Cash and Card ship with different
     * defaults, but the merchant may reasonably not care about tracking
     * a card auth code, so this is editable per method.
     */
    public function toggleReference(Request $request, PaymentMethod $method): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.view');

        if (pos_is_demo()) {
            return $this->jsonOrError(
                $request,
                __('settings.demo.locked'),
                route('admin.settings.payment-methods.edit'),
            );
        }

        if (!\in_array((string) $method->provider, ['', 'none'], true) && $method->provider !== null) {
            abort(403, 'Gateway-backed methods are managed under Payment Gateways.');
        }

        $request->validate([
            'requires_reference' => ['required', 'boolean'],
        ]);

        $method->requires_reference = (bool) $request->boolean('requires_reference');
        $method->save();

        return $this->jsonOrRedirect(
            $request,
            __('settings.payment_methods.flash.saved', ['name' => $method->name]),
            route('admin.settings.payment-methods.edit'),
        );
    }

    /**
     * UPI-specific: store the merchant's Virtual Payment Address and
     * the display name shown in the customer's UPI app on scan. The
     * cashier renders these as a `upi://pay?pa=…&pn=…` QR so customers
     * can scan and pay in their UPI app — no API, no webhook, the
     * cashier still confirms the payment manually via the reference
     * field (UPI UTR).
     *
     * Both fields are optional individually so the admin can save the
     * VPA without setting the payee name (defaults to the store name),
     * and clear either by submitting an empty value.
     */
    public function updateUpi(Request $request, PaymentMethod $method): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.view');

        if (pos_is_demo()) {
            return $this->jsonOrError(
                $request,
                __('settings.demo.locked'),
                route('admin.settings.payment-methods.edit'),
            );
        }

        if ($method->code !== 'upi') {
            abort(404, 'UPI settings are only configurable on the UPI method.');
        }

        $data = $request->validate([
            // VPA shape: <handle>@<bank-id>. Reject obviously bogus
            // input early so the cashier QR never has a malformed
            // payment address. Empty is OK — clears the config.
            'vpa'        => ['nullable', 'string', 'max:191', 'regex:/^[a-zA-Z0-9._\-]{2,}@[a-zA-Z0-9.\-]{2,}$/'],
            'payee_name' => ['nullable', 'string', 'max:99'],    // UPI spec caps `pn` at ~99 chars in practice
        ], [
            'vpa.regex' => __('settings.payment_methods.upi.vpa_invalid'),
        ]);

        $creds = $method->provider_credentials ?? [];

        $vpa = trim((string) ($data['vpa'] ?? ''));
        if ($vpa === '') {
            unset($creds['vpa']);
        } else {
            $creds['vpa'] = $vpa;
        }

        $payeeName = trim((string) ($data['payee_name'] ?? ''));
        if ($payeeName === '') {
            unset($creds['payee_name']);
        } else {
            $creds['payee_name'] = $payeeName;
        }

        $method->provider_credentials = $creds;
        $method->save();

        return $this->jsonOrRedirect(
            $request,
            __('settings.payment_methods.upi.flash_saved'),
            route('admin.settings.payment-methods.edit'),
        );
    }
}
