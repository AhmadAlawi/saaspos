<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Sale;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Customer-Facing Display (CFD) — Slice 1.
 *
 * Renders the second screen the shopper looks at while the cashier rings
 * up. It is display-only: it holds no cart of its own. The live cart is
 * pushed from the cashier window over a same-machine BroadcastChannel
 * (see resources/js/cashier/cfd-broadcast.js); this page just paints the
 * last snapshot it received. Idle attract copy + store branding come from
 * this server bootstrap so the idle screen has something to show before any
 * sale starts. See docs/features/customer-display.md §5 (Tier 1).
 */
class CustomerDisplayController extends Controller
{
    public function show(): View
    {
        // Only staff who can ring up a sale may open the customer display —
        // it's launched from an authenticated cashier session and shows
        // customer-safe data only (never cost/margin).
        $this->authorize('create', Sale::class);

        $store    = current_store();
        $company  = Company::current();
        $terminal = current_terminal();
        $cfd      = $terminal?->cfd_config ?? [];

        // Same channel string the cashier computes — scoped per terminal so
        // two POS on one machine can't cross-talk; 'default' when unbound.
        $channel = 'pos-cfd:'.(current_terminal_id() ?: 'default');

        // Cashier-specific theme default (falls back to the company default),
        // mirroring how <x-cashier-layout> resolves it — so the display opens
        // in the same look as the till it's paired with.
        $cashierTheme = $company?->cashier()['theme_default'] ?? 'auto';
        $themeDefault = $cashierTheme !== 'auto'
            ? $cashierTheme
            : ($company?->theme_default ?: 'light');

        $currency = app_currency();

        // Attract-media slideshow (Slice 3) — resolve the stored image paths
        // to URLs for the idle screen. Empty → the idle screen just shows the
        // logo + welcome.
        $attractMedia = collect($cfd['attract_media'] ?? [])
            ->filter(fn ($p) => is_string($p) && $p !== '')
            ->map(fn ($p) => \Illuminate\Support\Facades\Storage::disk('public')->url($p))
            ->values()
            ->all();

        $bootstrap = [
            'channel'  => $channel,
            // Transport (Slice 4): 'same_machine' → BroadcastChannel;
            // 'separate_device' → poll the per-terminal state endpoint.
            'transport' => $cfd['transport'] ?? 'same_machine',
            'poll_url'  => route('cashier.display.state', ['terminal' => current_terminal_id() ?: 0]),
            'currency' => [
                'symbol'       => $currency['symbol'] ?? '',
                'symbol_first' => (bool) ($currency['symbol_first'] ?? true),
            ],
            'store'    => [
                'name' => $store?->name ?? (string) config('app.name'),
            ],
            'attract'  => [
                'media'   => $attractMedia,
                'seconds' => (int) ($cfd['attract_seconds'] ?? 8),
            ],
            // Idle-screen copy: terminal override → translated default.
            'welcome'  => ($cfd['welcome_text'] ?? null)
                ?: __('customer-display.idle.welcome', ['store' => $store?->name ?? config('app.name')]),
            'tagline'  => __('customer-display.idle.tagline'),
            'thankyou' => ($cfd['thankyou_text'] ?? null) ?: __('customer-display.thankyou.title'),
            // Mirror the cashier's UPI QR onto the payment screen when this
            // terminal opts in — the shopper then scans it from their own
            // display instead of leaning over the till.
            'show_upi_qr' => (bool) ($cfd['show_upi_qr'] ?? false),
            // Pre-translated labels so the display honours locale + RTL
            // without an __() equivalent in JS.
            'labels'   => [
                'your_order'  => __('customer-display.sale.your_order'),
                'item'        => __('customer-display.sale.item'),
                'items'       => __('customer-display.sale.items'),
                'col_item'    => __('customer-display.sale.col_item'),
                'col_qty'     => __('customer-display.sale.col_qty'),
                'col_amount'  => __('customer-display.sale.col_amount'),
                'subtotal'    => __('customer-display.sale.subtotal'),
                'discount'    => __('customer-display.sale.discount'),
                'tax'         => __('customer-display.sale.tax'),
                'total'       => __('customer-display.sale.total'),
                'added'       => __('customer-display.sale.added'),
                'scanning'    => __('customer-display.sale.scanning'),
                'loyalty'     => __('customer-display.sale.loyalty'),
                'points'      => __('customer-display.sale.points'),
                'each'        => __('customer-display.sale.each'),
                // Payment + thank-you (Slice 2).
                'amount_due'  => __('customer-display.payment.amount_due'),
                'tendered'    => __('customer-display.payment.tendered'),
                'change_due'  => __('customer-display.payment.change_due'),
                'scan_to_pay' => __('customer-display.payment.scan_to_pay'),
                'scan_hint'   => __('customer-display.payment.scan_hint'),
                'scan_upi'      => __('customer-display.payment.scan_upi'),
                'scan_upi_hint' => __('customer-display.payment.scan_upi_hint'),
                // Apple Pay / Google Pay — same QR/pay_url as the generic
                // flow, just a wallet-specific label (see wallet_brand in
                // the payment snapshot, resources/js/cashier/cashier-page.js).
                'scan_apple_pay'  => __('customer-display.payment.scan_apple_pay'),
                'scan_google_pay' => __('customer-display.payment.scan_google_pay'),
                'scan_wallet_hint' => __('customer-display.payment.scan_wallet_hint'),
                'change'      => __('customer-display.thankyou.change'),
                'receipt_scan' => __('customer-display.thankyou.receipt_scan'),
                'receipt_hint' => __('customer-display.thankyou.receipt_hint'),
            ],
        ];

        return view('cashier.display', [
            'bootstrap'      => $bootstrap,
            'appName'        => $company?->display_app_name ?: (string) config('app.name'),
            'appLogoUrl'     => $company?->app_logo_url,
            'appLogoDarkUrl' => $company?->app_logo_dark_url,
            'brandColor'     => $company?->brand_color,
            'brandTextColor' => $company?->brand_text_color,
            'faviconUrl'     => $company?->favicon_url,
            'themeDefault'   => $themeDefault,
            'storeName'      => $store?->name ?? (string) config('app.name'),
        ]);
    }

    /**
     * Tier 2 relay — the cashier posts its latest snapshot; we cache it per
     * terminal. A separate tablet reads it back via {@see state()}. The
     * snapshot is the cashier's own customer-safe data (names/prices only),
     * so we just bound its shape and stash the whole thing. Debounced on the
     * client, so this is hit at most ~1–2×/sec per till.
     */
    public function push(Request $request): JsonResponse
    {
        $this->authorize('create', Sale::class);

        $data = $request->validate([
            'snapshot' => ['required', 'array'],
        ]);

        $terminalId = current_terminal_id() ?: 0;
        Cache::put($this->stateKey($terminalId), $data['snapshot'], now()->addMinutes(5));

        return response()->json(['ok' => true]);
    }

    /**
     * Return the last snapshot pushed for a terminal (or an empty object).
     * Polled ~every 1.5s by a separate-device display. Auth-gated the same
     * way the display page is — the tablet runs in a signed-in session.
     */
    public function state(int $terminal): JsonResponse
    {
        $this->authorize('create', Sale::class);

        return response()->json(Cache::get($this->stateKey($terminal)) ?: (object) []);
    }

    private function stateKey(int $terminalId): string
    {
        return "cfd:state:{$terminalId}";
    }
}
