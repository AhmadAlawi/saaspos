<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One of N addresses per customer (Home, Office, Billing, Shipping…).
 * Exactly one row per customer may have `is_default = true`; the
 * controller layer enforces that on save.
 *
 * Used for receipt header (when applicable), GSTIN interstate logic
 * (the customer's state vs the store's state determines IGST vs
 * CGST+SGST), and future delivery routing.
 */
class CustomerAddress extends Model
{
    protected $fillable = [
        'customer_id', 'label',
        'line1', 'line2', 'city', 'state', 'postal_code', 'country_code', 'landmark',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
