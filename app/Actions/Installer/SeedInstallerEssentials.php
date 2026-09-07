<?php

namespace App\Actions\Installer;

use Database\Seeders\CurrenciesSeeder;
use Database\Seeders\PaymentMethodsSeeder;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\ReturnReasonsSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\StockAdjustmentReasonsSeeder;
use Database\Seeders\UnitsSeeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Seeds the lookup data the rest of the installer needs.
 * ChartOfAccountsSeeder runs LATER (after company is created in Step 5).
 */
class SeedInstallerEssentials
{
    /**
     * The lookup seeders the installer must run, in order. Shared with the
     * stepped runner ({@see RunInstallerSeederStep}) so the chunked database
     * step and this one-shot variant stay in lock-step.
     *
     * @var array<int, class-string>
     */
    public const SEEDERS = [
        PermissionsSeeder::class,
        RolesSeeder::class,
        CurrenciesSeeder::class,
        UnitsSeeder::class,
        PaymentMethodsSeeder::class,
        ReturnReasonsSeeder::class,
        StockAdjustmentReasonsSeeder::class,
    ];

    /** @return array{ok: bool, output: string} */
    public function __invoke(): array
    {
        try {
            foreach (self::SEEDERS as $seeder) {
                Artisan::call('db:seed', [
                    '--class' => $seeder,
                    '--force' => true,
                ]);
            }

            return ['ok' => true, 'output' => Artisan::output()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'output' => $e->getMessage()];
        }
    }
}
