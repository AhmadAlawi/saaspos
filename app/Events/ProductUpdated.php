<?php

namespace App\Events;

use App\Models\Product;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductUpdated
{
    use Dispatchable, SerializesModels;

    /** @param  array<string, mixed>  $original  Pre-update column snapshot from getOriginal(). */
    public function __construct(public Product $product, public array $original = []) {}
}
