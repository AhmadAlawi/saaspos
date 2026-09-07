<?php

namespace App\Http\Controllers\Installer\Concerns;

use App\Support\InstallerDiagnostics;
use App\Support\InstallState;
use Illuminate\Http\Response;
use Illuminate\Support\ViewErrorBag;

/**
 * Renders the installer's friendly error screen (docs §10) from a controller
 * that caught a hard failure itself — e.g. a failed migration — so it can
 * point "Retry this step" at the right place. Unexpected/uncaught crashes are
 * handled by the InstallerErrorHandler middleware instead.
 */
trait RendersInstallerError
{
    protected function installerError(string $message, string $retryRoute, ?string $stepLabel = null): Response
    {
        return response()->view('installer.error', [
            'currentStep'  => null,
            'stepLabel'    => $stepLabel,
            'message'      => $message,
            'diagnostic'   => InstallerDiagnostics::toText($stepLabel),
            'retryUrl'     => route($retryRoute),
            'canStartOver' => ! InstallState::isLocked(),
            'errors'       => new ViewErrorBag(),
        ], 500);
    }
}
