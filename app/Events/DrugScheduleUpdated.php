<?php

namespace App\Events;

use App\Models\DrugSchedule;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DrugScheduleUpdated
{
    use Dispatchable, SerializesModels;

    /** @param  array<string, mixed>  $original */
    public function __construct(public DrugSchedule $drugSchedule, public array $original = []) {}
}
