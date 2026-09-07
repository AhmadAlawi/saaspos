<?php

namespace App\Actions\Updater;

use Illuminate\Support\Facades\Artisan;

/**
 * Runs the new release's migrations forward. A separate action (rather than an
 * inline Artisan call) so the InstallUpdate orchestrator can inject it — which
 * also lets tests simulate a migration failure to exercise the rollback path.
 */
class ExecuteUpdateMigrations
{
    public function __invoke(): void
    {
        Artisan::call('migrate', ['--force' => true]);
    }
}
