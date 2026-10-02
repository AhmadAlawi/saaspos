<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * The ledger backing `customers.store_credit_balance` and
 * `customers.loyalty_points` — those two columns are cached rollups
 * for display; this table is the source of truth for how they got
 * there. The table (`customer_credit_transactions`) predates this
 * model — it shipped with the customers migration but was never given
 * an Eloquent class until the loyalty-points feature needed one.
 *
 * `reference_type`/`reference_id` are plain string/int columns, not
 * Eloquent's default `{name}able_type`/`{name}able_id` convention —
 * `reference()` below points `morphTo()` at the actual column names.
 */
class CustomerCreditTransaction extends Model
{
    public const UPDATED_AT = null;

    public const TYPE_LOYALTY_EARN   = 'loyalty_earn';
    public const TYPE_LOYALTY_REDEEM = 'loyalty_redeem';

    protected $fillable = [
        'customer_id', 'store_id', 'type',
        'amount', 'points',
        'balance_after_amount', 'balance_after_points',
        'reference_type', 'reference_id',
        'expires_at', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount'                => 'decimal:4',
            'points'                => 'integer',
            'balance_after_amount'  => 'decimal:4',
            'balance_after_points'  => 'integer',
            'expires_at'            => 'datetime',
            'created_at'            => 'datetime',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The Sale/etc. this movement is tied to, if any. */
    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }
}
