<?php

namespace App\Events;

use App\Models\Unit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UnitUpdated
{
    use Dispatchable, SerializesModels;

    /** @param  array<string, mixed>  $original  Pre-update column snapshot from getOriginal(). */
    public function __construct(public Unit $unit, public array $original = []) {}
}
