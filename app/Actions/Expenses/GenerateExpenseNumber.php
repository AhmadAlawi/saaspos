<?php

namespace App\Actions\Expenses;

use App\Models\Expense;
use App\Models\Store;

/**
 * Next expense number for a store — `EXP-{store}-{Ym}-{seq:04}`
 * (e.g. EXP-MAIN-202606-0007). Sequence resets monthly via the prefix.
 *
 * Caller MUST run this inside the insert transaction so two concurrent
 * creates can't read the same MAX and collide on `(store_id, number)`.
 */
class GenerateExpenseNumber
{
    public function __invoke(int $storeId, ?\DateTimeInterface $when = null): string
    {
        $store = Store::query()->findOrFail($storeId);
        $when  = $when ?? now();

        $prefix = 'EXP-'.($store->code ?: $store->id).'-'.$when->format('Ym').'-';

        $latest = Expense::withTrashed()
            ->where('store_id', $storeId)
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $seq = $latest ? ((int) substr((string) $latest, strrpos((string) $latest, '-') + 1)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
