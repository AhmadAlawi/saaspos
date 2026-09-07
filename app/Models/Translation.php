<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A per-locale string override, layered over the `lang/*.php` file
 * defaults by {@see App\Support\Translation\DatabaseMergeLoader}.
 * `group` is the lang file name (or `*` for JSON strings); `key` is the
 * dot-path within that group (e.g. `fields.name`).
 */
class Translation extends Model
{
    protected $fillable = ['locale', 'group', 'key', 'value'];
}
