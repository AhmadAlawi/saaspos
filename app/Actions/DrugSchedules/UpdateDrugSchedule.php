<?php

namespace App\Actions\DrugSchedules;

use App\Events\DrugScheduleUpdated;
use App\Models\DrugSchedule;

class UpdateDrugSchedule
{
    /** @param array<string, mixed> $data */
    public function __invoke(DrugSchedule $row, array $data): DrugSchedule
    {
        $original = $row->getOriginal();

        $data = apply_filters('drug_schedule.attributes', $data, $row);
        do_action('drug_schedule.before_update', $row, $data);

        $row->update($data);

        do_action('drug_schedule.after_update', $row, $original);
        event(new DrugScheduleUpdated($row, $original));

        return $row;
    }
}
