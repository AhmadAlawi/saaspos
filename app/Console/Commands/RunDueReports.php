<?php

namespace App\Console\Commands;

use App\Actions\Reports\RunScheduledReport;
use App\Models\ScheduledReport;
use Illuminate\Console\Command;

/**
 * Delivers scheduled reports whose next_run_at has passed. Wired to run every
 * minute from the Laravel scheduler (routes/console.php), matching the
 * cron-driven, shared-hosting model — no queue worker required.
 *
 * Timing model: next_run_at is stored as a UTC instant (see
 * {@see ScheduledReport::computeNextRun()}); the framework console timezone is
 * UTC, so comparing against `now()` here is an apples-to-apples UTC compare.
 * After each schedule fires we always advance next_run_at — a failed run is
 * logged (and retryable from the admin screen) rather than re-fired every
 * minute.
 */
class RunDueReports extends Command
{
    protected $signature = 'pos:run-scheduled-reports';

    protected $description = 'Deliver any scheduled reports that are now due.';

    public function handle(RunScheduledReport $runScheduledReport): int
    {
        $due = ScheduledReport::query()
            ->active()
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->orderBy('next_run_at')
            ->get();

        foreach ($due as $schedule) {
            try {
                $runScheduledReport($schedule);
            } catch (\Throwable $e) {
                // RunScheduledReport swallows its own errors, but never let a
                // single bad schedule stop the rest of the batch.
                report($e);
            } finally {
                $schedule->forceFill([
                    'last_run_at' => now(),
                    'next_run_at' => $schedule->computeNextRun(),
                ])->save();
            }
        }

        if ($due->isNotEmpty()) {
            $this->info("Ran {$due->count()} scheduled report(s).");
        }

        return self::SUCCESS;
    }
}
