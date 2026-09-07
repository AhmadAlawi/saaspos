<?php

namespace App\Actions\Accounting;

use App\Models\JournalEntry;
use App\Models\Sale;

/**
 * When a sale is voided, reverses its journal entry (docs/features/accounting.md
 * §5.2, source `sale_void`) — undoing the revenue, tax, cash, and COGS in one
 * balanced mirror. No-op if the sale never posted (e.g. accounting was off) or
 * was already voided. Idempotent; the `sale.after_void` listener wraps it so a
 * failure never blocks the void.
 */
class PostSaleVoidEntry
{
    public function __construct(private ReverseJournalEntry $reverse) {}

    public function __invoke(Sale $sale): ?JournalEntry
    {
        if ($this->alreadyReversed($sale)) {
            return null;
        }

        $original = JournalEntry::where('source', JournalEntry::SOURCE_SALE)
            ->where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->posted()
            ->first();

        if (! $original) {
            return null; // nothing to reverse
        }

        return ($this->reverse)($original, JournalEntry::SOURCE_SALE_VOID, [
            'description' => __('accounting.descriptions.sale_void', ['number' => $sale->number]),
        ]);
    }

    private function alreadyReversed(Sale $sale): bool
    {
        return JournalEntry::where('source', JournalEntry::SOURCE_SALE_VOID)
            ->where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->exists();
    }
}
