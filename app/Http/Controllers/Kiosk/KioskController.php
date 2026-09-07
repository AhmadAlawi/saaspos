<?php

namespace App\Http\Controllers\Kiosk;

use App\Actions\Sales\PlaceKioskOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kiosk\PlaceKioskOrderRequest;
use App\Models\Company;
use App\Models\Sale;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Self-Ordering Kiosk — Slice 1 (shell + browse + cart).
 *
 * Renders the customer-operated ordering app for a station typed `kiosk`.
 * It is a re-skin of the cashier driven by the shopper instead of staff:
 * it reads the SAME offline catalog the cashier syncs into IndexedDB
 * (via /cashier/sync), so this controller ships no catalog endpoint of
 * its own — it just bootstraps branding, kiosk config, and labels. The
 * page itself (resources/js/kiosk/kiosk-app.js) owns the attract → browse
 * → cart state machine. Checkout / place-order land in Slices 2–3.
 *
 * See docs/features/kiosk-self-ordering.md.
 */
class KioskController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        // A kiosk is opened from an authenticated staff session and then
        // handed to the customer; it shows customer-safe data only, so the
        // gate is the same "can ring up a sale" check the cashier uses.
        $this->authorize('create', Sale::class);

        $terminal = current_terminal();

        // The station must be bound to a kiosk-enabled terminal. Otherwise
        // send the operator back to the till with guidance — a normal
        // register should never expose the customer kiosk.
        if (! $terminal || ! $terminal->kioskEnabled()) {
            return redirect()->route('cashier.index')
                ->with('error', __('kiosk.errors.not_configured'));
        }

        $store   = current_store();
        $company = Company::current();

        // Match the till's saved theme (cashier default → company default),
        // mirroring the CFD so the kiosk opens in the same look.
        $cashierTheme = $company?->cashier()['theme_default'] ?? 'auto';
        $themeDefault = $cashierTheme !== 'auto'
            ? $cashierTheme
            : ($company?->theme_default ?: 'light');

        $currency = app_currency();
        $mode     = $terminal->kioskMode();
        $cfg      = $terminal->kiosk_config ?? [];

        $storeName = $store?->name ?? (string) config('app.name');

        $bootstrap = [
            'mode'         => $mode,   // checkout | order
            'syncUrl'      => route('cashier.sync'),
            'placeUrl'     => route('kiosk.place'),
            'checkoutStartUrl'    => route('kiosk.checkout.start'),
            'checkoutCompleteUrl' => route('kiosk.checkout.complete'),
            'sessionStatusUrl'    => route('cashier.pos-sessions.status', ['uuid' => '__UUID__']),
            'sessionCancelUrl'    => route('cashier.pos-sessions.cancel', ['uuid' => '__UUID__']),
            'exitUrl'      => route('kiosk.exit'),
            'cashierUrl'   => route('cashier.index'),
            'idleTimeout'  => (int) ($cfg['idle_timeout_seconds'] ?? 60),
            'currencyCode' => $currency['code'] ?? 'USD',
            'store'        => ['name' => $storeName],
            // Slice 5 config polish.
            'customerMode' => $terminal->kioskCustomerMode(),   // off | optional | required
            'allowNote'    => $terminal->kioskAllowNote(),
            'soundEnabled' => $terminal->kioskSoundEnabled(),
            'thankyouSeconds' => $terminal->kioskThankyouSeconds(),
            // Static-QR (UPI) pay-at-the-machine option. Rendered client-side
            // as a `upi://pay?…` deep link, exactly like the cashier's. Null
            // when the merchant hasn't picked a UPI method for this station.
            'upi'          => $this->upiPayload($terminal, $storeName),
            'categoryIds'  => array_map('strval', $terminal->kioskCategoryIds()),  // whitelist ([] = all)
            'pinRequired'  => $terminal->kioskPinRequired(),
            'attract'      => [
                'media'   => $terminal->kioskAttractMedia(),
                'seconds' => (int) ($cfg['attract_seconds'] ?? 8),
            ],
            'welcome'      => ($cfg['welcome_text'] ?? null)
                ?: __('kiosk.idle.welcome', ['store' => $storeName]),
            'thankyou'     => ($cfg['thankyou_text'] ?? null) ?: __('kiosk.thankyou.title'),
            // Pre-translated labels so the customer-facing JS honours locale
            // + RTL without an __() equivalent client-side.
            'labels'       => [
                'tagline'        => __('kiosk.idle.tagline'),
                'start'          => __('kiosk.idle.start'),
                'search'         => __('kiosk.browse.search'),
                'all'            => __('kiosk.browse.all'),
                'add'            => __('kiosk.browse.add'),
                'from'           => __('kiosk.browse.from'),
                'options'        => __('kiosk.browse.options'),
                'choose_option'  => __('kiosk.browse.choose_option'),
                'combo'          => __('kiosk.browse.combo'),
                'whats_included' => __('kiosk.browse.whats_included'),
                'add_combo'      => __('kiosk.browse.add_combo'),
                'empty_search'   => __('kiosk.browse.empty_search'),
                'view_cart'      => __('kiosk.browse.view_cart'),
                'keep_shopping'  => __('kiosk.cart.keep_shopping'),
                'your_order'     => __('kiosk.cart.your_order'),
                'each'           => __('kiosk.cart.each'),
                'empty_cart'     => __('kiosk.cart.empty'),
                'start_shopping' => __('kiosk.cart.start_shopping'),
                'note'           => __('kiosk.cart.note'),
                'note_hint'      => __('kiosk.cart.note_hint'),
                'subtotal'       => __('kiosk.cart.subtotal'),
                'tax'            => __('kiosk.cart.tax'),
                'total'          => __('kiosk.cart.total'),
                'pay_now'        => __('kiosk.cart.pay_now'),
                'pay_counter'    => __('kiosk.cart.pay_counter'),
                'pay_choice'     => __('kiosk.cart.pay_choice'),
                'coming_soon'    => __('kiosk.cart.coming_soon'),
                'placing'        => __('kiosk.cart.placing'),
                'place_failed'   => __('kiosk.errors.place_failed'),
                'stock_limit'    => __('kiosk.errors.stock_limit'),
                'ty_placed'      => __('kiosk.thankyou.order_placed'),
                'ty_pickup'      => __('kiosk.thankyou.pickup_label'),
                'ty_sub'         => __('kiosk.thankyou.sub'),
                'ty_done'        => __('kiosk.thankyou.done'),
                'ty_offline'     => __('kiosk.thankyou.offline'),
                'ty_offline_sub' => __('kiosk.thankyou.offline_sub'),
                // Checkout mode (Slice 3).
                'pay_amount'     => __('kiosk.pay.amount_due'),
                'pay_scan'       => __('kiosk.pay.scan_to_pay'),
                'pay_scan_hint'  => __('kiosk.pay.scan_hint'),
                'pay_waiting'    => __('kiosk.pay.waiting'),
                'pay_cancel'     => __('kiosk.pay.cancel'),
                'pay_failed'     => __('kiosk.pay.failed'),
                'pay_starting'   => __('kiosk.pay.starting'),
                'pay_help'       => __('kiosk.pay.help'),
                'ty_paid'        => __('kiosk.thankyou.paid'),
                'ty_receipt'     => __('kiosk.thankyou.receipt_scan'),
                'ty_receipt_hint' => __('kiosk.thankyou.receipt_hint'),
                'ty_collect'     => __('kiosk.thankyou.collect'),
                'exit_title'     => __('kiosk.exit.title'),
                'exit_body'      => __('kiosk.exit.body'),
                'exit_stay'      => __('kiosk.exit.stay'),
                'exit_leave'     => __('kiosk.exit.leave'),
                'loading'        => __('kiosk.browse.loading'),
                'offline'        => __('kiosk.browse.offline'),
                // Slice 5 — customer prompt + supervisor exit.
                'cust_title'     => __('kiosk.customer.title'),
                'cust_name'      => __('kiosk.customer.name'),
                'cust_phone'     => __('kiosk.customer.phone'),
                'cust_required'  => __('kiosk.customer.required'),
                'exit_email'     => __('kiosk.exit.email'),
                'exit_password'  => __('kiosk.exit.password'),
                'exit_denied'    => __('kiosk.exit.denied'),
                // Static UPI QR (pay at the machine, verified at the counter).
                'upi_pay'        => __('kiosk.upi.pay_with'),
                'upi_scan'       => __('kiosk.upi.scan'),
                'upi_hint'       => __('kiosk.upi.hint'),
                'upi_paid'       => __('kiosk.upi.i_have_paid'),
                'upi_back'       => __('kiosk.upi.back'),
                'ty_upi'         => __('kiosk.thankyou.upi_claimed'),
                'ty_upi_sub'     => __('kiosk.thankyou.upi_claimed_sub'),
            ],
        ];

        return view('kiosk.app', [
            'bootstrap'      => $bootstrap,
            'appName'        => $company?->display_app_name ?: (string) config('app.name'),
            'appLogoUrl'     => $company?->app_logo_url,
            'appLogoDarkUrl' => $company?->app_logo_dark_url,
            'brandColor'     => $company?->brand_color,
            'brandTextColor' => $company?->brand_text_color,
            'faviconUrl'     => $company?->favicon_url,
            'themeDefault'   => $themeDefault,
            'storeName'      => $storeName,
        ]);
    }

    /**
     * The station's static-QR (UPI) option, or null when it isn't configured.
     *
     * We hand the client the VPA + payee name (never credentials) so it can
     * build a `upi://pay?pa=…&pn=…&am=…` deep link with the live cart total,
     * mirroring what the cashier and the counter queue already render. The
     * method must be provider-less and carry a `vpa`, or there is nothing to
     * encode — a gateway method goes through the QR-chooser instead.
     *
     * @return array<string, mixed>|null
     */
    private function upiPayload(\App\Models\Terminal $terminal, string $storeName): ?array
    {
        $id = $terminal->kioskUpiMethodId();
        if (! $id || $terminal->kioskMode() === 'order') {
            return null;
        }

        $method = \App\Models\PaymentMethod::query()
            ->active()
            ->whereKey($id)
            ->first(['id', 'name', 'provider', 'provider_credentials']);

        $vpa = $method?->upiVpa();
        if (! $method || $method->isGatewayBacked() || ! $vpa) {
            return null;
        }

        return [
            'method_id'  => (int) $method->id,
            'name'       => (string) $method->name,
            'vpa'        => $vpa,
            'payee_name' => (string) (($method->provider_credentials ?? [])['payee_name'] ?? $storeName),
        ];
    }

    /**
     * Place a self-ordering (`order`-mode) submission → a `placed` Sale that
     * lands in the staff orders queue. Cashless payment (`checkout` mode) is
     * a separate path (Slice 3). See docs/features/kiosk-self-ordering.md §5.
     */
    public function place(PlaceKioskOrderRequest $request, PlaceKioskOrder $place, \App\Actions\Customers\ResolveKioskCustomer $resolveCustomer): JsonResponse
    {
        $this->authorize('create', Sale::class);

        $terminal = current_terminal();
        if (! $terminal || ! $terminal->kioskEnabled()) {
            return response()->json(['message' => __('kiosk.errors.not_configured')], 422);
        }

        $data = $request->validated();

        // Customer details (Slice 5). Required-mode blocks placement without
        // a name or phone; otherwise attach when provided.
        $customerId = $data['customer_id'] ?? null;
        if ($customerId === null) {
            if ($terminal->kioskCustomerMode() === 'required'
                && trim((string) ($data['customer_name'] ?? '')) === ''
                && trim((string) ($data['customer_phone'] ?? '')) === '') {
                return response()->json(['message' => __('kiosk.customer.required')], 422);
            }
            $customerId = $resolveCustomer($data['customer_name'] ?? null, $data['customer_phone'] ?? null, $request->user());
        }

        // A UPI claim is only honoured when it names THIS terminal's configured
        // static-QR method. Anything else — a tampered client naming "Cash", or
        // a stale id after the merchant reconfigured the station — is dropped;
        // the order still places, just without a claim for staff to chase.
        $claimId = (int) ($data['payment_claim_method_id'] ?? 0);
        $claim   = ($claimId > 0 && $claimId === $terminal->kioskUpiMethodId()) ? $claimId : null;

        // Someone else took the last unit between the kiosk's cached catalog
        // and this tap — tell the shopper plainly instead of 500-ing.
        try {
            $sale = $place(
                header: [
                    'store_id'    => current_store_id() ?: default_store_id(),
                    'terminal_id' => $terminal->id,
                    'customer_id' => $customerId,
                    'local_uuid'  => $data['local_uuid'] ?? null,
                    'kiosk_note'  => $data['note'] ?? null,
                    'payment_claim_method_id' => $claim,
                    // Pass it explicitly: PlaceKioskOrder otherwise falls back to
                    // the legacy per-store column, which can disagree with the
                    // system-wide currency the kiosk priced the cart in.
                    'currency_code' => (string) app_currency()['code'],
                    'pickup_prefix' => $terminal->kioskPickupPrefix(),
                ],
                lines: $data['items'],
                staff: $request->user(),
            );
        } catch (\App\Exceptions\InsufficientStock $e) {
            return response()->json([
                'message' => __('sales.errors.insufficient_stock', [
                    'name'      => $e->productName,
                    'available' => $e->available,
                    'requested' => $e->requested,
                ]),
            ], 422);
        }

        // Sync-log parity with offline sales — an order placed offline and
        // drained later shows up in the admin sync log against its
        // local_uuid. updateOrCreate so a retry collapses onto one row.
        // Best-effort: the order is already committed, so a sync-log failure
        // must never surface as "your order wasn't placed".
        if (! empty($data['local_uuid'])) {
            try {
                \App\Models\SyncLog::query()->updateOrCreate(
                    ['local_uuid' => (string) $data['local_uuid']],
                    [
                        'user_id'          => $request->user()?->id,
                        'terminal_id'      => $terminal->id,
                        'entity'           => 'kiosk_order',
                        'payload'          => $request->all(),
                        'result'           => \App\Models\SyncLog::RESULT_SUCCESS,
                        'synced_entity_id' => (int) $sale->id,
                        'synced_at'        => now(),
                    ],
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Kiosk order sync-log write failed.', [
                    'sale_id' => $sale->id, 'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'ok'          => true,
            'pickup_code' => $sale->pickup_code,
            'number'      => $sale->number,
            'grand_total' => (string) $sale->grand_total,
        ]);
    }

    /**
     * Exit kiosk mode back to the staff cashier.
     *
     * When the terminal has `supervisor_pin_required`, a manager must
     * re-authenticate (email + password) and hold `terminals.configure` for
     * the store before we let the customer-facing lockdown be lifted —
     * reusing the discount-approval re-auth pattern
     * ({@see \App\Http\Controllers\Cashier\DiscountApprovalController}).
     * See docs/features/kiosk-self-ordering.md §6.
     */
    public function exit(Request $request): JsonResponse
    {
        $this->authorize('create', Sale::class);

        $terminal = current_terminal();

        if ($terminal && $terminal->kioskPinRequired()) {
            $data = $request->validate([
                'email'    => ['required', 'string', 'max:191'],
                'password' => ['required', 'string'],
            ]);

            $storeId    = current_store_id() ?: default_store_id();
            $supervisor = \App\Models\User::query()->where('email', $data['email'])->first();

            if (! $supervisor
                || ! \Illuminate\Support\Facades\Hash::check($data['password'], (string) $supervisor->password)
                || ! $supervisor->is_active
                || ! $supervisor->hasPermission('terminals.configure', (int) $storeId)) {
                return response()->json(['ok' => false, 'message' => __('kiosk.exit.denied')], 422);
            }
        }

        return response()->json([
            'ok'       => true,
            'redirect' => route('cashier.index'),
        ]);
    }
}
