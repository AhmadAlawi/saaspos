<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pay-in / pay-out / drawer-open-no-sale event against an open shift.
 *
 * Slice 1 — model only; the cashier-side UI for recording entries lands
 * with the cash-drawer slice. The Z-report sums these so the shift's
 * expected cash math stays right even when the UI is shipped.
 */
class CashDrawerEntry extends Model
{
    public const TYPE_PAY_IN              = 'pay_in';
    public const TYPE_PAY_OUT             = 'pay_out';
    public const TYPE_DRAWER_OPEN_NO_SALE = 'drawer_open_no_sale';

    public $timestamps = false;

    protected $fillable = [
        'shift_id', 'type', 'amount', 'reason', 'expense_id', 'supplier_payment_uuid', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount'     => 'decimal:4',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Shift, $this> */
    public function shift(): BelongsTo { return $this->belongsTo(Shift::class); }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
