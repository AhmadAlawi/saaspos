<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * An individual tax line (rate + accounting attribution). Multiple
 * components join into a {@see TaxGroup} via the pivot table
 * `tax_group_components`.
 */
class TaxComponent extends Model
{
    protected $fillable = [
        'code',
        'name',
        'rate',
        'accounting_account_id',
        'accounting_input_account_id',
        'is_recoverable',
        'is_reverse_chargeable',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate'                  => 'decimal:4',
            'is_recoverable'        => 'boolean',
            'is_reverse_chargeable' => 'boolean',
            'is_active'             => 'boolean',
        ];
    }

    /** Tax groups this component participates in. */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(TaxGroup::class, 'tax_group_components')
            ->withPivot(['sort_order', 'valid_from', 'valid_to']);
    }

    /** @param Builder<TaxComponent> $q */
    public function scopeActive(Builder $q): void
    {
        $q->where('is_active', true);
    }

    /** @param Builder<TaxComponent> $q */
    public function scopeOrdered(Builder $q): void
    {
        $q->orderBy('name');
    }
}
