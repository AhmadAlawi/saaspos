<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One posting line of a {@see JournalEntry}: a debit OR a credit against a
 * single {@see Account} (exactly one of the two is non-zero — enforced by the
 * poster). The `journal_lines` table has no timestamps.
 */
class JournalLine extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'journal_entry_id', 'account_id', 'debit', 'credit', 'description', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'debit'      => 'decimal:4',
            'credit'     => 'decimal:4',
            'sort_order' => 'integer',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
