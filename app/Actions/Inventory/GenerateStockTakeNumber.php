<?php

namespace App\Actions\Inventory;

use App\Models\Store;
use App\Models\StockTake;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Builds the next stock-take document number for a given (store, month).
 *
 * Format: `COUNT-{STORE}-{YYYYMM}-{NNNN}`, e.g. `COUNT-MAIN-202606-0001`.
 * Per-store + per-month sequence — mirrors purchase + sale numbering so
 * the operator's mental model carries over.
 *
 * Race-safe enough for v1.0: `stock_takes (store_id, number)` is unique
 * and the surrounding `CreateStockTake` action runs in a DB transaction.
 * Two concurrent creates race → the second insert fails and the caller
 * retries (uses soft-deletes' `withTrashed` so a previously-deleted
 * sequence doesn't get re-used).
 */
class GenerateStockTakeNumber
{
    public function __invoke(Store $store, ?CarbonInterface $forDate = null): string
    {
        $date = $forDate ?: Carbon::now();
        $ym   = $date->format('Ym');
        $prefix = sprintf('COUNT-%s-%s-', $store->code, $ym);

        $latest = StockTake::withTrashed()
            ->where('store_id', $store->id)
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('number');

        $seq = 1;
        if ($latest) {
            $tail = substr($latest, strrpos($latest, '-') + 1);
            $seq  = ((int) $tail) + 1;
        }

        return $prefix.sprintf('%04d', $seq);
    }
}
