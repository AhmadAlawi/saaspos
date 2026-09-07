<?php

namespace App\Actions\Units;

use App\Events\UnitDeleted;
use App\Exceptions\UnitHasProducts;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Soft-delete a unit. Refuses to delete a unit that is still referenced
 * by either:
 *   - live products (products.unit_id) — schema is restrictOnDelete; a
 *     hard delete would 500. Surface a friendly 422 first.
 *   - other (derived) units that point at it via base_unit_id — would
 *     leave orphaned conversion factors.
 *
 * Extension points:
 *   - action `unit.before_delete`     → fires before soft-delete
 *   - action `unit.after_delete`      → fires after soft-delete
 *   - event  UnitDeleted              → decoupled listeners
 */
class DeleteUnit
{
    public function __invoke(Unit $unit): void
    {
        $this->assertNoLiveDependents($unit);

        do_action('unit.before_delete', $unit);

        $unit->delete();

        do_action('unit.after_delete', $unit);
        event(new UnitDeleted($unit));
    }

    private function assertNoLiveDependents(Unit $unit): void
    {
        if (Schema::hasTable('products')) {
            $count = DB::table('products')
                ->where('unit_id', $unit->id)
                ->whereNull('deleted_at')
                ->count();

            if ($count > 0) {
                throw new UnitHasProducts($count, 'product');
            }
        }

        $derived = Unit::query()
            ->where('base_unit_id', $unit->id)
            ->whereKeyNot($unit->id)
            ->count();

        if ($derived > 0) {
            throw new UnitHasProducts($derived, 'unit');
        }
    }
}
