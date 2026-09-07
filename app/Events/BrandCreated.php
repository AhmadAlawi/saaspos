<?php

namespace App\Events;

use App\Models\Brand;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BrandCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Brand $brand) {}
}
