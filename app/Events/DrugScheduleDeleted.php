<?php

namespace App\Events;

use App\Models\DrugSchedule;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DrugScheduleDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public DrugSchedule $drugSchedule) {}
}
