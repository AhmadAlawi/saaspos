<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A locale the install offers (docs/features/multi-language.md §2).
 * English is seeded as the default and can't be deleted. `direction`
 * drives the layout's `dir` attribute for RTL languages.
 */
class Language extends Model
{
    protected $fillable = [
        'code', 'name', 'native_name', 'direction', 'is_active', 'is_default', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function isRtl(): bool
    {
        return $this->direction === 'rtl';
    }

    /** @param Builder<Language> $q */
    public function scopeActive(Builder $q): void
    {
        $q->where('is_active', true);
    }

    /** @param Builder<Language> $q */
    public function scopeOrdered(Builder $q): void
    {
        $q->orderByDesc('is_default')->orderBy('sort_order')->orderBy('name');
    }
}
