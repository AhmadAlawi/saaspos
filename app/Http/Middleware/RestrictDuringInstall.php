<?php

namespace App\Http\Middleware;

use App\Support\InstallState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applied to installer routes. Once installation is locked, installer routes 404.
 */
class RestrictDuringInstall
{
    public function handle(Request $request, Closure $next): Response
    {
        if (InstallState::isLocked()) {
            abort(404);
        }

        return $next($request);
    }
}
