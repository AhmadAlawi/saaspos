<?php

namespace App\Http\Middleware;

use App\Support\InstallerDiagnostics;
use App\Support\InstallState;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Catches an unexpected crash anywhere in the installer and renders the
 * friendly error screen with a copy-paste diagnostic dump instead of a raw
 * stack trace or blank 500 (docs/features/installer.md §10).
 *
 * Framework control-flow exceptions are re-thrown untouched so form
 * validation (back-with-errors), redirects, 404s (RestrictDuringInstall once
 * locked), and auth keep working normally.
 */
class InstallerErrorHandler
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (ValidationException | HttpResponseException | HttpExceptionInterface | AuthenticationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            $step = $this->stepLabel($request);

            return response()->view('installer.error', [
                'currentStep'  => null,
                'stepLabel'    => $step,
                'message'      => $e->getMessage() !== '' ? $e->getMessage() : __('installer.error.unknown'),
                'diagnostic'   => InstallerDiagnostics::toText($step),
                'retryUrl'     => url()->previous() ?: route('install.welcome'),
                'canStartOver' => ! InstallState::isLocked(),
                // Rendered outside the normal request flow (no session-shared
                // errors bag) — provide an empty one so the layout's
                // `$errors->any()` guard doesn't trip.
                'errors'       => new ViewErrorBag(),
            ], 500);
        }
    }

    /** A human label for the step that failed, from the matched route name. */
    private function stepLabel(Request $request): ?string
    {
        $name = $request->route()?->getName();

        return match (true) {
            $name === null                          => null,
            str_contains($name, 'requirements')     => __('installer.steps.requirements'),
            str_contains($name, 'license')          => __('installer.steps.license'),
            str_contains($name, 'database')         => __('installer.steps.database'),
            str_contains($name, 'admin')            => __('installer.steps.admin'),
            str_contains($name, 'demo'), str_contains($name, 'complete') => __('installer.steps.demo'),
            default                                 => null,
        };
    }
}
