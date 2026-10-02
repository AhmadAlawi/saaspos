<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One queued/prepared footage clip for a sale or activity-log event.
 * See the migration's doc-comment for why this exists instead of the
 * old synchronous fetch-and-serve flow. Processed by
 * {@see \App\Jobs\PrepareCameraClip}.
 */
class CameraClipRequest extends Model
{
    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_READY      = 'ready';
    public const STATUS_FAILED     = 'failed';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'store_id',
        'channel',
        'window_start',
        'window_end',
        'label',
        'status',
        'file_path',
        'error_message',
        'requested_by',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'window_start' => 'datetime',
            'window_end'   => 'datetime',
            'expires_at'   => 'datetime',
        ];
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
