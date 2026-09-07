<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One monthly period inside a {@see FiscalYear}. Every journal entry is filed
 * under the period its `entry_date` falls in. Locking a period blocks new /
 * edited entries dated within it (enforced in
 * {@see \App\Actions\Accounting\PostJournalEntry}).
 *
 * The `fiscal_periods` table intentionally has no timestamps.
 */
class FiscalPeriod extends Model
{
    public $timestamps = false;

    protected $fillable = ['fiscal_year_id', 'name', 'start_date', 'end_date', 'is_locked'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date'   => 'date',
            'is_locked'  => 'boolean',
        ];
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }
}
