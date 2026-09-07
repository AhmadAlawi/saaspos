<?php

namespace App\Actions\DrugSchedules;

use App\Actions\Concerns\FreesSoftDeletedUnique;
use App\Events\DrugScheduleCreated;
use App\Models\DrugSchedule;

class CreateDrugSchedule
{
    use FreesSoftDeletedUnique;

    /** @param array<string, mixed> $data */
    public function __invoke(array $data): DrugSchedule
    {
        $data['sort_order'] ??= (DrugSchedule::max('sort_order') ?? 0) + 1;

        $data = apply_filters('drug_schedule.attributes', $data);
        do_action('drug_schedule.before_create', $data);

        // Free a deleted schedule's code so the unique index doesn't 1062.
        $this->freeSoftDeletedUnique(DrugSchedule::class, ['code' => $data['code'] ?? null]);

        $row = DrugSchedule::create($data);

        do_action('drug_schedule.after_create', $row);
        event(new DrugScheduleCreated($row));

        return $row;
    }
}
