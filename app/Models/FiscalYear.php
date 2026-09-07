<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A financial year (e.g. "FY 2026-2027"), starting on
 * `company.fiscal_year_start_month`. Holds 12 monthly {@see FiscalPeriod}s.
 * Created on demand by {@see \App\Services\Accounting\FiscalPeriodResolver}.
 */
class FiscalYear extends Model
{
    protected $fillable = [
        'name', 'start_date', 'end_date', 'is_locked', 'locked_at', 'locked_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date'   => 'date',
            'is_locked'  => 'boolean',
            'locked_at'  => 'datetime',
        ];
    }

    public function periods(): HasMany
    {
        return $this->hasMany(FiscalPeriod::class);
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }
}
