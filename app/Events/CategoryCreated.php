<?php

namespace App\Events;

use App\Models\Category;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fires once a new category has been persisted. Dispatched by
 * {@see App\Actions\Categories\CreateCategory}.
 *
 * Add listeners via {@see App\Providers\EventServiceProvider} or
 * Laravel's auto-discovery (`__invoke(CategoryCreated $event)`).
 */
class CategoryCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Category $category) {}
}
