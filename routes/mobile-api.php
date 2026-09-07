<?php

use App\Http\Controllers\MobileApi\AuthController;
use App\Http\Controllers\MobileApi\ProductController;
use App\Http\Controllers\MobileApi\StockTakeController;
use Illuminate\Support\Facades\Route;

/**
 * Routes for the Expo mobile app (price checker / stock-take / label
 * printing). Loaded ONLY by public/mobile-api/index.php's own kernel —
 * never touches routes/web.php or bootstrap/app.php.
 */
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('mobile.api.auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/products/lookup', [ProductController::class, 'lookup']);
    Route::get('/products/search', [ProductController::class, 'search']);
    Route::get('/products/catalog', [ProductController::class, 'catalog']);
    Route::get('/products/catalog/meta', [ProductController::class, 'catalogMeta']);

    Route::get('/stock-takes', [StockTakeController::class, 'index']);
    Route::post('/stock-takes', [StockTakeController::class, 'store']);
    Route::get('/stock-takes/{stockTake}', [StockTakeController::class, 'show']);
    Route::patch('/stock-takes/{stockTake}', [StockTakeController::class, 'update']);
    Route::post('/stock-takes/{stockTake}/post', [StockTakeController::class, 'post']);
});
