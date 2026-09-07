<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Reports\RunScheduledReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreScheduledReportRequest;
use App\Models\ScheduledReport;
use App\Support\ReportRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Manages recurring report deliveries (docs/features/reports.md §3.6, §12.2).
 * Every action is gated by `reports.schedule`; creating/running a schedule
 * additionally requires the underlying report's own view permission so a user
 * can't schedule a report they cannot see. Mutations are owner-or-super-admin.
 */
class ScheduledReportController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user?->hasPermission('reports.schedule'), 403);

        $rows = ScheduledReport::query()
            ->visibleTo($user)
            ->with(['user:id,name', 'latestRun'])
            ->latest()
            ->get()
            ->map(function (ScheduledReport $s) use ($user) {
                $meta = ReportRegistry::get($s->report_key);
                if (! $meta) {
                    return null;
                }

                return [
                    'model'    => $s,
                    'title'    => __($meta['title_key']),
                    'last_run' => $s->latestRun,
                    'is_owner' => $s->user_id === $user->id,
                ];
            })
            ->filter()
            ->values();

        return view('admin.reports.schedules.index', ['rows' => $rows]);
    }

    public function store(StoreScheduledReportRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermission('reports.schedule'), 403);

        $key  = $request->input('report_key');
        $meta = ReportRegistry::get($key);
        abort_unless($meta && $user->hasPermission($meta['permission']), 403);

        $schedule = new ScheduledReport([
            'user_id'             => $user->id,
            'store_id'            => current_store_id() ?: null,
            'report_key'          => $key,
            'name'                => $request->input('name'),
            'parameters'          => ReportRegistry::sanitizeParams((array) $request->input('parameters', [])),
            'frequency'           => $request->input('frequency'),
            'day_of_week'         => $request->input('frequency') === ScheduledReport::FREQ_WEEKLY ? $request->integer('day_of_week') : null,
            'day_of_month'        => $request->input('frequency') === ScheduledReport::FREQ_MONTHLY ? $request->integer('day_of_month') : null,
            'time_of_day'         => $request->input('time_of_day').':00',
            'timezone'            => (string) config('app.timezone', 'UTC'),
            'format'              => $request->input('format'),
            'recipients_email'    => array_values(array_filter(array_map('trim', (array) $request->input('recipients_email', [])))),
            'recipients_whatsapp' => [],
            'subject_template'    => $request->input('subject_template'),
            'message_template'    => $request->input('message_template'),
            'is_active'           => true,
        ]);

        $schedule->next_run_at = $schedule->computeNextRun();
        $schedule->save();

        do_action('report.scheduled', $schedule);

        return response()->json([
            'ok'       => true,
            'id'       => $schedule->id,
            'redirect' => route('admin.reports.schedules.index'),
        ]);
    }

    public function pause(Request $request, ScheduledReport $scheduledReport): JsonResponse
    {
        $this->authorizeMutation($request, $scheduledReport);

        $scheduledReport->update(['is_active' => false]);

        return response()->json(['ok' => true]);
    }

    public function resume(Request $request, ScheduledReport $scheduledReport): JsonResponse
    {
        $this->authorizeMutation($request, $scheduledReport);

        $scheduledReport->update([
            'is_active'   => true,
            'next_run_at' => $scheduledReport->computeNextRun(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function runNow(Request $request, ScheduledReport $scheduledReport, RunScheduledReport $runScheduledReport): JsonResponse
    {
        $this->authorizeMutation($request, $scheduledReport);

        $meta = ReportRegistry::get($scheduledReport->report_key);
        abort_unless($meta && $request->user()->hasPermission($meta['permission']), 403);

        $run = $runScheduledReport($scheduledReport);

        return response()->json([
            'ok'      => true,
            'status'  => $run->status,
            'message' => $run->status === 'success'
                ? __('reports.schedule.run_now_ok')
                : ($run->error_message ?: __('reports.schedule.run_now_failed')),
        ]);
    }

    public function destroy(Request $request, ScheduledReport $scheduledReport): JsonResponse
    {
        $this->authorizeMutation($request, $scheduledReport);

        $scheduledReport->delete();

        return response()->json(['ok' => true]);
    }

    /** A schedule may be changed only by its owner or a super admin. */
    private function authorizeMutation(Request $request, ScheduledReport $scheduledReport): void
    {
        $user = $request->user();
        abort_unless($user?->hasPermission('reports.schedule'), 403);
        abort_unless($scheduledReport->user_id === $user->id || $user->is_super_admin, 403);
    }
}
