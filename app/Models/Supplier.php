<?php

namespace App\Models;

use App\Models\Concerns\MasksDemoEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Supplier — who we buy from. Company-level (visible across stores)
 * since most small businesses use the same suppliers everywhere.
 *
 * `outstanding_balance` is maintained from `purchases.balance_due` minus
 * unallocated supplier payments. In this slice (suppliers CRUD only) it
 * defaults to 0 — Purchases slice wires the live increments and the
 * daily reconciliation job.
 *
 * Multi-currency: `default_currency_code` is the supplier's invoicing
 * currency. v1.0 tracks outstanding in this single currency; v1.1 will
 * support per-currency outstanding (see `docs/features/suppliers-purchases.md` §19.2).
 *
 * See `docs/features/suppliers-purchases.md` for the full spec.
 */
class Supplier extends Model
{
    use MasksDemoEmail, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'business_name', 'contact_person',
        'email', 'phone',
        'gstin', 'tax_registration_number', 'pan',
        'default_currency_code', 'payment_terms_days',
        'address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country_code',
        'notes', 'is_active',
        'created_by', 'updated_by',
        // `outstanding_balance` is DELIBERATELY non-fillable. It's owned
        // by ReceivePurchase / RecordSupplierPayment / future Return
        // actions and must only be touched via `$supplier->forceFill(...)`
        // inside a locked transaction. Treating it as fillable would
        // let a form submit silently rewrite the supplier's books.
    ];

    protected function casts(): array
    {
        return [
            'is_active'           => 'boolean',
            'outstanding_balance' => 'decimal:4',
            'payment_terms_days'  => 'integer',
        ];
    }

    /** @param Builder<Supplier> $q */
    public function scopeActive(Builder $q): void
    {
        $q->where('is_active', true);
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

    /** @return HasMany<Purchase, $this> */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /** @return HasMany<PurchasePayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class)->orderByDesc('payment_date')->orderByDesc('id');
    }

    // Relations deferred until their slice lands:
    //   returns()            → Purchase-returns slice
    //   defaultCurrency()    → when a Currency model is introduced (currently the code is a plain string)
}
