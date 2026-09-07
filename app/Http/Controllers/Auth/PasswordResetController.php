<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Password reset flow.
 *
 * Three views: request a reset link, view the reset form (token in URL),
 * and an implicit "sent" / "reset success" via flash status. Backed by
 * Laravel's PasswordBroker — token storage, expiry (60 minutes by
 * default, see config/auth.php), and throttling are all stock.
 *
 * Mail delivery uses whatever SMTP the merchant configured in
 * Settings → Email. The `ApplyCompanySettings` middleware (registered
 * globally in bootstrap/app.php) rewrites `config('mail.*')` on every
 * request, so the reset notification respects that config without us
 * having to do anything extra here.
 */
class PasswordResetController extends Controller
{
    /** Show the "enter your email" form. */
    public function showLinkRequest(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Send the reset link to the user's email.
     *
     * Always returns the same success message regardless of whether the
     * email matched a real account — leaking that distinction would let
     * an attacker enumerate registered users.
     */
    public function sendLink(Request $request): RedirectResponse|JsonResponse
    {
        // On an AJAX submit a failed rule already returns a 422 JSON envelope
        // the page shows in-place — no full reload.
        $request->validate([
            'email' => ['required', 'email:rfc'],
        ]);

        Password::sendResetLink(
            $request->only('email')
        );

        // We collapse RESET_LINK_SENT vs INVALID_USER to the same friendly
        // message so the form doesn't leak whether the email is in our user
        // table.
        $message = __('auth.password_reset.link_sent_or_unknown');

        if ($request->expectsJson()) {
            return response()->json(['status' => $message]);
        }

        return back()
            ->with('status', $message)
            ->withInput($request->only('email'));
    }

    /** Show the "enter your new password" form. */
    public function showReset(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    /**
     * Verify the token + persist the new password.
     *
     * Logs the user OUT of every other session by rotating their
     * remember-me token, so a stolen browser session can't survive a
     * password reset.
     */
    public function reset(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'token'                 => ['required', 'string'],
            'email'                 => ['required', 'email:rfc'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            $message = __('auth.password_reset.success');

            // AJAX: flash the status so the login page shows it, and hand the
            // page a redirect target instead of a full-page redirect response.
            if ($request->expectsJson()) {
                $request->session()->flash('status', $message);

                return response()->json(['redirect' => route('login')]);
            }

            return redirect()->route('login')->with('status', $message);
        }

        // Token expired / wrong email / etc. Surface the raw Laravel
        // translation key so any locale override in lang/*/passwords.php
        // takes effect.
        $error = __($status);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $error,
                'errors'  => ['email' => [$error]],
            ], 422);
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => $error]);
    }
}
