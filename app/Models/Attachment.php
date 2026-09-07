<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A stored file attached to any model (polymorphic). Files live on the
 * private `local` disk and are streamed through an auth-gated controller
 * route — never served directly, since attachments (supplier invoices,
 * etc.) are not public.
 */
class Attachment extends Model
{
    // The table carries only `created_at` (DB `useCurrent`), no `updated_at`.
    public $timestamps = false;

    protected $fillable = [
        'attachable_type', 'attachable_id',
        'path', 'original_filename', 'mime_type', 'size_bytes',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /** Human-readable size, e.g. "2.4 MB". */
    public function humanSize(): string
    {
        $bytes = (int) $this->size_bytes;
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        $units = ['KB', 'MB', 'GB'];
        $value = $bytes / 1024;
        foreach ($units as $unit) {
            if ($value < 1024 || $unit === 'GB') {
                return rtrim(rtrim(number_format($value, 1), '0'), '.').' '.$unit;
            }
            $value /= 1024;
        }
        return $bytes.' B';
    }
}
