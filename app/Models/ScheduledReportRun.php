<?php

namespace App\Models;

use App\Models\Concerns\HasUtcDisplayTimes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One delivery attempt of a {@see ScheduledReport}. Records whether the run
 * succeeded, where the generated file landed, who it reached, and any error —
 * surfaced on the Scheduled-reports admin screen so failures are visible.
 *
 * The `scheduled_report_runs` table has no updated_at (append-only log).
 */
class ScheduledReportRun extends Model
{
    use HasUtcDisplayTimes;

    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED  = 'failed';

    public $timestamps = false;

    protected $fillable = [
        'scheduled_report_id', 'run_at', 'status', 'file_path',
        'recipients_delivered', 'error_message',
    ];

    protected $casts = [
        'run_at'               => 'datetime',
        'recipients_delivered' => 'array',
    ];

    public function scheduledReport(): BelongsTo
    {
        return $this->belongsTo(ScheduledReport::class);
    }
}
