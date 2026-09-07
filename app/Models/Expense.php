<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A business expense — money paid out (rent, utilities, supplies, …).
 * The drawer's cash-out side. `number` is auto-generated per store;
 * `status` defaults to `approved` (the approval workflow + recurring +
 * journal posting are deferred). Money is DECIMAL(15,4) end-to-end.
 */
class Expense extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /** User-editable fields. `number`, `status`, audit cols are forceFilled. */
    protected $fillable = [
        'category_id',
        'expense_date',
        'amount',
        'tax_amount',
        'payment_method_id',
        'supplier_id',
        'reference',
        'description',
        'receipt_image_path',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount'       => 'decimal:4',
            'tax_amount'   => 'decimal:4',
            'is_recurring' => 'boolean',
            'approved_at'  => 'datetime',
        ];
    }

    /** Total cash leaving the drawer for this expense. */
    public function getTotalAttribute(): string
    {
        return bcadd((string) ($this->amount ?? '0'), (string) ($this->tax_amount ?? '0'), 4);
    }

    /* ── Relationships ──────────────────────────────────────────── */

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }

    /** @return BelongsTo<ExpenseCategory, $this> */
    public function category(): BelongsTo { return $this->belongsTo(ExpenseCategory::class, 'category_id'); }

    /** @return BelongsTo<PaymentMethod, $this> */
    public function paymentMethod(): BelongsTo { return $this->belongsTo(PaymentMethod::class); }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    /* ── Scopes ─────────────────────────────────────────────────── */

    /** @param Builder<Expense> $q */
    public function scopeForStore(Builder $q, int $storeId): void { $q->where('store_id', $storeId); }

    /** @param Builder<Expense> $q */
    public function scopeOrdered(Builder $q): void { $q->orderByDesc('expense_date')->orderByDesc('id'); }
}
