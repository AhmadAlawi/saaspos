<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per update attempt — the audit trail for the auto-updater. Created
 * 'pending', walked through 'installing', and finished as 'success', 'failed',
 * or 'rolled_back', with a `steps_log` capturing what happened at each step.
 *
 * `pre_update_backup_id` points at the mandatory snapshot taken before any file
 * is touched — the same backup the rollback restores from. Survives a restore
 * because the audit tables are overlaid (see RunRestore).
 *
 * No `updated_at`: `started_at` / `finished_at` carry the timing.
 */
class UpdateLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'from_version', 'to_version', 'started_at', 'finished_at', 'status',
        'pre_update_backup_id', 'channel', 'steps_log', 'error_message', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at'  => 'datetime',
            'finished_at' => 'datetime',
            'steps_log'   => 'array',
        ];
    }

    public function preUpdateBackup(): BelongsTo
    {
        return $this->belongsTo(BackupLog::class, 'pre_update_backup_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
