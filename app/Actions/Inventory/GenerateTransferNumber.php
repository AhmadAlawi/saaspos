<?php

namespace App\Actions\Inventory;

use App\Models\StockTransfer;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Builds the next stock-transfer document number for a given year.
 *
 * Format: `TRF-YYYY-NNNN`, e.g. `TRF-2026-0001`. Sequence resets every
 * January. Race-safe: the surrounding action runs inside a DB transaction
 * and the `stock_transfers.number` column has a UNIQUE index — the second
 * concurrent create fails and gets retried by the caller.
 */
class GenerateTransferNumber
{
    public function __invoke(?CarbonInterface $forDate = null): string
    {
        $date = $forDate ?: Carbon::now();
        $year = (int) $date->format('Y');

        $latest = StockTransfer::query()
            ->where('number', 'like', "TRF-{$year}-%")
            ->orderByDesc('id')
            ->value('number');

        $seq = 1;
        if ($latest) {
            $tail = substr($latest, strrpos($latest, '-') + 1);
            $seq  = ((int) $tail) + 1;
        }

        return sprintf('TRF-%04d-%04d', $year, $seq);
    }
}
