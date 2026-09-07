<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaxComponentsExported
{
    use Dispatchable, SerializesModels;

    public function __construct(public string $format, public int $count) {}
}
