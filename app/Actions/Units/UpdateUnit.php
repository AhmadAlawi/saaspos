<?php

namespace App\Actions\Units;

use App\Events\UnitUpdated;
use App\Models\Unit;

/**
 * Update an existing unit.
 *
 * Extension points:
 *   - filter `unit.attributes`        → modify the attribute array (shared with create)
 *   - action `unit.before_update`     → side-effects before save; receives ($unit, $data)
 *   - action `unit.after_update`      → side-effects after save;  receives ($unit, $original)
 *   - event  UnitUpdated              → decoupled listeners
 */
class UpdateUnit
{
    /** @param  array<string, mixed>  $data  Already-validated payload from UnitRequest. */
    public function __invoke(Unit $unit, array $data): Unit
    {
        $original = $unit->getOriginal();

        if (empty($data['base_unit_id'])) {
            $data['base_unit_id']      = null;
            $data['conversion_factor'] = null;
        }

        $data = apply_filters('unit.attributes', $data, $unit);
        do_action('unit.before_update', $unit, $data);

        $unit->update($data);

        do_action('unit.after_update', $unit, $original);
        event(new UnitUpdated($unit, $original));

        return $unit;
    }
}
