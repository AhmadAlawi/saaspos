<?php

namespace App\Events;

use App\Models\Brand;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BrandUpdated
{
    use Dispatchable, SerializesModels;

    /** @param  array<string, mixed>  $original  Pre-update column snapshot from getOriginal(). */
    public function __construct(public Brand $brand, public array $original = []) {}
}
