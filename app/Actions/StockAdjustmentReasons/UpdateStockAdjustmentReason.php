<?php

namespace App\Actions\StockAdjustmentReasons;

use App\Models\StockAdjustmentReason;

class UpdateStockAdjustmentReason
{
    /** @param array<string, mixed> $data */
    public function __invoke(StockAdjustmentReason $row, array $data): StockAdjustmentReason
    {
        $data = apply_filters('stock_adjustment_reason.attributes', $data, $row);
        do_action('stock_adjustment_reason.before_update', $row, $data);

        $row->update($data);

        do_action('stock_adjustment_reason.after_update', $row);

        return $row;
    }
}
