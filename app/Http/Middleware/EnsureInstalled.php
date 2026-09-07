<?php

namespace App\Http\Middleware;

use App\Support\InstallState;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applied to all non-installer routes. Blocks the app when the install lock file
 * is missing.
 *
 * Behaviour depends on whether the installer wizard is currently wired up:
 *   - Wizard enabled  → redirect to install.welcome (resumes the flow)
 *   - Wizard disabled → abort 503 with a hint to run the DevBootstrapSeeder
 *
 * The installer is currently DISABLED in bootstrap/app.php; see DevBootstrapSeeder.
 */
class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (InstallState::isLocked()) {
            return $next($request);
        }

        if (Route::has('install.welcome')) {
            return redirect()->route('install.welcome');
        }

        abort(503, 'System not yet bootstrapped. Visit /seed?class=DevBootstrapSeeder while APP_DEBUG=true to seed a dev company + admin user.');
    }
}
