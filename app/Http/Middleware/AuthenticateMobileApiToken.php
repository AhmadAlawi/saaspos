<?php

namespace App\Http\Middleware;

use App\Models\MobileApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token auth for the mobile API (routes/mobile-api.php). Separate
 * from the app's session-based `web` guard by design — resolves the user
 * AND the store the token is pinned to (see MobileApiToken doc comment),
 * attaching both to the request for controllers to read explicitly
 * instead of relying on the session-based current_store_id().
 */
class AuthenticateMobileApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');
        if (! str_starts_with($header, 'Bearer ')) {
            return response()->json(['message' => 'Missing bearer token.'], 401);
        }

        $plaintext = substr($header, 7);
        $token = MobileApiToken::resolve($plaintext);

        if (! $token || $token->isExpired()) {
            return response()->json(['message' => 'Invalid or expired token.'], 401);
        }

        $user = $token->user;
        if (! $user || ! $user->is_active) {
            return response()->json(['message' => 'Account unavailable.'], 401);
        }

        $token->forceFill(['last_used_at' => now()])->save();

        $request->attributes->set('mobile_api_user', $user);
        $request->attributes->set('mobile_api_store_id', $token->store_id);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
