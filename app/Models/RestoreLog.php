<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single restore attempt — one row per RunRestore invocation. Records the
 * source the operator restored from, whether a pre-restore safety snapshot
 * was taken, and the outcome.
 *
 * No `updated_at`: `started_at` / `finished_at` carry the timing, and
 * `created_at` is stamped by the database default.
 */
class RestoreLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'backup_log_id', 'source', 'started_at', 'finished_at', 'status',
        'error_message', 'was_pre_restore_backup_created', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at'                     => 'datetime',
            'finished_at'                    => 'datetime',
            'created_at'                     => 'datetime',
            'was_pre_restore_backup_created' => 'boolean',
        ];
    }

    public function backupLog(): BelongsTo
    {
        return $this->belongsTo(BackupLog::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
