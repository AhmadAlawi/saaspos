<?php

namespace App\Actions\Accounting;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Shift;

/**
 * On shift close, books the till's cash variance (docs/features/accounting.md
 * §5.2, source `shift_variance`):
 *
 *   overage  (counted > expected)  →  Dr Cash on Hand / Cr Cash Overage
 *   shortage (counted < expected)  →  Dr Cash Shortage / Cr Cash on Hand
 *
 * No-op on a balanced close. Idempotent; the `shift.after_close` listener wraps
 * it so a failure never blocks the close.
 */
class PostShiftVarianceEntry
{
    public function __construct(
        private PostJournalEntry $post,
        private \App\Services\Accounting\AccountMappingResolver $mappings,
    ) {}

    public function __invoke(Shift $shift): ?JournalEntry
    {
        if ($this->alreadyPosted($shift)) {
            return null;
        }

        $variance = (string) ($shift->cash_variance ?? '0');
        $cmp      = bccomp($variance, '0', 4);
        if ($cmp === 0) {
            return null; // clean close
        }

        $storeId = (int) $shift->store_id;
        $amount  = $cmp < 0 ? bcmul($variance, '-1', 4) : $variance;
        $cashId  = (int) Account::where('code', '1010')->value('id'); // Cash on Hand

        if ($cmp > 0) {
            // Overage — extra cash in the drawer is other income.
            $lines = [
                ['account_id' => $cashId, 'debit' => $amount, 'credit' => 0, 'description' => __('accounting.lines.cash')],
                ['account_id' => $this->mappings->id('cash_overage', $storeId), 'debit' => 0, 'credit' => $amount, 'description' => __('accounting.lines.cash_overage')],
            ];
        } else {
            // Shortage — missing cash is an expense.
            $lines = [
                ['account_id' => $this->mappings->id('cash_shortage', $storeId), 'debit' => $amount, 'credit' => 0, 'description' => __('accounting.lines.cash_shortage')],
                ['account_id' => $cashId, 'debit' => 0, 'credit' => $amount, 'description' => __('accounting.lines.cash')],
            ];
        }

        return ($this->post)([
            'store_id'       => $storeId,
            'entry_date'     => now()->toDateString(),
            'source'         => JournalEntry::SOURCE_SHIFT_VARIANCE,
            'reference_type' => Shift::class,
            'reference_id'   => $shift->id,
            'description'    => __('accounting.descriptions.shift_variance', ['number' => $shift->id]),
            'created_by'     => $shift->user_id ?? null,
            'lines'          => $lines,
        ]);
    }

    private function alreadyPosted(Shift $shift): bool
    {
        return JournalEntry::where('source', JournalEntry::SOURCE_SHIFT_VARIANCE)
            ->where('reference_type', Shift::class)
            ->where('reference_id', $shift->id)
            ->exists();
    }
}
