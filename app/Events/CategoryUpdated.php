<?php

namespace App\Events;

use App\Models\Category;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fires after a category save. `original` is the attribute snapshot
 * from before the update, so listeners can diff (e.g. detect a
 * parent_id change for audit logs).
 */
class CategoryUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param array<string, mixed> $original
     */
    public function __construct(
        public Category $category,
        public array $original = [],
    ) {}
}
