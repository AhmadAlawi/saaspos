<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single backup attempt — one row per RunBackup invocation. Created in
 * 'running' state, then flipped to 'success' (with file path, size, checksum
 * and the unencrypted manifest) or 'failed' (with the error) when the archive
 * is written.
 *
 * Two consumers lean on this row:
 *   - the restore wizard lists successful rows as restore sources;
 *   - the updater FKs its pre-update snapshot here via
 *     `update_logs.pre_update_backup_id`, so a rollback knows what to restore.
 *
 * No timestamps column pair: `started_at` / `finished_at` carry the timing.
 */
class BackupLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'type', 'destination', 'started_at', 'finished_at', 'status',
        'file_path', 'file_size_bytes', 'checksum', 'encryption_enabled',
        'schedule_id', 'manifest', 'error_message', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at'         => 'datetime',
            'finished_at'        => 'datetime',
            'file_size_bytes'    => 'integer',
            'encryption_enabled' => 'boolean',
            'manifest'           => 'array',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Successful backups only — the restore wizard's source list. */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }
}
