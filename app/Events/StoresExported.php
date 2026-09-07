<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class StoresExported
{
    use Dispatchable;

    public function __construct(
        public string $format,
        public int $count,
    ) {}
}
