<?php

namespace App\Console\Commands;

use App\Actions\Maintenance\ClearSampleData as ClearSampleDataAction;
use Illuminate\Console\Command;

/**
 * Wipe all business / sample data, keeping company, stores, users, settings,
 * lookups, and the chart of accounts — for a customer who installed with demo
 * data and wants a clean slate. Takes a backup first unless --no-backup.
 *
 *   php artisan pos:clear-sample-data
 *   php artisan pos:clear-sample-data --force --no-backup
 */
class ClearSampleData extends Command
{
    protected $signature = 'pos:clear-sample-data {--no-backup : Skip the safety backup} {--force : Do not prompt}';

    protected $description = 'Delete all business/sample data (keeps company, stores, users, settings, chart of accounts)';

    public function handle(ClearSampleDataAction $clear): int
    {
        if (pos_is_demo()) {
            $this->warn('This is a demo install (POS_DEMO_MODE) — use pos:demo-reset instead. Nothing done.');
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm(
            'This PERMANENTLY deletes ALL sales, purchases, products, customers, suppliers, '
            .'expenses, stock, and accounting entries. Company, stores, users, settings, and the '
            .'chart of accounts are kept. Continue?'
        )) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        $this->info('Clearing sample data…');
        $result = ($clear)(null, backup: ! $this->option('no-backup'));

        if ($result['backup']) {
            $this->info('Backup taken: '.$result['backup']->file_path);
        } elseif (! $this->option('no-backup')) {
            $this->warn('Backup could not be created — data was still cleared.');
        }

        $total = array_sum($result['cleared']);
        foreach ($result['cleared'] as $table => $count) {
            $this->line(sprintf('  %-28s %s', $table, number_format($count)));
        }
        $this->info("Done — cleared {$total} rows across ".count($result['cleared']).' tables.');

        return self::SUCCESS;
    }
}
