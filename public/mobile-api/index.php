<?php

/**
 * Independent front controller for the Expo mobile app's API.
 *
 * Deliberately NOT wired through bootstrap/app.php or public/index.php —
 * this boots its own Application instance with its own routing/middleware
 * stack, so nothing in the main app's bootstrap is ever touched. It reuses
 * the same vendor/, app/Models, .env and database as the main app (same
 * basePath), it just never goes through the main kernel.
 *
 * nginx routes /mobile-api/* to this file (see deployment notes).
 */

use App\Http\Middleware\AuthenticateMobileApiToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

define('LARAVEL_START', microtime(true));

$basePath = dirname(__DIR__, 2);

require $basePath.'/vendor/autoload.php';

$app = Application::configure(basePath: $basePath)
    ->withRouting(
        api: $basePath.'/routes/mobile-api.php',
        apiPrefix: '',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'mobile.api.auth' => AuthenticateMobileApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Every response from this API is JSON — never the HTML error
        // pages the main app's exception handler renders.
        $exceptions->shouldRenderJsonWhen(fn () => true);
    })
    ->create();

$app->handleRequest(Illuminate\Http\Request::capture());
