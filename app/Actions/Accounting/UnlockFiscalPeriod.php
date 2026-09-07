<?php

namespace App\Actions\Accounting;

use App\Models\FiscalPeriod;

/**
 * Unlocks a fiscal period so a correction can be posted into it. Fires
 * `fiscal_period.unlocked` for audit listeners. Refused once the period's year
 * has been closed.
 */
class UnlockFiscalPeriod
{
    public function __invoke(FiscalPeriod $period): FiscalPeriod
    {
        if ($period->fiscalYear?->is_locked) {
            throw new \RuntimeException(__('accounting.periods.errors.year_closed'));
        }

        $period->forceFill(['is_locked' => false])->save();

        do_action('fiscal_period.unlocked', $period, auth()->user());

        return $period;
    }
}
