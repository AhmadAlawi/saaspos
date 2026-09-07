<?php

namespace App\Actions\Reports;

use App\Mail\ScheduledReportMail;
use App\Models\ScheduledReport;
use App\Models\ScheduledReportRun;
use App\Services\Reports\ReportRunner;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Runs one scheduled report end-to-end: generate the file, email it to every
 * recipient, and log the outcome as a {@see ScheduledReportRun}. Never throws
 * — a failure is captured on the run row so the Scheduled-reports admin screen
 * can surface it (and offer a retry). Runs synchronously (no queue) to stay
 * shared-hosting friendly.
 *
 * Hooks: `report.scheduled.run` on success, `report.scheduled.run.failed` on
 * error.
 */
class RunScheduledReport
{
    public function __construct(private ReportRunner $runner) {}

    public function __invoke(ScheduledReport $schedule): ScheduledReportRun
    {
        try {
            $result = $this->runner->run(
                $schedule->report_key,
                $schedule->parameters ?? [],
                (string) $schedule->format,
                $schedule->timezone,
            );

            $delivered = $this->deliver($schedule, $result['path'], $result['title']);

            $run = ScheduledReportRun::create([
                'scheduled_report_id'  => $schedule->id,
                'run_at'               => now(),
                'status'               => ScheduledReportRun::STATUS_SUCCESS,
                'file_path'            => 'report-exports/'.$result['filename'],
                'recipients_delivered' => $delivered,
                'error_message'        => null,
            ]);

            do_action('report.scheduled.run', $schedule, $run);

            return $run;
        } catch (\Throwable $e) {
            report($e);

            $run = ScheduledReportRun::create([
                'scheduled_report_id'  => $schedule->id,
                'run_at'               => now(),
                'status'               => ScheduledReportRun::STATUS_FAILED,
                'file_path'            => null,
                'recipients_delivered' => [],
                'error_message'        => Str::limit($e->getMessage(), 480, ''),
            ]);

            do_action('report.scheduled.run.failed', $schedule, $e);

            return $run;
        }
    }

    /**
     * Email the generated file to each recipient. Plugins may augment the
     * list via the `report.scheduled.recipients` filter. If every recipient
     * fails (e.g. SMTP outage) the whole run is treated as failed so it can be
     * retried; a partial success keeps whatever got through.
     *
     * @return list<string>  the addresses actually delivered to
     */
    private function deliver(ScheduledReport $schedule, string $path, string $title): array
    {
        $recipients = apply_filters(
            'report.scheduled.recipients',
            array_values(array_filter(array_map('trim', (array) ($schedule->recipients_email ?? [])))),
            $schedule,
        );

        $delivered = [];
        $failures  = 0;

        foreach ($recipients as $email) {
            if ($email === '') {
                continue;
            }
            try {
                Mail::to($email)->send(new ScheduledReportMail($schedule, $path, $title));
                $delivered[] = $email;
            } catch (\Throwable $e) {
                report($e);
                $failures++;
            }
        }

        if ($delivered === [] && $failures > 0) {
            throw new \RuntimeException('Scheduled report could not be delivered to any recipient.');
        }

        return $delivered;
    }
}
