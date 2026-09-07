<?php

namespace App\Actions\Installer;

use Illuminate\Support\Facades\Artisan;

/**
 * Runs a single lookup seeder by its index in {@see SeedInstallerEssentials::SEEDERS}.
 *
 * Counterpart to {@see RunMigrationChunk}: the database step polls one seeder
 * per request so seeding, like migrating, never hangs on a single long
 * request. Seeders are idempotent (they upsert), so a retried index is safe.
 */
class RunInstallerSeederStep
{
    /**
     * @return array{ok: bool, output: string}
     */
    public function __invoke(int $index): array
    {
        $seeders = SeedInstallerEssentials::SEEDERS;

        if (! isset($seeders[$index])) {
            return ['ok' => true, 'output' => ''];
        }

        try {
            Artisan::call('db:seed', [
                '--class' => $seeders[$index],
                '--force' => true,
            ]);

            return ['ok' => true, 'output' => Artisan::output()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'output' => $e->getMessage()];
        }
    }

    /** Total number of seeder steps. */
    public static function total(): int
    {
        return count(SeedInstallerEssentials::SEEDERS);
    }
}
