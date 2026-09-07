<?php

namespace App\Console\Commands;

use App\Actions\Demo\ResetDemoData;
use Illuminate\Console\Command;

/**
 * Capture the current database as the demo baseline — the clean state every
 * nightly reset (see {@see ResetDemoData}) returns to.
 *
 * Run this ONCE, right after seeding the demo with exactly the data you want
 * visitors to always start from. Re-run it whenever you change that baseline.
 */
class DemoSnapshot extends Command
{
    protected $signature = 'pos:demo-snapshot';

    protected $description = 'Capture the current database as the demo baseline snapshot';

    public function handle(ResetDemoData $demo): int
    {
        $this->info('Capturing demo baseline…');

        $log = $demo->capture();

        if ($log->status !== 'success') {
            $this->error('Baseline capture failed: '.($log->error_message ?? 'unknown error'));
            return self::FAILURE;
        }

        $this->info(sprintf(
            'Demo baseline captured: %s (%s). The nightly reset will restore this state.',
            $log->file_path,
            number_format(($log->file_size_bytes ?? 0) / 1024, 1).' KB',
        ));

        return self::SUCCESS;
    }
}
