<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit row for every print attempt (docs/features/hardware.md §9.4).
 * Written by the client print bridge after it sends a receipt/label to a
 * printer — success or failure — so operators can see what printed, on
 * which terminal, and chase failures.
 *
 * The table has only `created_at` (no updated_at): a print is a
 * point-in-time event, never edited.
 */
class PrintLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'terminal_id',
        'user_id',
        'reference_type',
        'reference_id',
        'printer_type',
        'mode',
        'status',
        'error_message',
        'bytes_size',
    ];

    protected function casts(): array
    {
        return [
            'created_at'  => 'datetime',
            'reference_id' => 'integer',
            'bytes_size'  => 'integer',
        ];
    }

    /** @return BelongsTo<Terminal, $this> */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
