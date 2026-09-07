<?php

namespace App\Actions\Installer;

use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\Artisan;

class SeedChartOfAccounts
{
    /** @return array{ok: bool, output: string} */
    public function __invoke(): array
    {
        try {
            Artisan::call('db:seed', [
                '--class' => ChartOfAccountsSeeder::class,
                '--force' => true,
            ]);

            return ['ok' => true, 'output' => Artisan::output()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'output' => $e->getMessage()];
        }
    }
}
