<?php

namespace App\Actions\Inventory;

use App\Models\StockAdjustment;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Builds the next stock-adjustment document number for a given year.
 *
 * Format: `ADJ-YYYY-NNNN`, e.g. `ADJ-2026-0001`. Sequence resets every
 * January so the running count stays manageable across the years.
 *
 * Race-safe enough for v1.0: the surrounding `CreateStockAdjustment`
 * action runs inside a DB transaction and the `stock_adjustments.number`
 * column has a UNIQUE index — if two concurrent creates race to the
 * same number, the second one fails its INSERT and gets retried.
 */
class GenerateStockAdjustmentNumber
{
    public function __invoke(?CarbonInterface $forDate = null): string
    {
        $date = $forDate ?: Carbon::now();
        $year = (int) $date->format('Y');

        $latest = StockAdjustment::query()
            ->where('number', 'like', "ADJ-{$year}-%")
            ->orderByDesc('id')
            ->value('number');

        $seq = 1;
        if ($latest) {
            $tail = substr($latest, strrpos($latest, '-') + 1);
            $seq  = ((int) $tail) + 1;
        }

        return sprintf('ADJ-%04d-%04d', $year, $seq);
    }
}
