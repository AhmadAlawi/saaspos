<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per cashier action on the /cashier POS screen (docs/features/
 * hardware.md §9.5 — cashier activity log). Same shape as {@see PrintLog}:
 * a point-in-time event, never edited, no `updated_at`. `type` is the
 * broad bucket the by-type report groups on ('cart', 'discount', 'sale',
 * 'refund', 'drawer', 'shift', 'print', 'auth', 'error'); `action` is the
 * specific event within it.
 */
class CashierActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'terminal_id',
        'user_id',
        'shift_id',
        'type',
        'action',
        'reference_type',
        'reference_id',
        'meta',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'created_at'     => 'datetime',
            'reference_id'   => 'integer',
            'meta'           => 'array',
        ];
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
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

    /** @return BelongsTo<Shift, $this> */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
