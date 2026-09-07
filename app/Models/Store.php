<?php

namespace App\Models;

use App\Models\Concerns\MasksDemoEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Physical location of the company. One install = one company; the
 * company has N stores (a retail chain's branches, a supermarket's
 * city outlets, etc.). v1.0 ships a minimal Store model — full
 * store-admin UI lands with the multi-store rollout.
 *
 * For now this exists so per-store features (price overrides, stock,
 * stuff like receipt templates) can reference stores cleanly via
 * Eloquent without each call site rolling its own DB lookup.
 */
class Store extends Model
{
    use MasksDemoEmail, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country_code',
        'phone',
        'email',
        'timezone',
        'currency_code',
        'locale',
        'rounding_mode',
        'receipt_template_id',
        'enforce_shifts',
        'require_day_open',
        'discount_threshold_percent',
        'tax_inclusive_pricing',
        'cash_variance_tolerance',
        'pay_out_threshold',
        'settings',
        'is_active',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'enforce_shifts'             => 'boolean',
            'require_day_open'           => 'boolean',
            'discount_threshold_percent' => 'decimal:2',
            'tax_inclusive_pricing'      => 'boolean',
            'cash_variance_tolerance'    => 'decimal:4',
            'pay_out_threshold'       => 'decimal:4',
            'settings'                => 'array',
            'is_active'               => 'boolean',
            'is_default'              => 'boolean',
        ];
    }

    /**
     * Non-empty address lines (street, then city/state/postal on one
     * line), for receipt/report headers. A real accessor rather than a
     * per-view `@php` block — several receipt views used to compute this
     * inline and one (shifts/z-report-thermal.blade.php) hit an
     * "Undefined variable" error under Blade for a store with every
     * address field null. Centralizing it here as a normal model
     * attribute sidesteps that entirely.
     *
     * @return Attribute<array<int, string>, never>
     */
    protected function addressLines(): Attribute
    {
        return Attribute::make(
            get: fn () => array_values(array_filter([
                trim((string) ($this->address_line1 ?? '')),
                trim((string) ($this->address_line2 ?? '')),
                trim(implode(', ', array_filter([
                    $this->city ?? null,
                    $this->state ?? null,
                    $this->postal_code ?? null,
                ]))),
            ])),
        );
    }

    /** @param Builder<Store> $q */
    public function scopeActive(Builder $q): void
    {
        $q->where('is_active', true);
    }

    /** @param Builder<Store> $q */
    public function scopeOrdered(Builder $q): void
    {
        $q->orderBy('name');
    }

    /**
     * Users assigned to this store, each carrying their per-store role.
     *
     * @return BelongsToMany<\App\Models\User>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'store_user')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    /**
     * Checkout terminals (tills / workstations) belonging to this store.
     * Terminals are managed storewise, so this is the canonical entry
     * point for a store's terminals.
     *
     * @return HasMany<Terminal>
     */
    public function terminals(): HasMany
    {
        return $this->hasMany(Terminal::class);
    }

    /** @return BelongsTo<ReceiptTemplate, $this> */
    public function receiptTemplate(): BelongsTo
    {
        return $this->belongsTo(ReceiptTemplate::class);
    }
}
