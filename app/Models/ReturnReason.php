<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Reason codes for refunds / returns. Shared lookup used by both sales
 * and purchases — the cashier picks one when ringing up a return so
 * reports can slice "why did we refund?".
 *
 * `default_restock` lets a reason auto-toggle the restock-on-return
 * flag (e.g. "Defective" defaults to NO restock).
 */
class ReturnReason extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code', 'name', 'default_restock', 'requires_permission', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'default_restock'     => 'boolean',
            'requires_permission' => 'boolean',
            'is_active'           => 'boolean',
            'sort_order'          => 'integer',
        ];
    }

    /** @param Builder<ReturnReason> $q */
    public function scopeOrdered(Builder $q): void
    {
        $q->orderBy('sort_order')->orderBy('name');
    }
}
