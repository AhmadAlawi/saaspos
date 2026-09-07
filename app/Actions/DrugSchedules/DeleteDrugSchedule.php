<?php

namespace App\Actions\DrugSchedules;

use App\Events\DrugScheduleDeleted;
use App\Models\DrugSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Soft-delete a drug schedule. Products still tagged with its `code` in
 * `products.pharmacy_schedule` are reassigned first, so the delete never
 * orphans compliance metadata:
 *
 *   - if `$replacementId` is given (an existing schedule, not the row
 *     being deleted), every live product is moved onto the replacement's
 *     code before the delete.
 *   - otherwise the schedule is simply cleared from those products
 *     (they become "unscheduled") — there is no system-default schedule.
 *
 * Extension points:
 *   - action `drug_schedule.before_delete` → fires before soft-delete
 *   - action `drug_schedule.after_delete`  → fires after soft-delete
 *   - event  DrugScheduleDeleted           → decoupled listeners
 */
class DeleteDrugSchedule
{
    /** Public side-effects from the last invocation. */
    public int $movedCount = 0;
    public ?string $targetName = null;

    public function __invoke(DrugSchedule $row, ?int $replacementId = null): void
    {
        $target = $this->resolveTarget($row, $replacementId);

        $this->movedCount = $this->moveProducts($row, $target);
        $this->targetName = $target?->name;

        do_action('drug_schedule.before_delete', $row);

        $row->delete();

        do_action('drug_schedule.after_delete', $row);
        event(new DrugScheduleDeleted($row));
    }

    /**
     * Resolve the schedule to move linked products onto. A blank /
     * self / unknown replacement means "clear the schedule" (null target).
     */
    private function resolveTarget(DrugSchedule $row, ?int $replacementId): ?DrugSchedule
    {
        if (! $replacementId || $replacementId === $row->id) {
            return null;
        }

        return DrugSchedule::query()->whereKey($replacementId)->first();
    }

    /**
     * Re-tag every live product carrying this schedule's code. When
     * `$target` is null the field is cleared. Returns the rows moved.
     */
    private function moveProducts(DrugSchedule $row, ?DrugSchedule $target): int
    {
        if (! Schema::hasTable('products') || ! $row->code) {
            return 0;
        }

        return DB::table('products')
            ->where('pharmacy_schedule', $row->code)
            ->whereNull('deleted_at')
            ->update([
                'pharmacy_schedule' => $target?->code,
                'updated_at'        => now(),
            ]);
    }

    /**
     * Count of live products still tagged with this schedule's code. Used
     * by the controller's delete-info probe to drive the "move products
     * to…" dialog before the destroy fires.
     */
    public static function liveProductCount(DrugSchedule $row): int
    {
        if (! Schema::hasTable('products') || ! $row->code) {
            return 0;
        }

        return DB::table('products')
            ->where('pharmacy_schedule', $row->code)
            ->whereNull('deleted_at')
            ->count();
    }
}
