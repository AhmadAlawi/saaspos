<?php

namespace App\Actions\StockAdjustmentReasons;

use App\Models\StockAdjustmentReason;

class CreateStockAdjustmentReason
{
    /** @param array<string, mixed> $data */
    public function __invoke(array $data): StockAdjustmentReason
    {
        $data = apply_filters('stock_adjustment_reason.attributes', $data);
        do_action('stock_adjustment_reason.before_create', $data);

        $row = StockAdjustmentReason::create($data);

        do_action('stock_adjustment_reason.after_create', $row);

        return $row;
    }
}
