<?php

use App\Http\Middleware\ApplyCompanySettings;
use App\Http\Middleware\BlockDemoImportExport;
use App\Http\Middleware\EnsureAppKey;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\InstallerErrorHandler;
use App\Http\Middleware\EnsureStoreSelected;
use App\Http\Middleware\PreventResponseCache;
use App\Http\Middleware\RestrictDuringInstall;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Installer wizard. `RestrictDuringInstall` (on the group) 404s these
            // once the install lock is written, so on an installed site they're
            // invisible. For dev, state is bootstrapped via
            //   /seed?class=DevBootstrapSeeder
            // which calls the same Actions the wizard would and writes the lock.
            Route::middleware('web')
                ->group(base_path('routes/installer.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Every domain (pos, pos2, pricing, mecca) sits behind Cloudflare —
        // without this, Request::ip() resolves to CLOUDFLARE'S edge IP for
        // every visitor instead of the real client, so anything keyed by IP
        // (rate limiting, most visibly `throttle:60,1` on the public
        // pricing.infinityglobal.com.jo price-check page) collapses every
        // visitor into ONE shared bucket — a handful of people scanning
        // barcodes at once exhausts it and everyone starts getting 429'd,
        // which looks exactly like "the site goes down after ~6 users."
        // Ranges from https://www.cloudflare.com/ips/ (fetched 2026-08-28) —
        // trusting these specific ranges rather than '*' so a request that
        // bypasses Cloudflare and hits the origin directly can't spoof
        // X-Forwarded-For.
        $middleware->trustProxies(at: [
            '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
            '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
            '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
            '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
            '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
            '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
        ]);

        $middleware->alias([
            'restrict.during.install' => RestrictDuringInstall::class,
            'ensure.installed'        => EnsureInstalled::class,
            'store.selected'          => EnsureStoreSelected::class,
            'set.locale'              => SetLocale::class,
            'no.cache'                => PreventResponseCache::class,
            'installer.errors'        => InstallerErrorHandler::class,
            'feature.gate'            => EnsureFeatureEnabled::class,
        ]);

        // Guarantee an APP_KEY exists before cookie encryption / CSRF run — a
        // fresh upload ships none and the installer's first screen needs one.
        // Prepended to the GLOBAL stack so it executes ahead of the web group's
        // EncryptCookies. No-op (one filled() check) once a key is set.
        $middleware->prepend(EnsureAppKey::class);
        // Apply company-configured settings (name → config('app.name'), …)
        // to every web request so views, mailers, and exceptions see them.
        $middleware->web(prepend: [ApplyCompanySettings::class]);

        // Demo installs: block bulk import/export on any *.export / *.import*
        // route (no-op on real installs). Appended so it runs after auth.
        $middleware->web(append: [BlockDemoImportExport::class]);

        // Payment gateway webhook routes — Stripe (and future providers)
        // sign the payload at their own signing-secret HMAC, so they
        // can't carry a Laravel CSRF token. The signature verifier in
        // the controller is the auth.
        $middleware->validateCsrfTokens(except: [
            'webhooks/stripe',
            'webhooks/razorpay',
            'webhooks/paystack',
            'webhooks/flutterwave',
            'webhooks/mercado_pago',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A permission failure (policy `authorize()` or `abort(403)`) on a
        // normal browser navigation should feel friendly: bounce the user
        // back to the dashboard with a toast rather than dumping the bare
        // "403 · This action is unauthorized" screen. AJAX/API callers still
        // get the raw 403 JSON so the axios layer can react to it.
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            $is403 = $e instanceof \Illuminate\Auth\Access\AuthorizationException
                || ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                    && $e->getStatusCode() === 403);

            // Only reshape real browser PAGE navigations (GET) for signed-in
            // users. JSON/AJAX callers, forbidden form actions (POST/PATCH/
            // DELETE — usually AJAX anyway), and guests fall through to the
            // defaults (guests get the login redirect from the auth layer).
            if (! $is403
                || ! $request->isMethod('GET')
                || $request->expectsJson()
                || ! $request->user()) {
                return null;
            }

            return redirect()
                ->route('admin.dashboard')
                ->with('error', __('errors.unauthorized'));
        });
    })->create();
