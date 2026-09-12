<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/clear-cache', function () {
    Artisan::call('optimize:clear');
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    // Reset PHP OPcache too — otherwise edits to lang files, controllers,
    // etc. won't be picked up until Apache restarts. CLI artisan calls
    // bypass OPcache so they always see fresh, but the web SAPI doesn't.
    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }
    // Visiting /clear-cache directly has no referrer to redirect "back"
    // to, so return a plain confirmation that always works.
    return response("Cache cleared successfully.", 200)
        ->header('Content-Type', 'text/plain');
});

// Creates the public/storage symlink so uploaded files (brand logos,
// product images, etc.) are accessible via /storage/… URLs.
// Visit /storage-link once after deploying to any server where the
// symlink doesn't exist yet (shared hosting, fresh VPS, etc.).
// Diagnostic endpoint — hit /diag/paystack on the server to verify
// outbound HTTPS to Paystack works. Returns timing + HTTP status +
// any error message. Bypasses the whole controller chain so a
// Cloudflare 502 here points squarely at network/firewall, not
// our code. Remove before shipping to customers.
// QR-cancel self-test — proves whether THIS server actually kills a
// cancelled QR session's public link. Creates a throwaway pending
// session, cancels it, then asks CustomerPayController::show() what
// HTTP status the customer would get. PASS = 410 (dead). If you see
// 200 here, the deployed CustomerPayController.php is stale (not
// uploaded, or OPcache is serving the old compiled copy) — re-upload
// it and hit /clear-cache. Remove before shipping to customers.
Route::get('/diag/qr-cancel', function () {
    $store = \App\Models\Store::query()->first();
    if (! $store) {
        return response()->json(['error' => 'No store found.'], 200, [], JSON_PRETTY_PRINT);
    }

    $uuid    = (string) \Illuminate\Support\Str::uuid();
    $session = \App\Models\PosPaymentSession::create([
        'uuid' => $uuid, 'store_id' => $store->id, 'local_uuid' => 'diag-qr-cancel',
        'amount' => '1.0000', 'currency' => 'USD', 'status' => 'pending',
        'expires_at' => now()->addMinutes(15),
    ]);

    // Cancel it exactly the way the cashier's button does.
    $session->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();

    // Ask the live controller what the customer would now see.
    $status = null;
    try {
        $resp   = app(\App\Http\Controllers\Pay\CustomerPayController::class)->show($uuid);
        $status = method_exists($resp, 'getStatusCode') ? $resp->getStatusCode() : 200;
    } catch (\Throwable $e) {
        $status = 'EXCEPTION: ' . $e->getMessage();
    } finally {
        $session->delete();
    }

    return response()->json([
        'cancelled_session_show_status' => $status,
        'expected'                      => 410,
        'result'                        => $status === 410 ? 'PASS — link dies on cancel' : 'FAIL — controller is stale, re-upload + /clear-cache',
    ], 200, [], JSON_PRETTY_PRINT);
});

Route::get('/diag/paystack', function () {
    $url    = 'https://api.paystack.co/transaction/totals';
    $start  = microtime(true);
    $result = [
        'target'   => $url,
        'app_url'  => config('app.url'),
        'php'      => PHP_VERSION,
        'curl'     => function_exists('curl_version') ? (curl_version()['version'] ?? 'present') : 'missing',
        'openssl'  => extension_loaded('openssl'),
    ];
    try {
        $res = \Illuminate\Support\Facades\Http::timeout(8)
            ->connectTimeout(5)
            ->get($url);
        $result['elapsed_ms']   = (int) ((microtime(true) - $start) * 1000);
        $result['http_status']  = $res->status();
        $result['snippet']      = substr((string) $res->body(), 0, 300);
        $result['reachable']    = true;
    } catch (\Throwable $e) {
        $result['elapsed_ms']   = (int) ((microtime(true) - $start) * 1000);
        $result['reachable']    = false;
        $result['error_class']  = get_class($e);
        $result['error']        = $e->getMessage();
    }
    return response()->json($result, 200, [], JSON_PRETTY_PRINT);
});

// Heavier diagnostic — exercises the EXACT call the broken Paystack
// flow makes: same endpoint, same Bearer auth from stored creds,
// same body shape. Hit this to confirm whether the failure is at
// the initialize POST itself (Paystack rejects payload / credentials
// / currency) or somewhere else in the controller stack.
Route::get('/diag/paystack-init', function () {
    $start  = microtime(true);
    $method = \App\Models\PaymentMethod::query()
        ->where('provider', 'paystack')
        ->where('is_active', true)
        ->first();
    if (!$method) {
        return response()->json(['error' => 'Paystack payment method not configured.'], 200, [], JSON_PRETTY_PRINT);
    }
    $creds      = $method->provider_credentials ?? [];
    $secretKey  = (string) ($creds['secret_key'] ?? '');
    $mode       = (string) ($creds['mode'] ?? 'unknown');
    $maskedKey  = $secretKey !== ''
        ? substr($secretKey, 0, 12) . '…' . substr($secretKey, -4)
        : '(empty)';

    if ($secretKey === '') {
        return response()->json([
            'error' => 'Paystack secret_key is empty in provider_credentials.',
            'mode'  => $mode,
        ], 200, [], JSON_PRETTY_PRINT);
    }

    // Use whatever the request asks for, defaulting to a realistic
    // walk-in payload. Bump currency via ?currency=NGN for testing.
    $currency = strtoupper((string) request('currency', 'NGN'));
    $amount   = (int) request('amount', 1000);    // minor units
    $payload  = [
        'amount'    => $amount,
        'currency'  => $currency,
        'email'     => 'diag@example.com',
        'reference' => 'diag-' . \Illuminate\Support\Str::uuid(),
    ];

    $result = [
        'app_url'         => config('app.url'),
        'paystack_mode'   => $mode,
        'paystack_key'    => $maskedKey,
        'request_payload' => $payload,
    ];

    try {
        $res = \Illuminate\Support\Facades\Http::withToken($secretKey)
            ->timeout(8)
            ->connectTimeout(5)
            ->acceptJson()
            ->asJson()
            ->post('https://api.paystack.co/transaction/initialize', $payload);

        $result['elapsed_ms']  = (int) ((microtime(true) - $start) * 1000);
        $result['http_status'] = $res->status();
        $result['response']    = $res->json() ?? substr((string) $res->body(), 0, 600);
    } catch (\Throwable $e) {
        $result['elapsed_ms']  = (int) ((microtime(true) - $start) * 1000);
        $result['error_class'] = get_class($e);
        $result['error']       = $e->getMessage();
    }

    return response()->json($result, 200, [], JSON_PRETTY_PRINT);
});

// The installer and /migrate both link storage automatically. This stays as a
// manual repair hatch for installs that predate that, or hosts where the link
// was lost to a file-manager upload.
Route::get('/storage-link', function (\App\Actions\Installer\LinkPublicStorage $link) {
    $result = $link();

    if ($result['ok']) {
        return response('Storage linked successfully. Uploaded files are now accessible.');
    }

    return response('Storage link failed: '.($result['message'] ?? $result['status']), 500);
});

// Root: if not yet installed, ensure.installed middleware redirects to /install.
// Once installed, an authenticated user goes to the dashboard and everyone
// else to login. (Always redirecting to /login looped: /login's `guest`
// middleware bounces a signed-in user straight back to /.)
//
// Used to show LandingController's bundled marketing page here when
// POS_DEMO_MODE=true — dropped (2026-09-12): every instance (demo
// included) is now reached via Tillora's own subscription marketing site
// first, so a tenant's own root showing separate, contradictory marketing
// copy ("own outright, never a monthly fee" — a one-time-purchase pitch,
// not the subscription model this instance actually runs under) served no
// purpose and was actively misleading. LandingController itself is left
// in place, unrouted, in case a self-hosted fork ever wants it back.
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('admin.dashboard')
        : redirect()->route('login');
})->middleware('ensure.installed')->name('landing');

// User documentation — a static, self-contained HTML/Tailwind manual living
// in public/documentation/. Sub-pages (e.g. /documentation/cashier.html) and
// assets are served directly as static files by the web server; this route
// only needs to map the clean /documentation URL onto the index file. Public
// and always available, even before install, so customers can read the guide.
Route::get('/documentation', function () {
    return response()->file(public_path('documentation/index.html'), [
        'Cache-Control' => 'no-cache, must-revalidate',
    ]);
})->name('documentation');

// PWA web app manifest. Rendered from the company row (name, brand colour,
// uploaded app logo) so an installed desktop/mobile app carries the store's own
// branding rather than the generic placeholder that the old static
// public/manifest.json shipped. Public + auth-free: the browser fetches the
// manifest without credentials, and the install prompt can fire on the login
// page. Safe before install — the controller rescues a missing company row.
Route::get('/manifest.json', \App\Http\Controllers\PwaManifestController::class)->name('pwa.manifest');

// Branding feed for the static docs — lets the guide show the customer's own
// uploaded app logo + name in its header/footer. The DB is only touched once
// the app is installed (guarded by the install lock) so this stays instant and
// safe before install; the docs fall back to a bundled placeholder otherwise.
Route::get('/documentation-brand', function () {
    $name = config('app.name');
    if (! $name || strtolower($name) === 'laravel') {
        $name = 'POS';
    }
    $logo = $logoDark = null;
    try {
        if (\App\Support\InstallState::isLocked()) {
            $company = \App\Models\Company::query()->first();
            if ($company) {
                $logo     = $company->app_logo_url ?: $company->logo_url;
                $logoDark = $company->app_logo_dark_url ?: $logo;
            }
        }
    } catch (\Throwable) {
        // DB unavailable — fall through to the placeholder.
    }

    return response()->json([
        'name'     => $name,
        'logo'     => $logo,
        'logoDark' => $logoDark,
    ])->header('Cache-Control', 'no-store');
})->name('documentation.brand');

// Public legal pages — payment gateways (Razorpay/Paystack/etc.) want
// these URLs during onboarding, and customers should be able to read
// them without logging in. 404 when the merchant hasn't authored them.
Route::middleware('ensure.installed')->group(function () {
    Route::get('/privacy-policy', [\App\Http\Controllers\LegalPageController::class, 'privacy'])->name('legal.privacy');
    Route::get('/terms',          [\App\Http\Controllers\LegalPageController::class, 'terms'])->name('legal.terms');
});

// Auth ─────────────────────────────────────────────────────────────
Route::middleware(['ensure.installed', 'guest'])->group(function () {
    Route::get('/login',  [\App\Http\Controllers\Auth\LoginController::class, 'show'])
        ->name('login');
    Route::post('/login', [\App\Http\Controllers\Auth\LoginController::class, 'attempt'])
        ->name('login.attempt');
    Route::post('/login/pin', [\App\Http\Controllers\Auth\LoginController::class, 'attemptPin'])
        ->name('login.pin');

    // Password reset — Laravel's broker handles tokens + expiry; the
    // outgoing mail uses whatever SMTP the merchant configured in
    // Settings → Email (applied by ApplyCompanySettings middleware).
    Route::get('/password/forgot',  [\App\Http\Controllers\Auth\PasswordResetController::class, 'showLinkRequest'])
        ->name('password.request');
    Route::post('/password/forgot', [\App\Http\Controllers\Auth\PasswordResetController::class, 'sendLink'])
        ->middleware('throttle:5,1')   // 5 send attempts per minute per IP — anti-spam
        ->name('password.email');
    Route::get('/password/reset/{token}', [\App\Http\Controllers\Auth\PasswordResetController::class, 'showReset'])
        ->name('password.reset');
    Route::post('/password/reset',         [\App\Http\Controllers\Auth\PasswordResetController::class, 'reset'])
        ->name('password.update');
});

Route::post('/logout', [\App\Http\Controllers\Auth\LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Admin shell (auth-gated). Dashboard view is a stub; widgets, KPIs,
// and charts will be built when the underlying features come online.
Route::middleware(['ensure.installed', 'auth', 'store.selected', 'set.locale'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/',                      [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/chart-range',   [\App\Http\Controllers\Admin\DashboardController::class, 'chartRange'])->name('dashboard.chart-range');
    Route::get('dashboard/catalog-range', [\App\Http\Controllers\Admin\DashboardController::class, 'catalogRange'])->name('dashboard.catalog-range');
    Route::post('dashboard/dismiss-setup', [\App\Http\Controllers\Admin\DashboardController::class, 'dismissSetup'])->name('dashboard.dismiss-setup');

    // End-of-day quick summary — topbar popover (JSON), see DaySummaryController.
    Route::get('day-summary', [\App\Http\Controllers\Admin\DaySummaryController::class, 'index'])->name('day-summary');

    // Profile & preferences — the signed-in user's own account (topbar menu).
    // Self-service: no permission gate.
    Route::get('profile',           [\App\Http\Controllers\Admin\ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile',         [\App\Http\Controllers\Admin\ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password',  [\App\Http\Controllers\Admin\ProfileController::class, 'updatePassword'])->name('profile.password');

    // Notifications — the topbar bell dropdown (own notifications only).
    Route::get('notifications',              [\App\Http\Controllers\Admin\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all',    [\App\Http\Controllers\Admin\NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('notifications/{id}/read',   [\App\Http\Controllers\Admin\NotificationController::class, 'markRead'])->name('notifications.read');

    // Categories — list with off-canvas edit/add drawer + drag-to-reorder.
    Route::get('categories',              [\App\Http\Controllers\Admin\CategoryController::class, 'index'])->name('categories.index');
    Route::get('categories/export',       [\App\Http\Controllers\Admin\CategoryController::class, 'export'])->name('categories.export');
    // CSV / XLSX import wizard — literal `import` sub-paths declared BEFORE
    // the `categories/{category}` wildcards so they aren't captured by it.
    Route::get ('categories/import',          [\App\Http\Controllers\Admin\CategoryImportController::class, 'index'])->name('categories.import');
    Route::get ('categories/import/template', [\App\Http\Controllers\Admin\CategoryImportController::class, 'template'])->name('categories.import.template');
    Route::post('categories/import/preview',  [\App\Http\Controllers\Admin\CategoryImportController::class, 'preview'])->name('categories.import.preview');
    Route::post('categories/import/commit',   [\App\Http\Controllers\Admin\CategoryImportController::class, 'commit'])->name('categories.import.commit');
    Route::get('categories/search',       [\App\Http\Controllers\Admin\CategoryController::class, 'search'])->name('categories.search');
    Route::get('categories/search-replacement', [\App\Http\Controllers\Admin\CategoryController::class, 'searchForReplacement'])->name('categories.search-replacement');
    Route::post('categories',             [\App\Http\Controllers\Admin\CategoryController::class, 'store'])->name('categories.store');
    Route::post('categories/reorder',     [\App\Http\Controllers\Admin\CategoryController::class, 'reorder'])->name('categories.reorder');
    Route::patch('categories/{category}', [\App\Http\Controllers\Admin\CategoryController::class, 'update'])->name('categories.update');
    Route::get('categories/{category}/delete-info',     [\App\Http\Controllers\Admin\CategoryController::class, 'deleteInfo'])->name('categories.delete-info');
    Route::get('categories/{category}/deactivate-check',[\App\Http\Controllers\Admin\CategoryController::class, 'deactivateCheck'])->name('categories.deactivate-check');
    Route::delete('categories/{category}',[\App\Http\Controllers\Admin\CategoryController::class, 'destroy'])->name('categories.destroy');

    // Brands — flat list with side editor (same shape as Categories minus the tree).
    Route::get('brands',           [\App\Http\Controllers\Admin\BrandController::class, 'index'])->name('brands.index');
    Route::get('brands/rows',      [\App\Http\Controllers\Admin\BrandController::class, 'rows'])->name('brands.rows');
    Route::get('brands/export',    [\App\Http\Controllers\Admin\BrandController::class, 'export'])->name('brands.export');
    Route::get('brands/search',    [\App\Http\Controllers\Admin\BrandController::class, 'search'])->name('brands.search');
    Route::get('brands/search-replacement', [\App\Http\Controllers\Admin\BrandController::class, 'searchForReplacement'])->name('brands.search-replacement');
    Route::post('brands',          [\App\Http\Controllers\Admin\BrandController::class, 'store'])->name('brands.store');
    Route::patch('brands/{brand}', [\App\Http\Controllers\Admin\BrandController::class, 'update'])->name('brands.update');
    Route::get('brands/{brand}/delete-info',      [\App\Http\Controllers\Admin\BrandController::class, 'deleteInfo'])->name('brands.delete-info');
    Route::get('brands/{brand}/deactivate-check', [\App\Http\Controllers\Admin\BrandController::class, 'deactivateCheck'])->name('brands.deactivate-check');
    Route::delete('brands/{brand}',[\App\Http\Controllers\Admin\BrandController::class, 'destroy'])->name('brands.destroy');

    // Terminals — checkout stations + their hardware config (flat list, side editor).
    Route::get('terminals',              [\App\Http\Controllers\Admin\TerminalController::class, 'index'])->name('terminals.index');
    Route::get('terminals/rows',         [\App\Http\Controllers\Admin\TerminalController::class, 'rows'])->name('terminals.rows');
    Route::get('terminals/export',       [\App\Http\Controllers\Admin\TerminalController::class, 'export'])->name('terminals.export');
    // Dedicated add / edit pages (the editor moved off the index into its
    // own full-width page — the config grew too tall for a side panel).
    Route::get('terminals/create',          [\App\Http\Controllers\Admin\TerminalController::class, 'create'])->name('terminals.create');
    Route::get('terminals/{terminal}/edit', [\App\Http\Controllers\Admin\TerminalController::class, 'edit'])->name('terminals.edit');
    Route::post('terminals',             [\App\Http\Controllers\Admin\TerminalController::class, 'store'])->name('terminals.store');
    // Async upload for a customer-display attract-media image — returns the
    // stored path + URL; the editor keeps the path and submits it on save.
    Route::post('terminals/cfd-media',   [\App\Http\Controllers\Admin\TerminalController::class, 'uploadCfdMedia'])->name('terminals.cfd-media');
    Route::patch('terminals/{terminal}', [\App\Http\Controllers\Admin\TerminalController::class, 'update'])->name('terminals.update');
    Route::delete('terminals/{terminal}',[\App\Http\Controllers\Admin\TerminalController::class, 'destroy'])->name('terminals.destroy');
    // Bind / unbind THIS browser (workstation) to a terminal via the
    // long-lived `pos_terminal_id` cookie that current_terminal() reads.
    Route::post('terminals/clear-selection',  [\App\Http\Controllers\Admin\TerminalController::class, 'clearSelection'])->name('terminals.clear-selection');
    Route::post('terminals/{terminal}/select',[\App\Http\Controllers\Admin\TerminalController::class, 'select'])->name('terminals.select');

    // Self-ordering kiosk — staff orders queue (order-mode placements
    // awaiting counter payment). See docs/features/kiosk-self-ordering.md §5.
    Route::get('kiosk-orders',                 [\App\Http\Controllers\Admin\KioskOrderController::class, 'index'])->name('kiosk-orders.index');
    Route::get('kiosk-orders/rows',            [\App\Http\Controllers\Admin\KioskOrderController::class, 'rows'])->name('kiosk-orders.rows');
    // Full kiosk-order history (any status), date-windowed — the two live
    // queues only ever show open work.
    Route::get('kiosk-orders/all-rows',        [\App\Http\Controllers\Admin\KioskOrderController::class, 'allRows'])->name('kiosk-orders.all-rows');
    Route::get('kiosk-orders/export',          [\App\Http\Controllers\Admin\KioskOrderController::class, 'export'])->name('kiosk-orders.export');
    // Take payment for a pending kiosk order without leaving this page: the
    // panel edits lines, splits tenders (cash/card/QR), then completes.
    Route::post('kiosk-orders/{sale}/pay', [\App\Http\Controllers\Admin\KioskOrderController::class, 'pay'])->name('kiosk-orders.pay');
    Route::post('kiosk-orders/{sale}/reject',  [\App\Http\Controllers\Admin\KioskOrderController::class, 'reject'])->name('kiosk-orders.reject');
    // Already paid at the kiosk — staff hand the goods over and clear it.
    Route::post('kiosk-orders/{sale}/collect', [\App\Http\Controllers\Admin\KioskOrderController::class, 'collect'])->name('kiosk-orders.collect');

    // Units — flat list with side editor + base/derived linkage.
    // NOTE: literal sub-paths (export, categories) must come BEFORE the
    // {unit} wildcard or Laravel routes them to UnitController first.
    Route::get('units',                  [\App\Http\Controllers\Admin\UnitController::class, 'index'])->name('units.index');
    Route::get('units/rows',             [\App\Http\Controllers\Admin\UnitController::class, 'rows'])->name('units.rows');
    Route::get('units/export',           [\App\Http\Controllers\Admin\UnitController::class, 'export'])->name('units.export');
    Route::post('units',                 [\App\Http\Controllers\Admin\UnitController::class, 'store'])->name('units.store');

    // Unit Categories — dedicated page for managing measurement categories.
    Route::get('units/categories/export',                              [\App\Http\Controllers\Admin\UnitCategoryController::class, 'export'])->name('units.categories.export');
    Route::get('units/categories',                                     [\App\Http\Controllers\Admin\UnitCategoryController::class, 'index'])->name('units.categories.index');
    Route::post('units/categories',                                    [\App\Http\Controllers\Admin\UnitCategoryController::class, 'store'])->name('units.categories.store');
    Route::patch('units/categories/{unitCategory}',                    [\App\Http\Controllers\Admin\UnitCategoryController::class, 'update'])->name('units.categories.update');
    Route::delete('units/categories/{unitCategory}',                   [\App\Http\Controllers\Admin\UnitCategoryController::class, 'destroy'])->name('units.categories.destroy');
    Route::get('units/categories/{unitCategory}/deactivate-check',     [\App\Http\Controllers\Admin\UnitCategoryController::class, 'deactivateCheck'])->name('units.categories.deactivate-check');

    // Units wildcard routes (after all literal paths to avoid shadowing).
    Route::patch('units/{unit}',                     [\App\Http\Controllers\Admin\UnitController::class, 'update'])->name('units.update');
    Route::get('units/{unit}/deactivate-check',      [\App\Http\Controllers\Admin\UnitController::class, 'deactivateCheck'])->name('units.deactivate-check');
    Route::delete('units/{unit}',                    [\App\Http\Controllers\Admin\UnitController::class, 'destroy'])->name('units.destroy');

    // Products — full-page list + dedicated create/edit pages (too many
    // fields for the side-editor pattern used by Brand/Unit/Category).
    Route::get('products',                [\App\Http\Controllers\Admin\ProductController::class, 'index'])->name('products.index');
    Route::get('products/export',         [\App\Http\Controllers\Admin\ProductController::class, 'export'])->name('products.export');
    // JSON search for the remote product picker (kit components, etc.).
    Route::get('products/search',         [\App\Http\Controllers\Admin\ProductController::class, 'search'])->name('products.search');
    // Exact barcode/sku lookup for the label-print scan-and-Enter box.
    Route::get('products/lookup-barcode', [\App\Http\Controllers\Admin\ProductController::class, 'lookupBarcode'])->name('products.lookup-barcode');
    // One page of server-paginated rows/cards as an HTML fragment — the
    // Products list fetches this on every page / search / filter / sort.
    Route::get('products/rows',           [\App\Http\Controllers\Admin\ProductController::class, 'rows'])->name('products.rows');
    // CSV / XLSX import wizard — two steps. `preview` stages the file
    // and returns suggested column mapping; `commit` runs the import.
    Route::get ('products/import',          [\App\Http\Controllers\Admin\ProductImportController::class, 'index'])->name('products.import');
    Route::get ('products/import/template', [\App\Http\Controllers\Admin\ProductImportController::class, 'template'])->name('products.import.template');
    Route::post('products/import/preview',  [\App\Http\Controllers\Admin\ProductImportController::class, 'preview'])->name('products.import.preview');
    Route::post('products/import/commit',  [\App\Http\Controllers\Admin\ProductImportController::class, 'commit'])->name('products.import.commit');
    // Bulk product images — match declared filenames to products, then upload
    // in batches. Before the products/{product} wildcard.
    Route::get ('products/bulk-images',       [\App\Http\Controllers\Admin\BulkProductImageController::class, 'index'])->name('products.bulk-images');
    Route::post('products/bulk-images/match', [\App\Http\Controllers\Admin\BulkProductImageController::class, 'match'])->name('products.bulk-images.match');
    Route::post('products/bulk-images/store', [\App\Http\Controllers\Admin\BulkProductImageController::class, 'store'])->name('products.bulk-images.store');
    // Label printer — wizard + print-ready A4 sheet (kept before the
    // products/{product} wildcard so "labels" isn't read as an id).
    Route::get ('products/labels',         [\App\Http\Controllers\Admin\LabelController::class, 'form'])->name('products.labels');
    Route::post('products/labels/sheet',   [\App\Http\Controllers\Admin\LabelController::class, 'sheet'])->name('products.labels.sheet');
    // Label Designer — free positioning for one label size at a time,
    // see app/Http/Controllers/Admin/LabelLayoutController.php.
    Route::get('products/labels/designer/{layoutKey}', [\App\Http\Controllers\Admin\LabelLayoutController::class, 'edit'])
        ->name('products.labels.designer.edit')
        ->where('layoutKey', implode('|', array_map('preg_quote', array_keys(config('labels.layouts')))));
    Route::patch('products/labels/designer/{layoutKey}/elements/{type}', [\App\Http\Controllers\Admin\LabelLayoutController::class, 'update'])
        ->name('products.labels.designer.update')
        ->where(['layoutKey' => implode('|', array_map('preg_quote', array_keys(config('labels.layouts')))), 'type' => 'name|sku|price|barcode|image']);
    Route::get('products/labels/designer/{layoutKey}/preview', [\App\Http\Controllers\Admin\LabelLayoutController::class, 'preview'])
        ->name('products.labels.designer.preview')
        ->where('layoutKey', implode('|', array_map('preg_quote', array_keys(config('labels.layouts')))));
    Route::get('products/create',         [\App\Http\Controllers\Admin\ProductController::class, 'create'])->name('products.create');
    Route::post('products',               [\App\Http\Controllers\Admin\ProductController::class, 'store'])->name('products.store');
    Route::get('products/{product}/edit', [\App\Http\Controllers\Admin\ProductController::class, 'edit'])->name('products.edit');
    Route::patch('products/{product}',    [\App\Http\Controllers\Admin\ProductController::class, 'update'])->name('products.update');
    Route::post('products/{product}/toggle', [\App\Http\Controllers\Admin\ProductController::class, 'toggle'])->name('products.toggle');
    Route::delete('products/{product}',   [\App\Http\Controllers\Admin\ProductController::class, 'destroy'])->name('products.destroy');

    // Drug Schedules — pharmacy compliance lookup, fed into the
    // Products → Compliance tab. Same side-editor pattern as Brands.
    Route::get('drug-schedules/export',                [\App\Http\Controllers\Admin\DrugScheduleController::class, 'export'])->name('drug-schedules.export');
    Route::get('drug-schedules/search-replacement',    [\App\Http\Controllers\Admin\DrugScheduleController::class, 'searchForReplacement'])->name('drug-schedules.search-replacement');
    Route::get('drug-schedules',                       [\App\Http\Controllers\Admin\DrugScheduleController::class, 'index'])->name('drug-schedules.index');
    Route::post('drug-schedules',                      [\App\Http\Controllers\Admin\DrugScheduleController::class, 'store'])->name('drug-schedules.store');
    Route::patch('drug-schedules/{drugSchedule}',                [\App\Http\Controllers\Admin\DrugScheduleController::class, 'update'])->name('drug-schedules.update');
    Route::get('drug-schedules/{drugSchedule}/delete-info',     [\App\Http\Controllers\Admin\DrugScheduleController::class, 'deleteInfo'])->name('drug-schedules.delete-info');
    Route::get('drug-schedules/{drugSchedule}/deactivate-check',[\App\Http\Controllers\Admin\DrugScheduleController::class, 'deactivateCheck'])->name('drug-schedules.deactivate-check');
    Route::delete('drug-schedules/{drugSchedule}',               [\App\Http\Controllers\Admin\DrugScheduleController::class, 'destroy'])->name('drug-schedules.destroy');

    // ── Inventory ──
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('levels/export',                          [\App\Http\Controllers\Admin\StockLevelController::class, 'export'])->name('levels.export');
        Route::get('levels',                                 [\App\Http\Controllers\Admin\StockLevelController::class, 'index'])->name('levels.index');
        Route::get('levels/rows',                            [\App\Http\Controllers\Admin\StockLevelController::class, 'rows'])->name('levels.rows');
        Route::patch('levels/{stockLevel}/reorder-override', [\App\Http\Controllers\Admin\StockLevelController::class, 'updateReorderOverride'])->name('levels.reorder');
        Route::get('movements',                              [\App\Http\Controllers\Admin\StockMovementController::class, 'index'])->name('movements.index');
        Route::get('movements/rows',                         [\App\Http\Controllers\Admin\StockMovementController::class, 'rows'])->name('movements.rows');
        Route::get('low-stock',                              [\App\Http\Controllers\Admin\LowStockReportController::class, 'index'])->name('low-stock.index');
        Route::get('low-stock/export',                       [\App\Http\Controllers\Admin\LowStockReportController::class, 'export'])->name('low-stock.export');
        Route::get('oversold',                               [\App\Http\Controllers\Admin\OversoldReportController::class, 'index'])->name('oversold.index');
        Route::get('oversold/export',                        [\App\Http\Controllers\Admin\OversoldReportController::class, 'export'])->name('oversold.export');

        // Product batches (Slice 3b) — read-only visibility for owners.
        // CRUD lives in ReceivePurchase + RecordStockMovement.
        Route::get('batches/export',                         [\App\Http\Controllers\Admin\ProductBatchController::class, 'export'])->name('batches.export');
        Route::get('batches/rows',                           [\App\Http\Controllers\Admin\ProductBatchController::class, 'rows'])->name('batches.rows');
        Route::get('batches',                                [\App\Http\Controllers\Admin\ProductBatchController::class, 'index'])->name('batches.index');
        // Archive (soft-delete) a spent batch — the only write path here.
        // Wildcard LAST so `batches/export` + `batches/rows` still resolve.
        Route::delete('batches/{batch}',                     [\App\Http\Controllers\Admin\ProductBatchController::class, 'destroy'])->name('batches.destroy');
        // Un-archive. Binds by raw id (the controller resolves withTrashed) —
        // implicit binding would 404 on the very rows this acts on.
        Route::post('batches/{batch}/restore',               [\App\Http\Controllers\Admin\ProductBatchController::class, 'restore'])->name('batches.restore');

        // Stock takes (cycle counts) — same permission family as
        // adjustments (`products.adjust_stock`); see StockTakePolicy.
        Route::get('stock-takes/export',                     [\App\Http\Controllers\Admin\StockTakeController::class, 'export'])->name('stock-takes.export');
        Route::get('stock-takes',                            [\App\Http\Controllers\Admin\StockTakeController::class, 'index'])->name('stock-takes.index');
        Route::get('stock-takes/create',                     [\App\Http\Controllers\Admin\StockTakeController::class, 'create'])->name('stock-takes.create');
        Route::post('stock-takes',                           [\App\Http\Controllers\Admin\StockTakeController::class, 'store'])->name('stock-takes.store');
        // Before the {stockTake} wildcard so "scan" isn't read as an id.
        Route::get('stock-takes/scan',                       [\App\Http\Controllers\Admin\StockTakeController::class, 'scan'])->name('stock-takes.scan');
        Route::get('stock-takes/{stockTake}',                [\App\Http\Controllers\Admin\StockTakeController::class, 'show'])->name('stock-takes.show');
        Route::get('stock-takes/{stockTake}/edit',           [\App\Http\Controllers\Admin\StockTakeController::class, 'edit'])->name('stock-takes.edit');
        Route::patch('stock-takes/{stockTake}',              [\App\Http\Controllers\Admin\StockTakeController::class, 'update'])->name('stock-takes.update');
        Route::post('stock-takes/{stockTake}/post',          [\App\Http\Controllers\Admin\StockTakeController::class, 'post'])->name('stock-takes.post');
        Route::delete('stock-takes/{stockTake}',             [\App\Http\Controllers\Admin\StockTakeController::class, 'destroy'])->name('stock-takes.destroy');

        Route::get('adjustments/export',                     [\App\Http\Controllers\Admin\StockAdjustmentController::class, 'export'])->name('adjustments.export');
        Route::get('adjustments',                            [\App\Http\Controllers\Admin\StockAdjustmentController::class, 'index'])->name('adjustments.index');
        Route::get('adjustments/rows',                       [\App\Http\Controllers\Admin\StockAdjustmentController::class, 'rows'])->name('adjustments.rows');
        Route::get('adjustments/search-products',            [\App\Http\Controllers\Admin\StockAdjustmentController::class, 'searchProducts'])->name('adjustments.search-products');
        Route::get('adjustments/scan',                       [\App\Http\Controllers\Admin\StockAdjustmentController::class, 'scan'])->name('adjustments.scan');
        Route::get('adjustments/batches',                    [\App\Http\Controllers\Admin\StockAdjustmentController::class, 'batches'])->name('adjustments.batches');
        Route::post('adjustments/product-stock',             [\App\Http\Controllers\Admin\StockAdjustmentController::class, 'productStock'])->name('adjustments.product-stock');
        Route::get('adjustments/create',                     [\App\Http\Controllers\Admin\StockAdjustmentController::class, 'create'])->name('adjustments.create');
        Route::post('adjustments',                           [\App\Http\Controllers\Admin\StockAdjustmentController::class, 'store'])->name('adjustments.store');
        Route::get('adjustments/{stockAdjustment}',          [\App\Http\Controllers\Admin\StockAdjustmentController::class, 'show'])->name('adjustments.show');
        Route::get('adjustments/{stockAdjustment}/edit',     [\App\Http\Controllers\Admin\StockAdjustmentController::class, 'edit'])->name('adjustments.edit');
        Route::patch('adjustments/{stockAdjustment}',        [\App\Http\Controllers\Admin\StockAdjustmentController::class, 'update'])->name('adjustments.update');
        Route::post('adjustments/{stockAdjustment}/post',    [\App\Http\Controllers\Admin\StockAdjustmentController::class, 'post'])->name('adjustments.post');
        Route::delete('adjustments/{stockAdjustment}',       [\App\Http\Controllers\Admin\StockAdjustmentController::class, 'destroy'])->name('adjustments.destroy');

        Route::get('transfers/export',                               [\App\Http\Controllers\Admin\StockTransferController::class, 'export'])->name('transfers.export');
        Route::get('transfers',                                      [\App\Http\Controllers\Admin\StockTransferController::class, 'index'])->name('transfers.index');
        Route::get('transfers/rows',                                 [\App\Http\Controllers\Admin\StockTransferController::class, 'rows'])->name('transfers.rows');
        Route::get('transfers/search-products',                      [\App\Http\Controllers\Admin\StockTransferController::class, 'searchProducts'])->name('transfers.search-products');
        Route::get('transfers/scan',                                 [\App\Http\Controllers\Admin\StockTransferController::class, 'scan'])->name('transfers.scan');
        Route::post('transfers/product-stock',                       [\App\Http\Controllers\Admin\StockTransferController::class, 'productStock'])->name('transfers.product-stock');
        Route::get('transfers/create',                               [\App\Http\Controllers\Admin\StockTransferController::class, 'create'])->name('transfers.create');
        Route::post('transfers',                                     [\App\Http\Controllers\Admin\StockTransferController::class, 'store'])->name('transfers.store');
        Route::get('transfers/{stockTransfer}',                      [\App\Http\Controllers\Admin\StockTransferController::class, 'show'])->name('transfers.show');
        Route::get('transfers/{stockTransfer}/edit',                 [\App\Http\Controllers\Admin\StockTransferController::class, 'edit'])->name('transfers.edit');
        Route::patch('transfers/{stockTransfer}',                    [\App\Http\Controllers\Admin\StockTransferController::class, 'update'])->name('transfers.update');
        Route::post('transfers/{stockTransfer}/dispatch',            [\App\Http\Controllers\Admin\StockTransferController::class, 'dispatch'])->name('transfers.dispatch');
        Route::post('transfers/{stockTransfer}/receive',             [\App\Http\Controllers\Admin\StockTransferController::class, 'receive'])->name('transfers.receive');
        Route::post('transfers/{stockTransfer}/cancel',              [\App\Http\Controllers\Admin\StockTransferController::class, 'cancel'])->name('transfers.cancel');
        Route::delete('transfers/{stockTransfer}',                   [\App\Http\Controllers\Admin\StockTransferController::class, 'destroy'])->name('transfers.destroy');

        Route::get('adjustment-reasons',                                       [\App\Http\Controllers\Admin\StockAdjustmentReasonController::class, 'index'])->name('adjustment-reasons.index');
        Route::get('adjustment-reasons/rows',                                  [\App\Http\Controllers\Admin\StockAdjustmentReasonController::class, 'rows'])->name('adjustment-reasons.rows');
        Route::post('adjustment-reasons',                                      [\App\Http\Controllers\Admin\StockAdjustmentReasonController::class, 'store'])->name('adjustment-reasons.store');
        Route::patch('adjustment-reasons/{stockAdjustmentReason}',             [\App\Http\Controllers\Admin\StockAdjustmentReasonController::class, 'update'])->name('adjustment-reasons.update');
        Route::delete('adjustment-reasons/{stockAdjustmentReason}',            [\App\Http\Controllers\Admin\StockAdjustmentReasonController::class, 'destroy'])->name('adjustment-reasons.destroy');
    });

    // ── Customers ──
    Route::get('customers/export',           [\App\Http\Controllers\Admin\CustomerController::class, 'export'])->name('customers.export');
    Route::get('customers/search',           [\App\Http\Controllers\Admin\CustomerController::class, 'search'])->name('customers.search');
    Route::get('customers/rows',             [\App\Http\Controllers\Admin\CustomerController::class, 'rows'])->name('customers.rows');
    Route::get('customers',                  [\App\Http\Controllers\Admin\CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/create',           [\App\Http\Controllers\Admin\CustomerController::class, 'create'])->name('customers.create');
    Route::post('customers',                 [\App\Http\Controllers\Admin\CustomerController::class, 'store'])->name('customers.store');
    Route::get('customers/{customer}',       [\App\Http\Controllers\Admin\CustomerController::class, 'show'])->name('customers.show');
    Route::get('customers/{customer}/edit',  [\App\Http\Controllers\Admin\CustomerController::class, 'edit'])->name('customers.edit');
    Route::patch('customers/{customer}',     [\App\Http\Controllers\Admin\CustomerController::class, 'update'])->name('customers.update');
    Route::patch('customers/{customer}/toggle-active', [\App\Http\Controllers\Admin\CustomerController::class, 'toggleActive'])->name('customers.toggle-active');
    Route::delete('customers/{customer}',    [\App\Http\Controllers\Admin\CustomerController::class, 'destroy'])->name('customers.destroy');

    // Customer groups (sub-setting)
    Route::get('customer-groups/export',                   [\App\Http\Controllers\Admin\CustomerGroupController::class, 'export'])->name('customer-groups.export');
    Route::get('customer-groups',                          [\App\Http\Controllers\Admin\CustomerGroupController::class, 'index'])->name('customer-groups.index');
    Route::get('customer-groups/rows',                     [\App\Http\Controllers\Admin\CustomerGroupController::class, 'rows'])->name('customer-groups.rows');
    Route::post('customer-groups',                         [\App\Http\Controllers\Admin\CustomerGroupController::class, 'store'])->name('customer-groups.store');
    Route::patch('customer-groups/{customerGroup}',        [\App\Http\Controllers\Admin\CustomerGroupController::class, 'update'])->name('customer-groups.update');
    Route::delete('customer-groups/{customerGroup}',       [\App\Http\Controllers\Admin\CustomerGroupController::class, 'destroy'])->name('customer-groups.destroy');

    // ── Reports ──
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('',                            [\App\Http\Controllers\Admin\ReportsHubController::class, '__invoke'])->name('index');
        Route::get('sales',                       [\App\Http\Controllers\Admin\SalesSummaryReportController::class, 'index'])->name('sales.index');
        Route::get('sales/export',                [\App\Http\Controllers\Admin\SalesSummaryReportController::class, 'export'])->name('sales.export');
        Route::get('sales-by-product',            [\App\Http\Controllers\Admin\SalesByProductReportController::class, 'index'])->name('sales-by-product.index');
        Route::get('sales-by-product/export',     [\App\Http\Controllers\Admin\SalesByProductReportController::class, 'export'])->name('sales-by-product.export');
        Route::get('sales-by-cashier',            [\App\Http\Controllers\Admin\SalesByCashierReportController::class, 'index'])->name('sales-by-cashier.index');
        Route::get('sales-by-cashier/export',     [\App\Http\Controllers\Admin\SalesByCashierReportController::class, 'export'])->name('sales-by-cashier.export');
        Route::get('sales-by-payment-method',     [\App\Http\Controllers\Admin\SalesByPaymentMethodReportController::class, 'index'])->name('sales-by-payment-method.index');
        Route::get('sales-by-payment-method/export', [\App\Http\Controllers\Admin\SalesByPaymentMethodReportController::class, 'export'])->name('sales-by-payment-method.export');
        Route::get('sales-by-category',           [\App\Http\Controllers\Admin\SalesByCategoryReportController::class, 'index'])->name('sales-by-category.index');
        Route::get('sales-by-category/export',     [\App\Http\Controllers\Admin\SalesByCategoryReportController::class, 'export'])->name('sales-by-category.export');
        Route::get('discounts',                   [\App\Http\Controllers\Admin\DiscountsReportController::class, 'index'])->name('discounts.index');
        Route::get('discounts/export',             [\App\Http\Controllers\Admin\DiscountsReportController::class, 'export'])->name('discounts.export');
        Route::get('top-customers',               [\App\Http\Controllers\Admin\TopCustomersReportController::class, 'index'])->name('top-customers.index');
        Route::get('top-customers/export',         [\App\Http\Controllers\Admin\TopCustomersReportController::class, 'export'])->name('top-customers.export');
        Route::get('top-suppliers',               [\App\Http\Controllers\Admin\TopSuppliersReportController::class, 'index'])->name('top-suppliers.index');
        Route::get('top-suppliers/export',         [\App\Http\Controllers\Admin\TopSuppliersReportController::class, 'export'])->name('top-suppliers.export');
        Route::get('shifts-by-cashier',           [\App\Http\Controllers\Admin\ShiftsByCashierReportController::class, 'index'])->name('shifts-by-cashier.index');
        Route::get('shifts-by-cashier/export',     [\App\Http\Controllers\Admin\ShiftsByCashierReportController::class, 'export'])->name('shifts-by-cashier.export');
        Route::get('aged-receivables',            [\App\Http\Controllers\Admin\AgedReceivablesReportController::class, 'index'])->name('aged-receivables.index');
        Route::get('aged-receivables/export',     [\App\Http\Controllers\Admin\AgedReceivablesReportController::class, 'export'])->name('aged-receivables.export');

        // Accounting reports (Slice 3) — read-only aggregation of the ledger.
        Route::get('trial-balance',               [\App\Http\Controllers\Admin\TrialBalanceReportController::class, 'index'])->name('trial-balance.index');
        Route::get('trial-balance/export',        [\App\Http\Controllers\Admin\TrialBalanceReportController::class, 'export'])->name('trial-balance.export');
        Route::get('general-ledger',              [\App\Http\Controllers\Admin\GeneralLedgerReportController::class, 'index'])->name('general-ledger.index');
        Route::get('general-ledger/export',       [\App\Http\Controllers\Admin\GeneralLedgerReportController::class, 'export'])->name('general-ledger.export');
        Route::get('profit-and-loss',             [\App\Http\Controllers\Admin\ProfitAndLossReportController::class, 'index'])->name('profit-and-loss.index');
        Route::get('profit-and-loss/export',      [\App\Http\Controllers\Admin\ProfitAndLossReportController::class, 'export'])->name('profit-and-loss.export');
        Route::get('balance-sheet',               [\App\Http\Controllers\Admin\BalanceSheetReportController::class, 'index'])->name('balance-sheet.index');
        Route::get('balance-sheet/export',        [\App\Http\Controllers\Admin\BalanceSheetReportController::class, 'export'])->name('balance-sheet.export');
        Route::get('cash-flow',                   [\App\Http\Controllers\Admin\CashFlowReportController::class, 'index'])->name('cash-flow.index');
        Route::get('cash-flow/export',            [\App\Http\Controllers\Admin\CashFlowReportController::class, 'export'])->name('cash-flow.export');

        // Saved reports (Slice 5a) — capture a report's current params under a name.
        Route::get('saved',                  [\App\Http\Controllers\Admin\SavedReportController::class, 'index'])->name('saved.index');
        Route::post('saved',                 [\App\Http\Controllers\Admin\SavedReportController::class, 'store'])->name('saved.store');
        Route::delete('saved/{savedReport}', [\App\Http\Controllers\Admin\SavedReportController::class, 'destroy'])->name('saved.destroy');

        // Scheduled reports (Slice 5b) — recurring email delivery of a report.
        Route::get('schedules',                              [\App\Http\Controllers\Admin\ScheduledReportController::class, 'index'])->name('schedules.index');
        Route::post('schedules',                             [\App\Http\Controllers\Admin\ScheduledReportController::class, 'store'])->name('schedules.store');
        Route::post('schedules/{scheduledReport}/pause',     [\App\Http\Controllers\Admin\ScheduledReportController::class, 'pause'])->name('schedules.pause');
        Route::post('schedules/{scheduledReport}/resume',    [\App\Http\Controllers\Admin\ScheduledReportController::class, 'resume'])->name('schedules.resume');
        Route::post('schedules/{scheduledReport}/run-now',   [\App\Http\Controllers\Admin\ScheduledReportController::class, 'runNow'])->name('schedules.run-now');
        Route::delete('schedules/{scheduledReport}',         [\App\Http\Controllers\Admin\ScheduledReportController::class, 'destroy'])->name('schedules.destroy');
    });

    // ── Accounting (Slice 8) — journal / day book + manual entries ──
    Route::prefix('accounting')->name('accounting.')->group(function () {
        Route::get('journal',                    [\App\Http\Controllers\Admin\JournalEntryController::class, 'index'])->name('journal.index');
        Route::get('journal/create',             [\App\Http\Controllers\Admin\JournalEntryController::class, 'create'])->name('journal.create');
        Route::post('journal',                   [\App\Http\Controllers\Admin\JournalEntryController::class, 'store'])->name('journal.store');
        Route::get('journal/{entry}',            [\App\Http\Controllers\Admin\JournalEntryController::class, 'show'])->name('journal.show');
        Route::post('journal/{entry}/reverse',   [\App\Http\Controllers\Admin\JournalEntryController::class, 'reverse'])->name('journal.reverse');

        // Fiscal years / periods + year-end close (Slice 9).
        Route::get('periods',                    [\App\Http\Controllers\Admin\FiscalPeriodController::class, 'index'])->name('periods.index');
        Route::post('periods/{period}/lock',     [\App\Http\Controllers\Admin\FiscalPeriodController::class, 'lockPeriod'])->name('periods.lock');
        Route::post('periods/{period}/unlock',   [\App\Http\Controllers\Admin\FiscalPeriodController::class, 'unlockPeriod'])->name('periods.unlock');
        Route::post('years/{year}/close',        [\App\Http\Controllers\Admin\FiscalPeriodController::class, 'closeYear'])->name('years.close');

        // Chart of accounts + business-event mappings (Slice 10).
        Route::get('chart-of-accounts',              [\App\Http\Controllers\Admin\ChartOfAccountsController::class, 'index'])->name('chart-of-accounts.index');
        Route::post('chart-of-accounts',             [\App\Http\Controllers\Admin\ChartOfAccountsController::class, 'store'])->name('chart-of-accounts.store');
        // Account groups CRUD (Slice 12) — declared before {account} so `groups` isn't captured as an account.
        Route::post('chart-of-accounts/groups',           [\App\Http\Controllers\Admin\ChartOfAccountsController::class, 'storeGroup'])->name('chart-of-accounts.groups.store');
        Route::patch('chart-of-accounts/groups/{group}',  [\App\Http\Controllers\Admin\ChartOfAccountsController::class, 'updateGroup'])->name('chart-of-accounts.groups.update');
        Route::delete('chart-of-accounts/groups/{group}', [\App\Http\Controllers\Admin\ChartOfAccountsController::class, 'destroyGroup'])->name('chart-of-accounts.groups.destroy');
        Route::patch('chart-of-accounts/{account}',  [\App\Http\Controllers\Admin\ChartOfAccountsController::class, 'update'])->name('chart-of-accounts.update');
        Route::delete('chart-of-accounts/{account}', [\App\Http\Controllers\Admin\ChartOfAccountsController::class, 'destroy'])->name('chart-of-accounts.destroy');

        Route::get('mappings',   [\App\Http\Controllers\Admin\AccountMappingController::class, 'index'])->name('mappings.index');
        Route::patch('mappings', [\App\Http\Controllers\Admin\AccountMappingController::class, 'update'])->name('mappings.update');

        // Opening balances (migration) — Slice 11.
        Route::get('opening-balances',  [\App\Http\Controllers\Admin\OpeningBalanceController::class, 'index'])->name('opening-balances.index');
        Route::post('opening-balances', [\App\Http\Controllers\Admin\OpeningBalanceController::class, 'store'])->name('opening-balances.store');
    });

    // ── Sync log (Slice 4) ──
    Route::get('sync-log',             [\App\Http\Controllers\Admin\SyncLogController::class, 'index'])->name('sync-log.index');
    Route::get('sync-log/rows',        [\App\Http\Controllers\Admin\SyncLogController::class, 'rows'])->name('sync-log.rows');
    Route::get('sync-log/{syncLog}',   [\App\Http\Controllers\Admin\SyncLogController::class, 'show'])->name('sync-log.show');

    // ── Purchases ──
    Route::get('purchases/export',           [\App\Http\Controllers\Admin\PurchaseController::class, 'export'])->name('purchases.export');
    Route::get('purchases/rows',             [\App\Http\Controllers\Admin\PurchaseController::class, 'rows'])->name('purchases.rows');
    Route::get('purchases/scan',             [\App\Http\Controllers\Admin\PurchaseController::class, 'scan'])->name('purchases.scan');
    Route::get('purchases/prices',           [\App\Http\Controllers\Admin\PurchaseController::class, 'prices'])->name('purchases.prices');
    Route::get('purchases',                  [\App\Http\Controllers\Admin\PurchaseController::class, 'index'])->name('purchases.index');
    Route::get('purchases/create',           [\App\Http\Controllers\Admin\PurchaseController::class, 'create'])->name('purchases.create');
    Route::post('purchases',                 [\App\Http\Controllers\Admin\PurchaseController::class, 'store'])->name('purchases.store');
    Route::get('purchases/{purchase}',       [\App\Http\Controllers\Admin\PurchaseController::class, 'show'])->name('purchases.show');
    Route::get('purchases/{purchase}/edit',  [\App\Http\Controllers\Admin\PurchaseController::class, 'edit'])->name('purchases.edit');
    Route::get('purchases/{purchase}/attachment/{attachment}', [\App\Http\Controllers\Admin\PurchaseController::class, 'downloadAttachment'])->name('purchases.attachment.download');
    Route::patch('purchases/{purchase}',     [\App\Http\Controllers\Admin\PurchaseController::class, 'update'])->name('purchases.update');
    Route::post('purchases/{purchase}/receive', [\App\Http\Controllers\Admin\PurchaseController::class, 'receive'])->name('purchases.receive');
    Route::post('purchases/{purchase}/cancel',  [\App\Http\Controllers\Admin\PurchaseController::class, 'cancel'])->name('purchases.cancel');
    Route::delete('purchases/{purchase}',    [\App\Http\Controllers\Admin\PurchaseController::class, 'destroy'])->name('purchases.destroy');

    // ── Purchase returns ──
    Route::get('purchase-returns',                           [\App\Http\Controllers\Admin\PurchaseReturnController::class, 'index'])->name('purchase-returns.index');
    Route::get('purchase-returns/rows',                      [\App\Http\Controllers\Admin\PurchaseReturnController::class, 'rows'])->name('purchase-returns.rows');
    Route::get('purchase-returns/{purchaseReturn}',          [\App\Http\Controllers\Admin\PurchaseReturnController::class, 'show'])->name('purchase-returns.show');
    Route::get('purchases/{purchase}/returns/create',        [\App\Http\Controllers\Admin\PurchaseReturnController::class, 'create'])->name('purchase-returns.create');
    Route::post('purchases/{purchase}/returns',              [\App\Http\Controllers\Admin\PurchaseReturnController::class, 'store'])->name('purchase-returns.store');

    // ── Supplier payments ──
    Route::get('supplier-payments',                       [\App\Http\Controllers\Admin\SupplierPaymentController::class, 'index'])->name('supplier-payments.index');
    Route::get('supplier-payments/rows',                  [\App\Http\Controllers\Admin\SupplierPaymentController::class, 'rows'])->name('supplier-payments.rows');
    Route::get('supplier-payments/create',                [\App\Http\Controllers\Admin\SupplierPaymentController::class, 'create'])->name('supplier-payments.create');
    Route::post('supplier-payments',                      [\App\Http\Controllers\Admin\SupplierPaymentController::class, 'store'])->name('supplier-payments.store');
    Route::get('supplier-payments/{supplierPayment}',     [\App\Http\Controllers\Admin\SupplierPaymentController::class, 'show'])->name('supplier-payments.show');
    Route::post('supplier-payments/{supplierPayment}/void',[\App\Http\Controllers\Admin\SupplierPaymentController::class, 'void'])->name('supplier-payments.void');
    Route::get('suppliers/{supplier}/open-purchases',     [\App\Http\Controllers\Admin\SupplierPaymentController::class, 'openPurchases'])->name('suppliers.open-purchases');

    // Customer payments (Slice 6b) — mirror of supplier-payments. One
    // payment can settle multiple open sales for a customer; the form's
    // Auto-allocate spreads a typed total across oldest sales FIFO.
    Route::get('customer-payments',                       [\App\Http\Controllers\Admin\CustomerPaymentController::class, 'index'])->name('customer-payments.index');
    Route::get('customer-payments/rows',                  [\App\Http\Controllers\Admin\CustomerPaymentController::class, 'rows'])->name('customer-payments.rows');
    Route::get('customer-payments/create',                [\App\Http\Controllers\Admin\CustomerPaymentController::class, 'create'])->name('customer-payments.create');
    Route::post('customer-payments',                      [\App\Http\Controllers\Admin\CustomerPaymentController::class, 'store'])->name('customer-payments.store');
    Route::get('customer-payments/{customerPayment}',     [\App\Http\Controllers\Admin\CustomerPaymentController::class, 'show'])->name('customer-payments.show');
    Route::get('customers/{customer}/open-sales',         [\App\Http\Controllers\Admin\CustomerPaymentController::class, 'openSales'])->name('customers.open-sales');

    // Customer statement — print-ready event log + running balance.
    Route::get('customers/{customer}/statement',          [\App\Http\Controllers\Admin\CustomerStatementController::class, 'show'])->name('customers.statement.show');
    Route::get('customers/{customer}/statement/print',    [\App\Http\Controllers\Admin\CustomerStatementController::class, 'print'])->name('customers.statement.print');
    Route::post('customers/{customer}/statement/email',   [\App\Http\Controllers\Admin\CustomerStatementController::class, 'email'])->name('customers.statement.email');

    // ── Suppliers ──
    Route::get('suppliers/export',           [\App\Http\Controllers\Admin\SupplierController::class, 'export'])->name('suppliers.export');
    Route::get('suppliers/rows',             [\App\Http\Controllers\Admin\SupplierController::class, 'rows'])->name('suppliers.rows');
    Route::get('suppliers',                  [\App\Http\Controllers\Admin\SupplierController::class, 'index'])->name('suppliers.index');
    Route::get('suppliers/create',           [\App\Http\Controllers\Admin\SupplierController::class, 'create'])->name('suppliers.create');
    Route::post('suppliers',                 [\App\Http\Controllers\Admin\SupplierController::class, 'store'])->name('suppliers.store');
    Route::get('suppliers/{supplier}',       [\App\Http\Controllers\Admin\SupplierController::class, 'show'])->name('suppliers.show');
    Route::get('suppliers/{supplier}/edit',  [\App\Http\Controllers\Admin\SupplierController::class, 'edit'])->name('suppliers.edit');
    Route::patch('suppliers/{supplier}',     [\App\Http\Controllers\Admin\SupplierController::class, 'update'])->name('suppliers.update');
    Route::patch('suppliers/{supplier}/toggle-active', [\App\Http\Controllers\Admin\SupplierController::class, 'toggleActive'])->name('suppliers.toggle-active');
    Route::delete('suppliers/{supplier}',    [\App\Http\Controllers\Admin\SupplierController::class, 'destroy'])->name('suppliers.destroy');

    // Expenses — the drawer's cash-out side (Register & Cash Mgmt, Slice E).
    Route::get('expenses/export',            [\App\Http\Controllers\Admin\ExpenseController::class, 'export'])->name('expenses.export');
    Route::get('expenses/rows',              [\App\Http\Controllers\Admin\ExpenseController::class, 'rows'])->name('expenses.rows');
    Route::get('expenses',                   [\App\Http\Controllers\Admin\ExpenseController::class, 'index'])->name('expenses.index');
    Route::get('expenses/create',            [\App\Http\Controllers\Admin\ExpenseController::class, 'create'])->name('expenses.create');
    Route::post('expenses',                  [\App\Http\Controllers\Admin\ExpenseController::class, 'store'])->name('expenses.store');
    Route::get('expenses/{expense}/edit',    [\App\Http\Controllers\Admin\ExpenseController::class, 'edit'])->name('expenses.edit');
    Route::patch('expenses/{expense}',       [\App\Http\Controllers\Admin\ExpenseController::class, 'update'])->name('expenses.update');
    Route::delete('expenses/{expense}',      [\App\Http\Controllers\Admin\ExpenseController::class, 'destroy'])->name('expenses.destroy');

    // Expense categories — manageable picklist for the expense form.
    Route::get('expense-categories',                      [\App\Http\Controllers\Admin\ExpenseCategoryController::class, 'index'])->name('expense-categories.index');
    Route::post('expense-categories',                     [\App\Http\Controllers\Admin\ExpenseCategoryController::class, 'store'])->name('expense-categories.store');
    Route::patch('expense-categories/{expenseCategory}',  [\App\Http\Controllers\Admin\ExpenseCategoryController::class, 'update'])->name('expense-categories.update');
    Route::delete('expense-categories/{expenseCategory}', [\App\Http\Controllers\Admin\ExpenseCategoryController::class, 'destroy'])->name('expense-categories.destroy');

    // Stores — multi-store CRUD + active-store switcher. Switching sets
    // session('active_store_id'); the StoreScoped trait reads it.
    Route::get('stores/export',          [\App\Http\Controllers\Admin\StoreController::class, 'export'])->name('stores.export');
    Route::get('stores',                 [\App\Http\Controllers\Admin\StoreController::class, 'index'])->name('stores.index');
    Route::get('stores/create',          [\App\Http\Controllers\Admin\StoreController::class, 'create'])->name('stores.create');
    Route::post('stores',                [\App\Http\Controllers\Admin\StoreController::class, 'store'])->name('stores.store');
    Route::get('stores/{store}/edit',    [\App\Http\Controllers\Admin\StoreController::class, 'edit'])->name('stores.edit');
    Route::patch('stores/{store}',       [\App\Http\Controllers\Admin\StoreController::class, 'update'])->name('stores.update');
    Route::patch('stores/{store}/toggle',[\App\Http\Controllers\Admin\StoreController::class, 'toggle'])->name('stores.toggle');
    Route::patch('stores/{store}/default',[\App\Http\Controllers\Admin\StoreController::class, 'setDefault'])->name('stores.default');
    Route::delete('stores/{store}',      [\App\Http\Controllers\Admin\StoreController::class, 'destroy'])->name('stores.destroy');
    Route::post('stores/{store}/switch', \App\Http\Controllers\Admin\StoreSwitchController::class)->name('stores.switch');

    // Users — account management + per-store role assignment.
    Route::get('users/export',          [\App\Http\Controllers\Admin\UserController::class, 'export'])->name('users.export');
    Route::get('users/rows',            [\App\Http\Controllers\Admin\UserController::class, 'rows'])->name('users.rows');
    Route::get('users',                 [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('users.index');
    Route::get('users/create',          [\App\Http\Controllers\Admin\UserController::class, 'create'])->name('users.create');
    Route::post('users',                [\App\Http\Controllers\Admin\UserController::class, 'store'])->name('users.store');
    Route::get('users/{user}/edit',     [\App\Http\Controllers\Admin\UserController::class, 'edit'])->name('users.edit');
    Route::patch('users/{user}',        [\App\Http\Controllers\Admin\UserController::class, 'update'])->name('users.update');
    Route::patch('users/{user}/toggle', [\App\Http\Controllers\Admin\UserController::class, 'toggle'])->name('users.toggle');
    Route::delete('users/{user}',       [\App\Http\Controllers\Admin\UserController::class, 'destroy'])->name('users.destroy');

    // Roles — permission builder. Gated by the `roles.manage` permission.
    Route::get('roles/export',          [\App\Http\Controllers\Admin\RoleController::class, 'export'])->name('roles.export');
    Route::get('roles/rows',            [\App\Http\Controllers\Admin\RoleController::class, 'rows'])->name('roles.rows');
    Route::get('roles',                 [\App\Http\Controllers\Admin\RoleController::class, 'index'])->name('roles.index');
    Route::get('roles/create',          [\App\Http\Controllers\Admin\RoleController::class, 'create'])->name('roles.create');
    Route::post('roles',                [\App\Http\Controllers\Admin\RoleController::class, 'store'])->name('roles.store');
    Route::get('roles/{role}/edit',     [\App\Http\Controllers\Admin\RoleController::class, 'edit'])->name('roles.edit');
    Route::patch('roles/{role}',        [\App\Http\Controllers\Admin\RoleController::class, 'update'])->name('roles.update');
    Route::delete('roles/{role}',       [\App\Http\Controllers\Admin\RoleController::class, 'destroy'])->name('roles.destroy');

    // Language switch (self-service) + Languages admin (UI translations via
    // an Excel round-trip — see docs/features/multi-language.md).
    Route::post('locale/{code}',               [\App\Http\Controllers\Admin\LocaleController::class, 'switch'])->name('locale.switch');
    Route::get('languages',                    [\App\Http\Controllers\Admin\LanguageController::class, 'index'])->name('languages.index');
    Route::get('languages/create',             [\App\Http\Controllers\Admin\LanguageController::class, 'create'])->name('languages.create');
    Route::post('languages',                   [\App\Http\Controllers\Admin\LanguageController::class, 'store'])->name('languages.store');
    Route::get('languages/{language}/edit',    [\App\Http\Controllers\Admin\LanguageController::class, 'edit'])->name('languages.edit');
    Route::patch('languages/{language}',       [\App\Http\Controllers\Admin\LanguageController::class, 'update'])->name('languages.update');
    Route::patch('languages/{language}/default',[\App\Http\Controllers\Admin\LanguageController::class, 'setDefault'])->name('languages.default');
    Route::patch('languages/{language}/toggle',[\App\Http\Controllers\Admin\LanguageController::class, 'toggle'])->name('languages.toggle');
    Route::get('languages/{language}/export',  [\App\Http\Controllers\Admin\LanguageController::class, 'export'])->name('languages.export');
    Route::post('languages/{language}/import', [\App\Http\Controllers\Admin\LanguageController::class, 'import'])->name('languages.import');
    Route::post('languages/{language}/auto-translate', [\App\Http\Controllers\Admin\LanguageController::class, 'autoTranslate'])->name('languages.auto-translate');
    Route::delete('languages/{language}',      [\App\Http\Controllers\Admin\LanguageController::class, 'destroy'])->name('languages.destroy');

    // Settings — hub + per-group editors. Currency is the first group;
    // taxes / receipts / stores / users land here as they ship.
    Route::get('settings',          [\App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('settings.index');
    Route::get('settings/company',  [\App\Http\Controllers\Admin\CompanyProfileController::class, 'edit'])->name('settings.company.edit');
    Route::patch('settings/company',[\App\Http\Controllers\Admin\CompanyProfileController::class, 'update'])->name('settings.company.update');
    Route::get('settings/payment-gateways',   [\App\Http\Controllers\Admin\PaymentGatewaysSettingsController::class, 'edit'])->name('settings.payment-gateways.edit');
    Route::patch('settings/payment-gateways', [\App\Http\Controllers\Admin\PaymentGatewaysSettingsController::class, 'update'])->name('settings.payment-gateways.update');

    // Manual payment methods (Cash, Card, UPI, Bank Transfer, Cheque):
    // list page + per-row toggle endpoint.
    Route::get('settings/payment-methods',                       [\App\Http\Controllers\Admin\PaymentMethodsSettingsController::class, 'edit'])->name('settings.payment-methods.edit');
    Route::patch('settings/payment-methods/{method}/toggle',     [\App\Http\Controllers\Admin\PaymentMethodsSettingsController::class, 'toggle'])->name('settings.payment-methods.toggle');
    Route::patch('settings/payment-methods/{method}/toggle-drawer', [\App\Http\Controllers\Admin\PaymentMethodsSettingsController::class, 'toggleDrawer'])->name('settings.payment-methods.toggle-drawer');
    Route::patch('settings/payment-methods/{method}/toggle-reference', [\App\Http\Controllers\Admin\PaymentMethodsSettingsController::class, 'toggleReference'])->name('settings.payment-methods.toggle-reference');
    Route::patch('settings/payment-methods/{method}/upi',        [\App\Http\Controllers\Admin\PaymentMethodsSettingsController::class, 'updateUpi'])->name('settings.payment-methods.upi');
    Route::get('settings/branding', [\App\Http\Controllers\Admin\BrandingSettingsController::class, 'edit'])->name('settings.branding.edit');
    Route::patch('settings/branding',[\App\Http\Controllers\Admin\BrandingSettingsController::class, 'update'])->name('settings.branding.update');
    Route::get('settings/legal-pages',   [\App\Http\Controllers\Admin\LegalPagesSettingsController::class, 'edit'])->name('settings.legal-pages.edit');
    Route::patch('settings/legal-pages', [\App\Http\Controllers\Admin\LegalPagesSettingsController::class, 'update'])->name('settings.legal-pages.update');
    Route::get('settings/regional', [\App\Http\Controllers\Admin\RegionalSettingsController::class, 'edit'])->name('settings.regional.edit');
    Route::patch('settings/regional',[\App\Http\Controllers\Admin\RegionalSettingsController::class, 'update'])->name('settings.regional.update');
    Route::get('settings/currency', [\App\Http\Controllers\Admin\CurrencySettingsController::class, 'edit'])->name('settings.currency.edit');
    Route::patch('settings/currency',[\App\Http\Controllers\Admin\CurrencySettingsController::class, 'update'])->name('settings.currency.update');

    // Settings → Tax (Slice 1 — components + groups CRUD). Resolver +
    // exemptions + reverse-charge + country seeders land in later slices.
    Route::get('settings/tax',                                                [\App\Http\Controllers\Admin\TaxSettingsController::class, 'index'])->name('settings.tax.index');
    Route::get('settings/tax/components/export',                             [\App\Http\Controllers\Admin\TaxComponentController::class, 'export'])->name('settings.tax.components.export');
    Route::get('settings/tax/components/list',                               [\App\Http\Controllers\Admin\TaxComponentController::class, 'list'])->name('settings.tax.components.list');
    Route::get('settings/tax/components',                                     [\App\Http\Controllers\Admin\TaxComponentController::class, 'index'])->name('settings.tax.components.index');
    Route::post('settings/tax/components',                                    [\App\Http\Controllers\Admin\TaxComponentController::class, 'store'])->name('settings.tax.components.store');
    Route::patch('settings/tax/components/{taxComponent}',                    [\App\Http\Controllers\Admin\TaxComponentController::class, 'update'])->name('settings.tax.components.update');
    Route::delete('settings/tax/components/{taxComponent}',                   [\App\Http\Controllers\Admin\TaxComponentController::class, 'destroy'])->name('settings.tax.components.destroy');
    Route::get('settings/tax/components/{taxComponent}/deactivate-check',    [\App\Http\Controllers\Admin\TaxComponentController::class, 'deactivateCheck'])->name('settings.tax.components.deactivate-check');
    Route::get('settings/tax/groups/export',                                 [\App\Http\Controllers\Admin\TaxGroupController::class, 'export'])->name('settings.tax.groups.export');
    Route::get('settings/tax/groups',                                         [\App\Http\Controllers\Admin\TaxGroupController::class, 'index'])->name('settings.tax.groups.index');
    Route::get('settings/tax/groups/search-replacement',                      [\App\Http\Controllers\Admin\TaxGroupController::class, 'searchForReplacement'])->name('settings.tax.groups.search-replacement');
    Route::get('settings/tax/groups/{taxGroup}/delete-info',                  [\App\Http\Controllers\Admin\TaxGroupController::class, 'deleteInfo'])->name('settings.tax.groups.delete-info');
    Route::get('settings/tax/groups/{taxGroup}/deactivate-check',             [\App\Http\Controllers\Admin\TaxGroupController::class, 'deactivateCheck'])->name('settings.tax.groups.deactivate-check');
    Route::post('settings/tax/groups',                                        [\App\Http\Controllers\Admin\TaxGroupController::class, 'store'])->name('settings.tax.groups.store');
    Route::patch('settings/tax/groups/{taxGroup}',                            [\App\Http\Controllers\Admin\TaxGroupController::class, 'update'])->name('settings.tax.groups.update');
    Route::delete('settings/tax/groups/{taxGroup}',                           [\App\Http\Controllers\Admin\TaxGroupController::class, 'destroy'])->name('settings.tax.groups.destroy');

    // Tax Classifications — literal paths must precede any future wildcards.
    Route::get('settings/tax/classifications/export',                             [\App\Http\Controllers\Admin\TaxClassificationController::class, 'export'])->name('settings.tax.classifications.export');
    Route::get('settings/tax/classifications',                                    [\App\Http\Controllers\Admin\TaxClassificationController::class, 'index'])->name('settings.tax.classifications.index');
    Route::post('settings/tax/classifications',                                   [\App\Http\Controllers\Admin\TaxClassificationController::class, 'store'])->name('settings.tax.classifications.store');
    Route::patch('settings/tax/classifications/{taxClassification}',              [\App\Http\Controllers\Admin\TaxClassificationController::class, 'update'])->name('settings.tax.classifications.update');
    Route::delete('settings/tax/classifications/{taxClassification}',             [\App\Http\Controllers\Admin\TaxClassificationController::class, 'destroy'])->name('settings.tax.classifications.destroy');
    Route::get('settings/tax/classifications/{taxClassification}/deactivate-check', [\App\Http\Controllers\Admin\TaxClassificationController::class, 'deactivateCheck'])->name('settings.tax.classifications.deactivate-check');

    Route::get('settings/receipt',  [\App\Http\Controllers\Admin\ReceiptSettingsController::class, 'edit'])->name('settings.receipt.edit');
    Route::patch('settings/receipt',[\App\Http\Controllers\Admin\ReceiptSettingsController::class, 'update'])->name('settings.receipt.update');

    // Hardware diagnostics — peripheral status board + test actions.
    Route::get ('settings/hardware',            [\App\Http\Controllers\Admin\HardwareController::class, 'index'])->name('settings.hardware');
    Route::post('settings/hardware/test-print', [\App\Http\Controllers\Admin\HardwareController::class, 'testPrint'])->name('settings.hardware.test-print');

    // System Health — read-only diagnostics board (config, DB, storage, jobs, PHP).
    Route::get('settings/system-health', [\App\Http\Controllers\Admin\SystemHealthController::class, 'index'])->name('settings.system-health');
    Route::post('settings/system-health/clear-sample-data', [\App\Http\Controllers\Admin\SystemHealthController::class, 'clearSampleData'])->name('settings.system-health.clear-sample-data');
    Route::get('settings/cashier',  [\App\Http\Controllers\Admin\CashierSettingsController::class, 'edit'])->name('settings.cashier.edit');
    Route::patch('settings/cashier',[\App\Http\Controllers\Admin\CashierSettingsController::class, 'update'])->name('settings.cashier.update');

    // Weighing-scale barcode template (Settings → Scale).
    Route::get('settings/scale',   [\App\Http\Controllers\Admin\ScaleSettingsController::class, 'edit'])->name('settings.scale.edit');
    Route::patch('settings/scale', [\App\Http\Controllers\Admin\ScaleSettingsController::class, 'update'])->name('settings.scale.update');

    // License status (Settings → License) — view + on-demand re-check.
    Route::get('settings/license', [\App\Http\Controllers\Admin\LicenseController::class, 'index'])->name('settings.license.index');
    Route::post('settings/license/recheck', [\App\Http\Controllers\Admin\LicenseController::class, 'recheck'])->name('settings.license.recheck');

    // Pricing settings — auto-apply markup on purchase receive. Single
    // toggle today; more pricing knobs (rounding rules, default markup)
    // can land here later without breaking the URL surface.
    Route::get('settings/pricing',  [\App\Http\Controllers\Admin\PricingSettingsController::class, 'edit'])->name('settings.pricing.edit');
    Route::patch('settings/pricing',[\App\Http\Controllers\Admin\PricingSettingsController::class, 'update'])->name('settings.pricing.update');
    Route::get('settings/numbering',  [\App\Http\Controllers\Admin\NumberFormatController::class, 'edit'])->name('settings.numbering.edit');
    Route::patch('settings/numbering',[\App\Http\Controllers\Admin\NumberFormatController::class, 'update'])->name('settings.numbering.update');
    Route::get('settings/email',         [\App\Http\Controllers\Admin\EmailSettingsController::class, 'edit'])->name('settings.email.edit');
    Route::patch('settings/email',       [\App\Http\Controllers\Admin\EmailSettingsController::class, 'update'])->name('settings.email.update');
    Route::post('settings/email/test',   [\App\Http\Controllers\Admin\EmailSettingsController::class, 'sendTest'])->name('settings.email.test');
    Route::get('settings/backup',                       [\App\Http\Controllers\Admin\BackupSettingsController::class, 'edit'])->name('settings.backup.edit');
    Route::patch('settings/backup',                     [\App\Http\Controllers\Admin\BackupSettingsController::class, 'update'])->name('settings.backup.update');
    Route::post('settings/backup/run',                  [\App\Http\Controllers\Admin\BackupSettingsController::class, 'runNow'])->name('settings.backup.run');
    Route::get('settings/backup/download/{filename}',   [\App\Http\Controllers\Admin\BackupSettingsController::class, 'download'])->name('settings.backup.download');
    Route::delete('settings/backup/{filename}',         [\App\Http\Controllers\Admin\BackupSettingsController::class, 'destroy'])->name('settings.backup.destroy');

    // Restore wizard — choose a backup → review → confirm → restore. Gated by
    // `backup.restore` in the controller (super-admin / Admin only by default).
    Route::get('settings/restore',           [\App\Http\Controllers\Admin\RestoreController::class, 'index'])->name('settings.restore.index');
    Route::post('settings/restore/validate', [\App\Http\Controllers\Admin\RestoreController::class, 'validateSource'])->name('settings.restore.validate');
    Route::post('settings/restore',          [\App\Http\Controllers\Admin\RestoreController::class, 'store'])->name('settings.restore.store');

    // Updates — read-only feed check + channel/auto-check prefs. Installing an
    // update is a separate `updater.run`-gated flow (later slice).
    Route::get('settings/updates',           [\App\Http\Controllers\Admin\UpdateController::class, 'index'])->name('settings.updates.index');
    Route::post('settings/updates/check',     [\App\Http\Controllers\Admin\UpdateController::class, 'check'])->name('settings.updates.check');
    Route::patch('settings/updates',          [\App\Http\Controllers\Admin\UpdateController::class, 'update'])->name('settings.updates.update');
    Route::get('settings/updates/preflight',  [\App\Http\Controllers\Admin\UpdateController::class, 'preflight'])->name('settings.updates.preflight');
    Route::post('settings/updates/install',   [\App\Http\Controllers\Admin\UpdateController::class, 'install'])->name('settings.updates.install');
    Route::post('settings/updates/skip',      [\App\Http\Controllers\Admin\UpdateController::class, 'skip'])->name('settings.updates.skip');
    Route::get('settings/updates/history',    [\App\Http\Controllers\Admin\UpdateController::class, 'history'])->name('settings.updates.history');
    Route::post('settings/updates/manual/upload',  [\App\Http\Controllers\Admin\UpdateController::class, 'manualUpload'])->name('settings.updates.manual.upload');
    Route::post('settings/updates/manual/chunk',   [\App\Http\Controllers\Admin\UpdateController::class, 'manualChunk'])->name('settings.updates.manual.chunk');
    Route::post('settings/updates/manual/install', [\App\Http\Controllers\Admin\UpdateController::class, 'manualInstall'])->name('settings.updates.manual.install');

    // Sales — admin index + detail. The cashier flow itself lives at /cashier
    // (separate URL, separate route group) so the topbar's "Open POS" link
    // goes somewhere recognisable.
    Route::get('sales',                  [\App\Http\Controllers\Admin\SaleController::class, 'index'])->name('sales.index');
    Route::get('sales/rows',             [\App\Http\Controllers\Admin\SaleController::class, 'rows'])->name('sales.rows');
    Route::get('sales/{sale}',           [\App\Http\Controllers\Admin\SaleController::class, 'show'])->name('sales.show');
    Route::get('sales/{sale}/receipt',   [\App\Http\Controllers\Admin\SaleController::class, 'receipt'])->name('sales.receipt');
    // Print pipeline — dual-format payload for the print bridge + outcome log.
    Route::get('sales/{sale}/print-payload', [\App\Http\Controllers\Admin\PrintController::class, 'salePayload'])->name('sales.print-payload');
    Route::get('sales/returns/{saleReturn}/print-payload', [\App\Http\Controllers\Admin\PrintController::class, 'refundPayload'])->name('sales.returns.print-payload');
    Route::get('sales/{sale}/print-payload/corrected-copy', [\App\Http\Controllers\Admin\PrintController::class, 'correctedCopyPayload'])->name('sales.corrected-copy-print-payload');
    Route::post('print-logs',                [\App\Http\Controllers\Admin\PrintController::class, 'log'])->name('print-logs.store');
    Route::get('sales/{sale}/refund',    [\App\Http\Controllers\Admin\SaleController::class, 'refundForm'])->name('sales.refund.form');
    Route::post('sales/{sale}/refund',   [\App\Http\Controllers\Admin\SaleController::class, 'refundStore'])->name('sales.refund.store');
    Route::post('sales/{sale}/payments', [\App\Http\Controllers\Admin\SaleController::class, 'recordPayment'])->name('sales.payments.store');
    Route::post('sales/{sale}/void',     [\App\Http\Controllers\Admin\SaleController::class, 'void'])->name('sales.void');
    Route::patch('sales/{sale}/payments/{payment}/method', [\App\Http\Controllers\Admin\SaleController::class, 'changePaymentMethod'])->name('sales.payments.change-method');
    Route::post('sales/returns/{saleReturn}/retry-reversal', [\App\Http\Controllers\Admin\SaleReturnGatewayRefundController::class, 'retry'])->name('sales.returns.retry-reversal');

    // Return reasons — picklist used by the refund form. Single-page
    // master-detail CRUD; mirrors adjustment-reasons.
    Route::get('return-reasons',                       [\App\Http\Controllers\Admin\ReturnReasonController::class, 'index'])->name('return-reasons.index');
    Route::post('return-reasons',                      [\App\Http\Controllers\Admin\ReturnReasonController::class, 'store'])->name('return-reasons.store');
    Route::patch('return-reasons/{returnReason}',      [\App\Http\Controllers\Admin\ReturnReasonController::class, 'update'])->name('return-reasons.update');
    Route::delete('return-reasons/{returnReason}',     [\App\Http\Controllers\Admin\ReturnReasonController::class, 'destroy'])->name('return-reasons.destroy');

    // Price rules — scheduled, time-boxed product/category discounts
    // ("20% off Shoes, Aug 10-12"), distinct from a cashier's manual
    // per-sale discount. Consumed by ResolveProductPrice.
    Route::get('pricing/rules',                        [\App\Http\Controllers\Admin\PriceRuleController::class, 'index'])->name('price-rules.index');
    Route::get('pricing/rules/search-products',        [\App\Http\Controllers\Admin\PriceRuleController::class, 'searchProducts'])->name('price-rules.search-products');
    Route::get('pricing/rules/create',                 [\App\Http\Controllers\Admin\PriceRuleController::class, 'create'])->name('price-rules.create');
    Route::post('pricing/rules',                       [\App\Http\Controllers\Admin\PriceRuleController::class, 'store'])->name('price-rules.store');
    Route::get('pricing/rules/{priceRule}/edit',       [\App\Http\Controllers\Admin\PriceRuleController::class, 'edit'])->name('price-rules.edit');
    Route::patch('pricing/rules/{priceRule}',          [\App\Http\Controllers\Admin\PriceRuleController::class, 'update'])->name('price-rules.update');
    Route::delete('pricing/rules/{priceRule}',         [\App\Http\Controllers\Admin\PriceRuleController::class, 'destroy'])->name('price-rules.destroy');

    // Receipt templates — full receipt layouts (block-based, reorderable),
    // as opposed to the single Company-wide fields on settings/receipt.
    Route::get('receipt-templates',                              [\App\Http\Controllers\Admin\ReceiptTemplateController::class, 'index'])->name('receipt-templates.index');
    Route::get('receipt-templates/create',                       [\App\Http\Controllers\Admin\ReceiptTemplateController::class, 'create'])->name('receipt-templates.create');
    Route::post('receipt-templates',                             [\App\Http\Controllers\Admin\ReceiptTemplateController::class, 'store'])->name('receipt-templates.store');
    Route::get('receipt-templates/{receiptTemplate}/edit',       [\App\Http\Controllers\Admin\ReceiptTemplateController::class, 'edit'])->name('receipt-templates.edit');
    Route::patch('receipt-templates/{receiptTemplate}',          [\App\Http\Controllers\Admin\ReceiptTemplateController::class, 'update'])->name('receipt-templates.update');
    Route::delete('receipt-templates/{receiptTemplate}',         [\App\Http\Controllers\Admin\ReceiptTemplateController::class, 'destroy'])->name('receipt-templates.destroy');
    Route::patch('receipt-templates/{receiptTemplate}/default',  [\App\Http\Controllers\Admin\ReceiptTemplateController::class, 'setDefault'])->name('receipt-templates.default');

    Route::get('receipt-templates/{receiptTemplate}/blocks',                [\App\Http\Controllers\Admin\ReceiptTemplateBlockController::class, 'edit'])->name('receipt-templates.blocks.edit');
    Route::get('receipt-templates/{receiptTemplate}/preview',               [\App\Http\Controllers\Admin\ReceiptTemplateBlockController::class, 'preview'])->name('receipt-templates.preview');
    Route::post('receipt-templates/{receiptTemplate}/blocks',               [\App\Http\Controllers\Admin\ReceiptTemplateBlockController::class, 'store'])->name('receipt-templates.blocks.store');
    Route::post('receipt-templates/{receiptTemplate}/blocks/reorder',       [\App\Http\Controllers\Admin\ReceiptTemplateBlockController::class, 'reorder'])->name('receipt-templates.blocks.reorder');
    Route::patch('receipt-templates/{receiptTemplate}/blocks/{block}',      [\App\Http\Controllers\Admin\ReceiptTemplateBlockController::class, 'update'])->name('receipt-templates.blocks.update');
    Route::delete('receipt-templates/{receiptTemplate}/blocks/{block}',     [\App\Http\Controllers\Admin\ReceiptTemplateBlockController::class, 'destroy'])->name('receipt-templates.blocks.destroy');

    // Canvas mode — free x/y positioned elements, AJAX-driven (drag/resize/
    // property-panel edits persist immediately, no full-page reload).
    Route::get('receipt-templates/{receiptTemplate}/canvas',                    [\App\Http\Controllers\Admin\ReceiptTemplateElementController::class, 'edit'])->name('receipt-templates.canvas.edit');
    Route::patch('receipt-templates/{receiptTemplate}/canvas/paper-size',        [\App\Http\Controllers\Admin\ReceiptTemplateElementController::class, 'updatePaperSize'])->name('receipt-templates.canvas.paper-size');
    Route::get('receipt-templates/{receiptTemplate}/canvas-preview',            [\App\Http\Controllers\Admin\ReceiptTemplateElementController::class, 'preview'])->name('receipt-templates.canvas.preview');
    Route::post('receipt-templates/{receiptTemplate}/elements',                 [\App\Http\Controllers\Admin\ReceiptTemplateElementController::class, 'store'])->name('receipt-templates.elements.store');
    Route::patch('receipt-templates/{receiptTemplate}/elements/{element}',      [\App\Http\Controllers\Admin\ReceiptTemplateElementController::class, 'update'])->name('receipt-templates.elements.update');
    Route::delete('receipt-templates/{receiptTemplate}/elements/{element}',     [\App\Http\Controllers\Admin\ReceiptTemplateElementController::class, 'destroy'])->name('receipt-templates.elements.destroy');

    // Shifts — open/close + Z-report. One open shift per (store, cashier);
    // sales completed during the shift auto-bind via sales.shift_id so the
    // Z-report can roll them up. Slice 1 — denomination helper, pay-in/out,
    // force-close, PDF/thermal Z-report land later.
    Route::get('shifts',                        [\App\Http\Controllers\Admin\ShiftController::class, 'index'])->name('shifts.index');
    Route::get('shifts/rows',                   [\App\Http\Controllers\Admin\ShiftController::class, 'rows'])->name('shifts.rows');
    Route::get('shifts/open',                   [\App\Http\Controllers\Admin\ShiftController::class, 'openForm'])->name('shifts.open.form');
    Route::post('shifts',                       [\App\Http\Controllers\Admin\ShiftController::class, 'store'])->name('shifts.store');
    Route::get('shifts/{shift}/x-report',       [\App\Http\Controllers\Admin\ShiftController::class, 'xReport'])->name('shifts.x-report');
    Route::get('shifts/{shift}/z-report/payload',[\App\Http\Controllers\Admin\ShiftController::class, 'zReportPayload'])->name('shifts.z-report.payload');
    Route::get('shifts/{shift}',                [\App\Http\Controllers\Admin\ShiftController::class, 'show'])->name('shifts.show');
    Route::get('shifts/{shift}/close',          [\App\Http\Controllers\Admin\ShiftController::class, 'closeForm'])->name('shifts.close.form');
    Route::post('shifts/{shift}/close',         [\App\Http\Controllers\Admin\ShiftController::class, 'close'])->name('shifts.close');

    // "Close Day" — the trading-day wrapper, distinct from closing one
    // employee's own shift above. See docs/features/cash-drawer-shifts.md.
    Route::get('shifts/day/{tradingDay}/close',         [\App\Http\Controllers\Admin\ShiftController::class, 'closeDayForm'])->name('shifts.day.close.form');
    Route::post('shifts/day/{tradingDay}/close',        [\App\Http\Controllers\Admin\ShiftController::class, 'closeDay'])->name('shifts.day.close');
    Route::get('shifts/day/{tradingDay}/report-payload',[\App\Http\Controllers\Admin\ShiftController::class, 'dayReportPayload'])->name('shifts.day.report-payload');

    // Cash drawer movements against an open shift. Single endpoint —
    // the `type` field in the body decides pay-in / pay-out / drawer-
    // open-no-sale; the request validator branches its rules + perm.
    Route::post('shifts/{shift}/cash-drawer',   [\App\Http\Controllers\Admin\ShiftController::class, 'recordCashDrawerEntry'])->name('shifts.cash-drawer.record');

    // Shift variance reasons — picklist used by the close-shift form.
    // Same inline master-detail CRUD as return-reasons / adjustment-reasons.
    Route::get('shift-variance-reasons',                              [\App\Http\Controllers\Admin\ShiftVarianceReasonController::class, 'index'])->name('shift-variance-reasons.index');
    Route::post('shift-variance-reasons',                             [\App\Http\Controllers\Admin\ShiftVarianceReasonController::class, 'store'])->name('shift-variance-reasons.store');
    Route::patch('shift-variance-reasons/{shiftVarianceReason}',      [\App\Http\Controllers\Admin\ShiftVarianceReasonController::class, 'update'])->name('shift-variance-reasons.update');
    Route::delete('shift-variance-reasons/{shiftVarianceReason}',     [\App\Http\Controllers\Admin\ShiftVarianceReasonController::class, 'destroy'])->name('shift-variance-reasons.destroy');
});

// Cashier surface. Same auth gate as the admin but no `admin/` URL prefix —
// `/cashier` is the operator-facing URL. The cashier UI itself reuses the
// admin layout in Slice 1; a dedicated cashier chrome lands when the rest
// of the cashier features (shift, held drawer, ⌘K) are wired up.
Route::middleware(['ensure.installed', 'auth', 'store.selected', 'set.locale'])->name('cashier.')->group(function () {
    Route::get('cashier',                  [\App\Http\Controllers\Admin\SaleController::class, 'cashier'])->name('index');

    // Customer-Facing Display (CFD) — the second screen turned toward the
    // shopper. Opened from the cashier as a second window; mirrors the cart
    // live over a same-machine BroadcastChannel (no server round-trip).
    // See docs/features/customer-display.md.
    Route::get('cashier/display',          [\App\Http\Controllers\Cashier\CustomerDisplayController::class, 'show'])->name('display');
    // Tier 2 (separate-device) relay: the cashier pushes its snapshot to a
    // per-terminal cache; the tablet polls the state endpoint. No Pusher.
    Route::post('cashier/display/push',            [\App\Http\Controllers\Cashier\CustomerDisplayController::class, 'push'])->name('display.push');
    Route::get('cashier/display/{terminal}/state', [\App\Http\Controllers\Cashier\CustomerDisplayController::class, 'state'])->name('display.state');

    // Offline-first Slice 1: catalog snapshot + heartbeat.
    Route::get('cashier/sync',             [\App\Http\Controllers\Admin\SaleController::class, 'sync'])->name('sync');
    Route::get('cashier/heartbeat',        [\App\Http\Controllers\Admin\SaleController::class, 'heartbeat'])->name('heartbeat');
    Route::get('cashier/search',           [\App\Http\Controllers\Admin\SaleController::class, 'search'])->name('search');
    Route::post('cashier/complete',        [\App\Http\Controllers\Admin\SaleController::class, 'complete'])->name('complete');

    // Open a shift WITHOUT leaving /cashier — the shift gate posts here
    // when the store enforces shifts (Slice A). Reuses the OpenShift action.
    Route::post('cashier/shift',           [\App\Http\Controllers\Cashier\ShiftController::class, 'open'])->name('shift.open');

    // Open today's trading day WITHOUT leaving /cashier — the "day" step
    // of the shift gate posts here when `stores.require_day_open` is on
    // and no day is open yet. Gated on `shifts.open_day` inside the
    // controller (403, not a route middleware, to keep the gate's own
    // canOpenDay flag as the single source of truth for who sees the button).
    Route::post('cashier/day/open',        [\App\Http\Controllers\Cashier\ShiftController::class, 'openDay'])->name('day.open');

    // Bind this workstation to a terminal from the cashier (Slice B).
    Route::post('cashier/terminal',        [\App\Http\Controllers\Cashier\TerminalController::class, 'select'])->name('terminal.select');

    // Manager approval for an over-threshold discount (Discounts Slice 2).
    Route::post('cashier/discount/approve',[\App\Http\Controllers\Cashier\DiscountApprovalController::class, 'approve'])->name('discount.approve');

    Route::get('cashier/batches',          [\App\Http\Controllers\Admin\SaleController::class, 'batches'])->name('batches');
    Route::get('cashier/customers/search', [\App\Http\Controllers\Admin\SaleController::class, 'customerSearch'])->name('customers.search');
    Route::post('cashier/customers',       [\App\Http\Controllers\Admin\SaleController::class, 'quickAddCustomer'])->name('customers.store');

    Route::post('cashier/hold',                 [\App\Http\Controllers\Admin\SaleController::class, 'holdCart'])->name('hold');
    Route::get('cashier/holds',                 [\App\Http\Controllers\Admin\SaleController::class, 'heldList'])->name('holds.list');
    Route::post('cashier/holds/{sale}/resume',  [\App\Http\Controllers\Admin\SaleController::class, 'resumeHeld'])->name('holds.resume');
    Route::delete('cashier/holds/{sale}',       [\App\Http\Controllers\Admin\SaleController::class, 'voidHeld'])->name('holds.void');

    Route::get('cashier/reprint-last',          [\App\Http\Controllers\Admin\SaleController::class, 'reprintLast'])->name('reprint-last');
    Route::get('cashier/recents',               [\App\Http\Controllers\Admin\SaleController::class, 'recentsList'])->name('recents');

    // Refund / return from the cashier surface. Reuses RecordSaleReturn
    // under the hood; UI lives in the cashier modal so the operator
    // doesn't have to leave /cashier to process a return at the counter.
    // Payment gateways (Slice 1 — Stripe). One controller dispatches
    // by `payment_methods.provider`; add Razorpay/Paystack/etc. by
    // implementing the gateway class + extending `gatewayFor()`.
    Route::post('cashier/gateways/start',  [\App\Http\Controllers\Cashier\GatewayController::class, 'start'])->name('gateways.start');
    Route::get('cashier/gateways/status',  [\App\Http\Controllers\Cashier\GatewayController::class, 'status'])->name('gateways.status');

    // POS payment sessions — chooser flow (cashier shows QR pointing
    // at /pay/pos/{uuid}; customer picks provider there). The cashier
    // creates a session row, polls status, and can cancel.
    Route::post('cashier/pos-sessions',                  [\App\Http\Controllers\Cashier\PaymentSessionController::class, 'create'])->name('pos-sessions.create');
    Route::get('cashier/pos-sessions/{uuid}/status',     [\App\Http\Controllers\Cashier\PaymentSessionController::class, 'status'])->name('pos-sessions.status');
    Route::post('cashier/pos-sessions/{uuid}/cancel',    [\App\Http\Controllers\Cashier\PaymentSessionController::class, 'cancel'])->name('pos-sessions.cancel');

    Route::get('cashier/refund/lookup',         [\App\Http\Controllers\Cashier\RefundController::class, 'lookup'])->name('refund.lookup');

    // "Blind" refund — scan a barcode and refund it with no invoice
    // lookup. Cashiers can't do this on their own permission (same as the
    // invoice-based refund below); both routes require a `refund_approval`
    // token from the PIN-based approval when the requester lacks
    // `sales.refund` directly.
    //
    // These two static POST routes MUST be registered before the
    // POST cashier/refund/{sale} wildcard below — Laravel matches POST
    // routes in registration order, so with the wildcard first, a POST to
    // /cashier/refund/approve or /cashier/refund/blind would match
    // {sale} first (treating "approve"/"blind" as the sale id), fail
    // route-model binding, and 404 before ever reaching these controllers.
    Route::post('cashier/refund/blind',         [\App\Http\Controllers\Cashier\RefundController::class, 'storeBlind'])->name('refund.store-blind');
    Route::post('cashier/refund/approve',       [\App\Http\Controllers\Cashier\RefundApprovalController::class, 'approve'])->name('refund.approve');

    Route::get('cashier/refund/{sale}',         [\App\Http\Controllers\Cashier\RefundController::class, 'show'])->name('refund.show');
    Route::post('cashier/refund/{sale}',        [\App\Http\Controllers\Cashier\RefundController::class, 'store'])->name('refund.store');
});

// Self-ordering kiosk surface. Same auth gate as the cashier — a kiosk is
// opened from an authenticated staff session and bound to a `type=kiosk`
// terminal, then locked down for the customer. It reuses the cashier's
// catalog sync (`/cashier/sync` → IndexedDB), so Slice 1 adds no new data
// endpoints. See docs/features/kiosk-self-ordering.md.
Route::middleware(['ensure.installed', 'auth', 'store.selected', 'set.locale'])->name('kiosk.')->group(function () {
    Route::get('kiosk',       [\App\Http\Controllers\Kiosk\KioskController::class, 'show'])->name('index');
    // Order-mode submission → a `placed` Sale in the staff queue (Slice 2).
    Route::post('kiosk/place', [\App\Http\Controllers\Kiosk\KioskController::class, 'place'])->name('place');
    // Checkout-mode: pay at the kiosk via the QR-chooser, then finalise the
    // sale with origin=kiosk (Slice 3). Status is polled on the shared
    // /cashier/pos-sessions/{uuid}/status endpoint.
    Route::post('kiosk/checkout/start',    [\App\Http\Controllers\Kiosk\KioskCheckoutController::class, 'start'])->name('checkout.start');
    Route::post('kiosk/checkout/complete', [\App\Http\Controllers\Kiosk\KioskCheckoutController::class, 'complete'])->name('checkout.complete');
    // Supervisor exit-kiosk-mode (skeleton in Slice 1: confirm → leave;
    // PIN hardening lands in Slice 5).
    Route::post('kiosk/exit', [\App\Http\Controllers\Kiosk\KioskController::class, 'exit'])->name('exit');
});

// Public webhook receivers — no auth, no CSRF (the provider signs the
// payload). Each provider gets its own route + controller because the
// signature header + raw-body handling differs.
Route::post('webhooks/stripe',       \App\Http\Controllers\Webhooks\StripeWebhookController::class)->name('webhooks.stripe');
Route::post('webhooks/razorpay',     \App\Http\Controllers\Webhooks\RazorpayWebhookController::class)->name('webhooks.razorpay');
Route::post('webhooks/paystack',     \App\Http\Controllers\Webhooks\PaystackWebhookController::class)->name('webhooks.paystack');
Route::post('webhooks/flutterwave',  \App\Http\Controllers\Webhooks\FlutterwaveWebhookController::class)->name('webhooks.flutterwave');
Route::post('webhooks/mercado_pago', \App\Http\Controllers\Webhooks\MercadoPagoWebhookController::class)->name('webhooks.mercado_pago');

// Customer-facing payment chooser — public route the cashier QR
// points at. Customer arrives, picks a gateway, pays, returns. No
// auth: the un-guessable session UUID is the token.
Route::get('pay/pos/{uuid}',          [\App\Http\Controllers\Pay\CustomerPayController::class, 'show'])->name('pay.pos.show');
Route::get('pay/pos/{uuid}/status',   [\App\Http\Controllers\Pay\CustomerPayController::class, 'status'])->name('pay.pos.status');
Route::post('pay/pos/{uuid}/select',  [\App\Http\Controllers\Pay\CustomerPayController::class, 'select'])->name('pay.pos.select');
Route::get('pay/pos/{uuid}/return',   [\App\Http\Controllers\Pay\CustomerPayController::class, 'return'])->name('pay.pos.return');

// Public, no-login receipt viewer — the CFD thank-you QR, WhatsApp, and
// email/SMS receipts all point here. The opaque 48-char token IS the auth;
// throttled to blunt token-guessing. See docs/features/whatsapp-receipts.md §5.
Route::get('r/{token}', [\App\Http\Controllers\PublicReceiptController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('receipt.public');

// Public, no-login price-check page — served from its own domain
// (pricing.infinityglobal.com.jo, see the nginx vhost) so a customer
// scanning with their phone camera never sees the POS/admin hostname.
// Root path on THAT domain only — Route::domain() means the pos./
// invoice. domains are completely unaffected, they don't match it.
Route::domain('pricing.infinityglobal.com.jo')->group(function () {
    Route::get('/',       [\App\Http\Controllers\Pricing\PriceCheckController::class, 'show'])->name('pricing.show');
    Route::get('lookup',  [\App\Http\Controllers\Pricing\PriceCheckController::class, 'lookup'])
        ->middleware('throttle:60,1')
        ->name('pricing.lookup');
});

// Dev-only migrate route. 404s in production (APP_DEBUG=false).
//   /migrate           — run pending migrations (safe to re-hit)
//   /migrate?fresh=1   — drop all tables and re-run everything (DESTRUCTIVE)
//
// After a `fresh` we ALSO clear caches and wipe file sessions. Without
// that the next request can hang on Windows: the stale session cookie
// points to a user_id that no longer exists, the file session handler
// holds a lock while the auth middleware tries to load that ghost user,
// and config/view/route caches may still reference the just-dropped
// schema. Clearing everything makes the next page load clean.
Route::get('/migrate', function (\Illuminate\Http\Request $request) {
    abort_unless(config('app.debug'), 404);

    @set_time_limit(0);
    @ini_set('memory_limit', '512M');

    // Release the session file lock BEFORE the long-running command so
    // other browser tabs aren't blocked while migrations run.
    if ($request->hasSession()) {
        $request->session()->save();
    }

    $isFresh = $request->boolean('fresh');
    $command = $isFresh ? 'migrate:fresh' : 'migrate';
    Artisan::call($command, ['--force' => true]);
    $output = Artisan::output();

    // Make sure public/storage → storage/app/public exists so uploaded brand
    // logos / product images render. Idempotent, and it clears a broken link
    // left behind by a move or a restore before re-creating it.
    $link = app(\App\Actions\Installer\LinkPublicStorage::class)();
    $output .= "\nstorage:link — {$link['status']}".($link['message'] ? ": {$link['message']}" : '');

    if ($isFresh) {
        // 1. Drop every framework cache that may reference the old schema.
        Artisan::call('optimize:clear');
        $output .= "\n".Artisan::output();

        // 2. Wipe file sessions so a stale logged-in cookie doesn't try
        //    to resolve a deleted user on the next request.
        $sessionsPath = storage_path('framework/sessions');
        if (is_dir($sessionsPath)) {
            foreach (glob($sessionsPath.'/*') as $file) {
                if (is_file($file) && basename($file) !== '.gitignore') {
                    @unlink($file);
                }
            }
            $output .= "\nCleared file sessions.\n";
        }
    }

    // Reset OPcache so the web SAPI (Apache) picks up any code that shipped
    // with this migration — e.g. policy/permission changes. CLI artisan
    // bypasses OPcache, so without this the browser can keep running stale
    // bytecode (old policies, etc.) and appear to ignore the new logic.
    if (function_exists('opcache_reset')) {
        @opcache_reset();
        $output .= "\nOPcache reset.\n";
    }

    return '<pre>'.e($output).'</pre>';
});

// Dev-only: dump what THIS PHP SAPI (the one Apache is using) sees.
// Use this when CLI `php -m` and the installer disagree about loaded extensions.
Route::get('/phpinfo', function () {
    abort_unless(config('app.debug'), 404);

    return response('<pre>'.e(implode("\n", [
        'SAPI:          '.php_sapi_name(),
        'PHP version:   '.PHP_VERSION,
        'Loaded INI:    '.(php_ini_loaded_file() ?: '(none)'),
        'Scanned INIs:  '.(php_ini_scanned_files() ?: '(none)'),
        '',
        'zip extension loaded?      '.(extension_loaded('zip') ? 'YES' : 'NO'),
        'intl extension loaded?     '.(extension_loaded('intl') ? 'YES' : 'NO'),
        'fileinfo extension loaded? '.(extension_loaded('fileinfo') ? 'YES' : 'NO'),
        '',
        '--- All loaded extensions ---',
        implode("\n", get_loaded_extensions()),
    ])).'</pre>')->header('Content-Type', 'text/html');
});

// Dev-only seed route. 404s in production.
//   /seed              — run DatabaseSeeder (idempotent; safe to re-hit)
//   /seed?class=Foo    — run a specific seeder (e.g. /seed?class=PermissionsSeeder)
Route::get('/seed', function (\Illuminate\Http\Request $request) {
    abort_unless(config('app.debug'), 404);

    @set_time_limit(0);
    @ini_set('memory_limit', '512M');

    // Release the session lock before the long-running command so other
    // browser tabs can keep responding while the seeder runs.
    if ($request->hasSession()) {
        $request->session()->save();
    }

    $params = ['--force' => true];
    if ($class = $request->query('class')) {
        $params['--class'] = $class;
    }
    Artisan::call('db:seed', $params);
    $output = Artisan::output();

    // Reset OPcache so the web SAPI sees any code changes that shipped with
    // this seed (CLI artisan bypasses OPcache; Apache can otherwise keep
    // running stale bytecode).
    if (function_exists('opcache_reset')) {
        @opcache_reset();
        $output .= "\nOPcache reset.\n";
    }

    return '<pre>'.e($output).'</pre>';
});

// Dev-only: prove the gateway refund reversal without real gateway keys. Stamps
// the newest (or ?sale=ID) sale as gateway-paid, runs a real 1-unit refund with
// a fake gateway, and links you to the sale so the "Reversed at gateway" badge
// is visible. Add ?fail=1 to see the failed/retry path. 404s in production.
Route::get('/simulate-gateway-refund', function (\Illuminate\Http\Request $request) {
    abort_unless(config('app.debug'), 404);

    $params = [];
    if ($sale = $request->query('sale')) {
        $params['sale'] = $sale;
    }
    if ($request->boolean('fail')) {
        $params['--fail'] = true;
    }

    Artisan::call('pos:simulate-gateway-refund', $params);

    return '<pre>'.e(Artisan::output()).'</pre>';
});

// Dev-only: diagnose why a gateway refund reversal did / didn't happen.
// Dumps the sale's payment rows + the latest refund's bucketing and gateway
// reversal status. Defaults to the most recent refund; ?sale=ID targets one.
// 404s in production.
Route::get('/debug-refund', function (\Illuminate\Http\Request $request) {
    abort_unless(config('app.debug'), 404);

    $out = [];
    $hasCol = \Illuminate\Support\Facades\Schema::hasColumn('sale_returns', 'gateway_refund_status');
    $out[] = 'Migration applied (gateway_refund_status column): '.($hasCol ? 'YES' : 'NO  ← run /migrate first!');

    $saleId = $request->query('sale');
    $return = $saleId
        ? \App\Models\SaleReturn::where('sale_id', (int) $saleId)->latest('id')->first()
        : \App\Models\SaleReturn::latest('id')->first();

    if (! $return) {
        $out[] = "\nNo refunds recorded yet. Process a refund first, then reload this page.";

        return response('<pre>'.e(implode("\n", $out)).'</pre>');
    }

    // ?reverse=1 → run the gateway reversal on this refund now (dev convenience
    // for refunds recorded before the fix landed). Idempotent + never throws.
    if ($request->boolean('reverse')) {
        app(\App\Actions\Sales\ReverseGatewayCharge::class)($return->fresh());
        $return->refresh();
        $out[] = "\n>> Ran gateway reversal now → status=".var_export($return->gateway_refund_status, true);
    }

    $sale = $return->sale;
    $out[] = "\nSale #{$sale->id}   status={$sale->status}   grand_total={$sale->grand_total}";
    $out[] = '--- Payments captured on this sale ---';
    foreach (\App\Models\SalePayment::where('sale_id', $sale->id)->get() as $p) {
        $m = $p->paymentMethod;
        $out[] = sprintf(
            "  %s (type=%s, provider=%s)  amount=%s\n     gateway_provider=%s  gateway_payment_id=%s",
            $m?->name ?? '?', $m?->type ?? '?', $m?->provider ?? '?', $p->amount,
            var_export($p->gateway_provider, true), var_export($p->gateway_payment_id, true),
        );
    }

    $rm = \App\Models\PaymentMethod::find($return->refund_method_id);
    $out[] = "\n--- Latest refund {$return->number} ---";
    $out[] = '  refund_method='.($rm?->name ?? '(none)').'  (type='.($rm?->type ?? '-').', code='.($rm?->code ?? '-').')';
    $out[] = '  refunded_in_cash            = '.$return->refunded_in_cash;
    $out[] = '  refunded_to_store_credit    = '.$return->refunded_to_store_credit;
    $out[] = '  refunded_to_original_method = '.$return->refunded_to_original_method;
    $out[] = '  gateway_refund_status  = '.var_export($return->gateway_refund_status, true);
    $out[] = '  gateway_refund_attempts= '.$return->gateway_refund_attempts;
    $out[] = '  gateway_refund_error   = '.var_export($return->gateway_refund_error, true);

    $out[] = "\n=== DIAGNOSIS ===";
    $toOriginal = bccomp((string) ($return->refunded_to_original_method ?? '0'), '0', 4) > 0;
    $hasGatewayPayment = \App\Models\SalePayment::where('sale_id', $sale->id)->whereNotNull('gateway_provider')
        ->whereIn('gateway_provider', ['stripe', 'razorpay', 'paystack', 'flutterwave', 'mercado_pago'])->exists();

    if (! $hasCol) {
        $out[] = 'The migration has not run — the feature is inert. Hit /migrate, then refund again.';
    } elseif (! $toOriginal) {
        $out[] = 'This refund did NOT go back to the original method — the amount is in cash or store';
        $out[] = 'credit, so no gateway call happens. Re-do the refund and pick the STRIPE / CARD method';
        $out[] = 'as the "refund to" method.';
    } elseif (! $hasGatewayPayment) {
        $out[] = 'The sale has NO payment tagged with a gateway provider (gateway_provider is null). It was';
        $out[] = 'not paid through the Stripe gateway flow, so there is no Stripe charge to reverse. Pay a';
        $out[] = 'fresh sale using the Stripe QR tile on the cashier, then refund THAT to the card method.';
    } elseif ($return->gateway_refund_status === 'failed') {
        $out[] = 'The gateway WAS called but Stripe rejected it — see gateway_refund_error above.';
    } elseif ($return->gateway_refund_status === 'succeeded') {
        $out[] = 'Reversal succeeded — the refund should be in your Stripe dashboard.';
    } else {
        $out[] = 'Unexpected state — status is null despite an original-method gateway refund. Share this dump.';
    }

    return response('<pre>'.e(implode("\n", $out)).'</pre>');
});

// Demo baseline routes — only exist on the public demo (POS_DEMO_MODE=true), so
// they 404 on real customer installs. Web equivalents of the pos:demo-snapshot
// / pos:demo-reset artisan commands, for hosts without SSH.
//   /demo-snapshot  — capture the CURRENT database as THE baseline every nightly
//                     reset restores. Hit this ONCE, right after seeding the
//                     demo with the data you want visitors to always start from.
//   /demo-reset     — restore that baseline right now (same thing the 2am cron
//                     does); handy to test the reset or clear junk on demand.
Route::get('/demo-snapshot', function () {
    abort_unless(pos_is_demo(), 404);

    @set_time_limit(0);
    @ini_set('memory_limit', '512M');

    $log = app(\App\Actions\Demo\ResetDemoData::class)->capture();
    $abs = $log->file_path
        ? \Illuminate\Support\Facades\Storage::disk($log->destination ?: 'local')->path($log->file_path)
        : null;

    $lines = [
        'status:    '.$log->status,
        'file_path: '.($log->file_path ?? '(none)'),
        'abs_path:  '.($abs ?? '(none)'),
        'exists:    '.($abs && is_file($abs) ? 'YES' : 'NO'),
        'size:      '.number_format(($log->file_size_bytes ?? 0) / 1024, 1).' KB',
    ];
    if ($log->status !== 'success') {
        $lines[] = 'error:     '.($log->error_message ?? 'unknown');
    } else {
        $lines[] = '';
        $lines[] = 'Baseline captured. The nightly reset (and /demo-reset) will restore this state.';
    }

    return '<pre>'.e(implode("\n", $lines)).'</pre>';
});

Route::get('/demo-reset', function () {
    abort_unless(pos_is_demo(), 404);

    @set_time_limit(0);
    @ini_set('memory_limit', '512M');

    $reset    = app(\App\Actions\Demo\ResetDemoData::class);
    $baseline = $reset->baseline();
    if ($baseline === null) {
        return '<pre>No baseline captured yet. Visit /demo-snapshot first.</pre>';
    }

    $abs = \Illuminate\Support\Facades\Storage::disk($baseline->destination ?: 'local')->path($baseline->file_path);
    if (! is_file($abs)) {
        return '<pre>'.e(
            "Baseline file is MISSING on disk:\n  {$abs}\n\n".
            "The backup_logs row exists but the .zip is gone.\n".
            "Fix: visit /demo-snapshot to capture a fresh baseline, then retry /demo-reset."
        ).'</pre>';
    }

    try {
        $log = $reset();
    } catch (\Throwable $e) {
        return '<pre>'.e('Demo reset FAILED: '.$e->getMessage()).'</pre>';
    }

    return '<pre>'.e($log && $log->status === 'success'
        ? 'Demo reset complete — database restored to baseline.'
        : 'Demo reset FAILED: '.($log?->error_message ?? 'unknown error')
    ).'</pre>';
});
