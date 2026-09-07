<?php

namespace App\Events;

use App\Models\Unit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UnitDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public Unit $unit) {}
}
