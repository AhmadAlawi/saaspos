<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Adds a `display_email` accessor that bullets the address on demo installs
 * and returns it untouched everywhere else — see demo_mask_email().
 *
 * Deliberately a SEPARATE attribute rather than an override of `email`: the
 * real value must still reach uniqueness rules, mail recipients and login
 * lookups. Read `display_email` on every surface that shows an address to a
 * human (blades, JSON payloads, exports); read `email` on write paths.
 */
trait MasksDemoEmail
{
    protected function displayEmail(): Attribute
    {
        return Attribute::get(fn () => demo_mask_email($this->email));
    }
}
