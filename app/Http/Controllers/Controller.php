<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Base controller every concrete controller extends. The
 * AuthorizesRequests trait gives child controllers access to
 * `authorize()` and `authorizeResource()` so policy gates can be
 * declared inline rather than through `Gate::authorize()` everywhere.
 */
abstract class Controller
{
    use AuthorizesRequests;
}
