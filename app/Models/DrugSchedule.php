<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pharmacy drug schedule lookup. The list previously lived as a
 * static constant on ProductRequest — moved to a table so each
 * customer install can mirror the regulatory regime of their country
 * (India: H/H1/X/OTC, US: Schedule II/III/IV/V, UK: P/POM/CD…).
 */
class DrugSchedule extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'country_code',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @param Builder<DrugSchedule> $q */
    public function scopeActive(Builder $q): void
    {
        $q->where('is_active', true);
    }

    /** @param Builder<DrugSchedule> $q */
    public function scopeOrdered(Builder $q): void
    {
        $q->orderBy('sort_order')->orderBy('code');
    }
}
