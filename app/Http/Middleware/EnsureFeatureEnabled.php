<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Plan-gated feature check (SaaS conversion plan, Phase 2). Applied per
 * route group as `feature.gate:<key>`, e.g.:
 *
 *   Route::middleware('feature.gate:advanced_reporting')->group(...);
 *
 * Backed by {@see feature_enabled()}, which defaults an unknown key to
 * enabled — so a self-hosted / non-SaaS install (no entitlements ever
 * persisted) is never restricted by this middleware, only a SaaS instance
 * whose license server actively turned the feature off.
 */
class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $key): Response
    {
        abort_unless(feature_enabled($key), 403, __('users.errors.feature_disabled'));

        return $next($request);
    }
}
