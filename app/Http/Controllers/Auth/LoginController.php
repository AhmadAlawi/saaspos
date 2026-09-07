<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\PinLoginRequest;
use App\Services\Auth\ResolveUserByPin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Session-based authentication for the admin app.
 * MFA, password reset, super-admin enforcement, role-permission checks etc.
 * are deferred — see docs/features/auth-users.md for the full spec.
 */
class LoginController extends Controller
{
    public function show(): View|RedirectResponse
    {
        return Auth::check()
            ? redirect()->intended($this->defaultRedirect())
            : view('auth.login');
    }

    public function attempt(LoginRequest $request): RedirectResponse|JsonResponse
    {
        $request->ensureIsNotRateLimited();

        $credentials = $request->only('email', 'password');
        $remember    = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            $request->hitRateLimit();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('auth.failed'),
                    'errors'  => ['email' => [__('auth.failed')]],
                ], 422);
            }

            return back()
                ->withErrors(['email' => __('auth.failed')])
                ->withInput($request->only('email', 'remember'));
        }

        $request->clearRateLimit();
        $request->session()->regenerate();

        if ($request->expectsJson()) {
            $intended = session()->pull('url.intended', $this->defaultRedirect());
            return response()->json(['redirect' => $intended]);
        }

        return redirect()->intended($this->defaultRedirect());
    }

    /**
     * PIN login — the touchscreen-friendly "main" way in, same trust
     * level as the discount/refund-approval PIN: a bare 6-digit PIN is
     * the whole credential, no username step. Unlike those approval
     * flows (mid-sale, already authenticated, store-scoped via
     * {@see \App\Services\Sales\ResolveManagerByPin}), this runs pre-auth
     * with no store context yet, so {@see ResolveUserByPin} scans every
     * active PIN-holder system-wide instead.
     */
    public function attemptPin(PinLoginRequest $request, ResolveUserByPin $resolve): RedirectResponse|JsonResponse
    {
        $request->ensureIsNotRateLimited();

        $user = $resolve($request->string('pin')->value());

        if (! $user) {
            $request->hitRateLimit();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('auth.pin_invalid'),
                    'errors'  => ['pin' => [__('auth.pin_invalid')]],
                ], 422);
            }

            return back()->withErrors(['pin' => __('auth.pin_invalid')]);
        }

        $request->clearRateLimit();
        Auth::login($user);
        $request->session()->regenerate();

        if ($request->expectsJson()) {
            $intended = session()->pull('url.intended', $this->defaultRedirect());
            return response()->json(['redirect' => $intended]);
        }

        return redirect()->intended($this->defaultRedirect());
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    /**
     * Where a fresh login lands with no `intended()` URL to honour
     * (a deep-linked bookmark still wins over this). A pure till account —
     * can sell somewhere, has no back-office access anywhere — goes
     * straight to the POS screen instead of the admin dashboard, since
     * that's the only screen it can actually use. Checked across every
     * store the user belongs to (not `current_store_id()`), because no
     * store is selected yet at this point in the request.
     */
    private function defaultRedirect(): string
    {
        $user = Auth::user();

        $isCashierOnly = $user
            && $user->hasAnyPermissionAcrossStores('sales.create')
            && ! $user->hasAnyPermissionAcrossStores('settings.view');

        return $isCashierOnly ? '/cashier' : '/admin';
    }
}
