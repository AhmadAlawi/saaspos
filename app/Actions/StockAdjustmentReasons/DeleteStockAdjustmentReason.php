<?php

namespace App\Actions\StockAdjustmentReasons;

use App\Exceptions\StockAdjustmentReasonInUse;
use App\Models\StockAdjustmentReason;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Delete an adjustment reason. Blocks when any stock_adjustments row
 * still references it — the user is offered the option to deactivate
 * instead (the picklist hides inactive rows but history keeps showing
 * them via the `reason_code_id` foreign key).
 */
class DeleteStockAdjustmentReason
{
    public function __invoke(StockAdjustmentReason $row): void
    {
        $this->assertNotInUse($row);

        do_action('stock_adjustment_reason.before_delete', $row);

        $row->delete();

        do_action('stock_adjustment_reason.after_delete', $row);
    }

    private function assertNotInUse(StockAdjustmentReason $row): void
    {
        if (! Schema::hasTable('stock_adjustments')) {
            return;
        }

        $count = DB::table('stock_adjustments')->where('reason_code_id', $row->id)->count();
        if ($count > 0) {
            throw new StockAdjustmentReasonInUse($count);
        }
    }
}
