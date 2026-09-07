<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fires after a successful export. `format` is the file extension
 * ('csv' or 'xlsx'); `count` is the number of category rows written.
 * Listeners can use this for audit logging, usage stats, etc.
 */
class CategoriesExported
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $format,
        public int    $count,
    ) {}
}
