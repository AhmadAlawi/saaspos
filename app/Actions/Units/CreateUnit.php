<?php

namespace App\Actions\Units;

use App\Actions\Concerns\FreesSoftDeletedUnique;
use App\Events\UnitCreated;
use App\Models\Unit;

/**
 * Persist a new unit.
 *
 * Extension points:
 *   - filter `unit.attributes`        → modify the attribute array
 *   - action `unit.before_create`     → side-effects before insert
 *   - action `unit.after_create`      → side-effects after insert
 *   - event  UnitCreated              → decoupled listeners
 */
class CreateUnit
{
    use FreesSoftDeletedUnique;

    /** @param  array<string, mixed>  $data  Already-validated payload from UnitRequest. */
    public function __invoke(array $data): Unit
    {
        // A base unit (no base_unit_id) doesn't have a conversion factor.
        if (empty($data['base_unit_id'])) {
            $data['base_unit_id']      = null;
            $data['conversion_factor'] = null;
        }

        $data = apply_filters('unit.attributes', $data);
        do_action('unit.before_create', $data);

        // Free a deleted unit's code so the unique index doesn't 1062.
        $this->freeSoftDeletedUnique(Unit::class, ['code' => $data['code'] ?? null]);

        $unit = Unit::create($data);

        do_action('unit.after_create', $unit);
        event(new UnitCreated($unit));

        return $unit;
    }
}
