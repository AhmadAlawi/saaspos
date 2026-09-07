<?php

namespace App\Events;

use App\Models\DrugSchedule;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DrugScheduleCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public DrugSchedule $drugSchedule) {}
}
