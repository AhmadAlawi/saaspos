<?php

use App\Http\Controllers\Installer\AdminController;
use App\Http\Controllers\Installer\DatabaseController;
use App\Http\Controllers\Installer\DemoController;
use App\Http\Controllers\Installer\LicenseController;
use App\Http\Controllers\Installer\RequirementsController;
use App\Http\Controllers\Installer\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::prefix('install')
    ->middleware(['installer.errors', 'restrict.during.install', 'no.cache'])
    ->group(function () {
        Route::get('/', [WelcomeController::class, 'show'])->name('install.welcome');
        Route::post('/language', [WelcomeController::class, 'setLanguage'])->name('install.language');

        Route::get('/requirements', [RequirementsController::class, 'show'])->name('install.requirements');

        Route::get('/license', [LicenseController::class, 'show'])->name('install.license');
        Route::post('/license', [LicenseController::class, 'validateLicense'])->name('install.license.validate');

        Route::get('/database', [DatabaseController::class, 'show'])->name('install.database');
        Route::post('/database', [DatabaseController::class, 'save'])->name('install.database.save');
        Route::get('/database/migrate', [DatabaseController::class, 'migrate'])->name('install.database.migrate');
        Route::post('/database/migrate/step', [DatabaseController::class, 'runStep'])->name('install.database.migrate.step');

        Route::get('/admin', [AdminController::class, 'show'])->name('install.admin');
        Route::post('/admin', [AdminController::class, 'save'])->name('install.admin.save');

        Route::get('/demo', [DemoController::class, 'show'])->name('install.demo');
        Route::post('/complete', [DemoController::class, 'complete'])->name('install.complete');
    });
