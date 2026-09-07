<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * On the public demo, block every bulk import and export.
 *
 * Imports mutate data (and the demo resets nightly, but a mid-day import
 * would still corrupt the shared demo for everyone); exports would let a
 * visitor pull the whole customer/product/sales dataset off the demo. This
 * one middleware covers all ~40 `*.export` / `*.import*` routes app-wide —
 * and any future ones — by matching the route name, so individual
 * controllers don't each need a guard.
 *
 * No-op on real installs (pos_is_demo() === false).
 */
class BlockDemoImportExport
{
    public function handle(Request $request, Closure $next): Response
    {
        if (pos_is_demo() && $this->isImportOrExport($request)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('settings.demo.action_locked')], 422);
            }

            return back()->with('error', __('settings.demo.action_locked'));
        }

        return $next($request);
    }

    private function isImportOrExport(Request $request): bool
    {
        $name = (string) ($request->route()?->getName() ?? '');

        return $name !== '' && (
            str_ends_with($name, '.export')
            || str_contains($name, '.import')
        );
    }
}
