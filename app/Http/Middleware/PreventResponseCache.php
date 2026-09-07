<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tell browsers + proxies never to cache the response.
 *
 * Used on routes that emit one-time-use HTML referencing Vite asset hashes
 * (e.g. the installer). Combined with Vite's content-hashed filenames this
 * guarantees the browser cannot serve a stale HTML page that points at an
 * asset hash that no longer exists.
 */
class PreventResponseCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
