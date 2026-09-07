<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;

/**
 * Controller helper — return JSON for AJAX clients (the system-wide
 * axios path via `data-ajax-form` / `lib/http.js`), classic redirects
 * for everything else (tests, legacy entry points, no-JS fallback).
 *
 * Usage in a controller:
 *
 *   public function store(Request $request, FooAction $create): JsonResponse|RedirectResponse
 *   {
 *       $foo = $create(...);
 *       return $this->jsonOrRedirect(
 *           $request,
 *           __('foos.flash.created', ['name' => $foo->name]),
 *           route('admin.foos.index'),
 *       );
 *   }
 *
 *   // Action-thrown business exception:
 *   } catch (FooHasBalance $e) {
 *       return $this->jsonOrError($request, $e->getMessage(), route('admin.foos.show', $foo));
 *   }
 *
 * The trait intentionally keeps the JSON envelope tiny — `message` for
 * the toast, `redirect` for follow-up navigation, `data` for anything
 * page-specific. Form validation errors come back automatically as
 * Laravel's standard 422 envelope (FormRequest); no manual shaping
 * needed there.
 */
trait RespondsJsonOrRedirect
{
    /**
     * Success response. Tests + non-AJAX clients get a flash redirect;
     * AJAX clients get JSON the `data-ajax-form` handler unwraps.
     *
     * @param array<string, mixed> $extra Optional extra data on the JSON envelope.
     */
    protected function jsonOrRedirect(
        Request $request,
        string $message,
        string $redirectUrl,
        array $extra = [],
    ): JsonResponse|RedirectResponse {
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json(array_merge([
                'message'  => $message,
                'redirect' => $redirectUrl,
            ], $extra));
        }
        return redirect($redirectUrl)->with('success', $message);
    }

    /**
     * Action-thrown error response. AJAX clients get a 422 with a
     * synthetic single-line errors envelope so the form-validators
     * surface it as a toast. Tests + non-AJAX clients get
     * `back()->withInput()->with('error')`.
     */
    protected function jsonOrError(
        Request $request,
        string $message,
        ?string $redirectUrl = null,
    ): JsonResponse|RedirectResponse {
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'errors'  => ['_action' => [$message]],
            ], 422);
        }

        $redirect = $redirectUrl
            ? redirect($redirectUrl)
            : back()->withInput();
        return $redirect->with('error', $message);
    }

    /**
     * "Disabled in demo mode" response for a blocked action (delete,
     * download, install, …). Unlike {@see jsonOrError}'s transient 422
     * toast, this flashes the message and sends the client to `$redirectUrl`
     * so it renders as an error toast on the next page load — the same
     * reliable, visible path the backup-download block uses.
     *
     * For the AJAX delete flow, `submitForm` follows the JSON `redirect`
     * via `window.location`, so the flashed message survives to that
     * navigation and paints there.
     */
    protected function demoBlocked(
        Request $request,
        string $redirectUrl,
    ): JsonResponse|RedirectResponse {
        $message = __('settings.demo.action_locked');
        session()->flash('error', $message);

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json(['redirect' => $redirectUrl]);
        }

        return redirect($redirectUrl);
    }
}
