<?php

namespace App\Http\Middleware;

use App\Actions\Installer\WriteEnvFile;
use App\Support\InstallState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes the app bootable for the installer before any database exists.
 *
 * Two guarantees, both needed because the installer's first screen is a
 * CSRF-protected form rendered on a fresh upload with no database and an empty
 * key:
 *
 *   1. APP_KEY — a fresh `.env` ships `APP_KEY=` empty, but cookie encryption /
 *      CSRF can't run without it. We generate one on the first request that
 *      finds it missing, persist it to `.env`, and rebind the encrypter so the
 *      rest of THIS request already uses it. (Shared-hosting customers can't
 *      run `php artisan key:generate`.)
 *
 *   2. Session + cache drivers — until the app is installed, the database isn't
 *      set up, so a DB-backed session/cache (Laravel's default) would crash the
 *      wizard before Step 4 creates the schema. We force the `file` drivers
 *      while uninstalled so the installer needs zero database to run, whatever
 *      the shipped `.env` says.
 *
 * Registered as GLOBAL middleware (prepended) so it runs before the `web`
 * group's EncryptCookies / StartSession. Once installed (lock present) it's a
 * couple of cheap checks and an instant pass-through.
 */
class EnsureAppKey
{
    public function __construct(private readonly WriteEnvFile $writeEnv) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Before install: never let the session/cache reach for a database
        // that doesn't exist yet. Force file-backed drivers for this request.
        if (! InstallState::isLocked()) {
            config([
                'session.driver' => 'file',
                'cache.default'  => 'file',
            ]);
        }

        if (blank(config('app.key'))) {
            $key = 'base64:'.base64_encode(random_bytes(32));

            ($this->writeEnv)(['APP_KEY' => $key]);
            config(['app.key' => $key]);

            // Drop the (unresolved / keyless) encrypter binding so the next
            // resolution — EncryptCookies, later this request — picks up the key.
            app()->forgetInstance('encrypter');
        }

        return $next($request);
    }
}
