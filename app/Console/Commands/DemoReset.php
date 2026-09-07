<?php

namespace App\Console\Commands;

use App\Actions\Demo\ResetDemoData;
use Illuminate\Console\Command;

/**
 * Restore the demo to its baseline snapshot now — the same action the nightly
 * scheduler runs (see routes/console.php). Handy for testing the reset, or for
 * clearing junk on demand without waiting for 02:00.
 *
 * No-ops unless POS_DEMO_MODE is on and a baseline has been captured with
 * `pos:demo-snapshot`.
 */
class DemoReset extends Command
{
    protected $signature = 'pos:demo-reset';

    protected $description = 'Restore the demo database to its baseline snapshot';

    public function handle(ResetDemoData $reset): int
    {
        if (! pos_is_demo()) {
            $this->warn('Not in demo mode (POS_DEMO_MODE is off) — nothing to reset.');
            return self::SUCCESS;
        }

        if ($reset->baseline() === null) {
            $this->error('No demo baseline found. Capture one first: php artisan pos:demo-snapshot');
            return self::FAILURE;
        }

        $this->info('Restoring demo to baseline…');

        $log = $reset();

        if ($log === null || $log->status !== 'success') {
            $this->error('Demo reset failed: '.($log->error_message ?? 'unknown error'));
            return self::FAILURE;
        }

        $this->info('Demo reset complete — database restored to baseline.');

        return self::SUCCESS;
    }
}
