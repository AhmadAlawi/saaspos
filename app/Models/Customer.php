<?php

namespace App\Models;

use App\Models\Concerns\MasksDemoEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Customer record — who's buying. Company-level (visible across stores),
 * with the first store of contact recorded for attribution reporting.
 *
 * v1.0 MVP fields. The derived balances (`outstanding_balance`,
 * `store_credit_balance`, `loyalty_points`) are kept on the row for
 * performance but reconciled from `customer_credit_transactions` by a
 * daily job once Sales lands. In this slice they default to 0.
 *
 * See `docs/features/customers.md` for the full spec.
 */
class Customer extends Model
{
    use MasksDemoEmail, SoftDeletes;

    protected $fillable = [
        'code', 'name',
        'is_business', 'business_name',
        'email', 'phone', 'whatsapp_phone', 'whatsapp_opt_out',
        'dob', 'gender',
        'gstin', 'pan', 'tax_registration_number',
        'customer_group_id', 'default_discount_percent',
        'credit_limit', 'outstanding_balance', 'store_credit_balance', 'loyalty_points',
        'first_store_id', 'meta', 'notes', 'is_active',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_business'              => 'boolean',
            'whatsapp_opt_out'         => 'boolean',
            'is_active'                => 'boolean',
            'dob'                      => 'date',
            'default_discount_percent' => 'decimal:4',
            'credit_limit'             => 'decimal:4',
            'outstanding_balance'      => 'decimal:4',
            'store_credit_balance'     => 'decimal:4',
            'loyalty_points'           => 'integer',
            'meta'                     => 'array',
        ];
    }

    /** @param Builder<Customer> $q */
    public function scopeActive(Builder $q): void
    {
        $q->where('is_active', true);
    }

    /** @return BelongsTo<CustomerGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    /** @return BelongsTo<Store, $this> */
    public function firstStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'first_store_id');
    }

    /** @return HasMany<CustomerAddress, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /** The default address row, or null if none flagged. */
    public function defaultAddress(): ?CustomerAddress
    {
        return $this->addresses()->where('is_default', true)->first();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
