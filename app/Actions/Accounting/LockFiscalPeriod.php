<?php

namespace App\Actions\Accounting;

use App\Models\FiscalPeriod;

/**
 * Locks a fiscal period. A locked period rejects new / edited journal entries
 * dated within it (enforced by {@see PostJournalEntry}). Fires
 * `fiscal_period.locked` for audit listeners.
 */
class LockFiscalPeriod
{
    public function __invoke(FiscalPeriod $period): FiscalPeriod
    {
        $period->forceFill(['is_locked' => true])->save();

        do_action('fiscal_period.locked', $period, auth()->user());

        return $period;
    }
}
