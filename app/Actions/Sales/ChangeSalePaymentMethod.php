<?php

namespace App\Actions\Sales;

use App\Exceptions\SalePaymentNotEditable;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SalePaymentMethodChange;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Manager-only correction to which payment method one of a sale's
 * tenders used — e.g. a cashier rang up cash but the customer actually
 * paid by card, and it wasn't caught until later. Deliberately allowed
 * even after the sale's shift has closed and its Z-report already
 * printed (unlike {@see VoidSale}, which restricts to the still-open
 * shift): "old invoice" is exactly the case this exists for.
 *
 * That's also the risk: a closed shift's Z-report is a snapshot taken
 * at close time. Changing a tender's method afterward does NOT
 * regenerate that historical report — the printed Z-report and the
 * live {@see \App\Actions\Shifts\ComputeShiftTotals} breakdown will
 * disagree on this shift's cash/card split from that point on. That's
 * accepted as the cost of correcting real errors after the fact; the
 * {@see SalePaymentMethodChange} audit row (old value, new value, who,
 * when, why) is what makes the discrepancy explainable during
 * reconciliation rather than a silent, unexplained mismatch.
 *
 * Eligibility:
 *   - The sale must not be voided (a void is a closed door; use a new
 *     sale, not a correction to a dead one).
 *   - The new payment method must be active.
 *   - The new payment method must actually differ from the current one
 *     (nothing to log, nothing to change).
 *
 * The `sale_payments` row is mutated in place — same "keep the row,
 * record what changed" shape VoidSale uses for the sale itself. Amount,
 * reference, and every other tender detail are untouched; only
 * `payment_method_id` changes.
 *
 * Hook: action `sale.payment_method_changed` → ($sale, $payment, $oldMethodId, $newMethodId, $user)
 */
class ChangeSalePaymentMethod
{
    public function __invoke(Sale $sale, SalePayment $payment, int $newPaymentMethodId, ?string $reason, User $user): SalePayment
    {
        return DB::transaction(function () use ($sale, $payment, $newPaymentMethodId, $reason, $user) {
            // Re-fetch with a lock so two clicks (or a click racing a void)
            // can't both pass the eligibility guard.
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            $payment = SalePayment::query()->lockForUpdate()->where('sale_id', $sale->id)->findOrFail($payment->id);
            $newMethod = PaymentMethod::query()->findOrFail($newPaymentMethodId);

            $this->guard($sale, $payment, $newMethod);

            $oldMethodId = $payment->payment_method_id;

            SalePaymentMethodChange::create([
                'sale_id'               => $sale->id,
                'sale_payment_id'       => $payment->id,
                'old_payment_method_id' => $oldMethodId,
                'new_payment_method_id' => $newMethod->id,
                'reason'                => $reason,
                'changed_by'            => $user->id,
                'changed_at'            => now(),
            ]);

            $payment->forceFill(['payment_method_id' => $newMethod->id])->save();

            $fresh = $payment->fresh();
            do_action('sale.payment_method_changed', $sale, $fresh, $oldMethodId, $newMethod->id, $user);

            return $fresh;
        });
    }

    private function guard(Sale $sale, SalePayment $payment, PaymentMethod $newMethod): void
    {
        if ($sale->status === Sale::STATUS_VOIDED) {
            throw new SalePaymentNotEditable($sale, 'sale_voided');
        }
        if (! $newMethod->is_active) {
            throw new SalePaymentNotEditable($sale, 'method_inactive');
        }
        if ((int) $payment->payment_method_id === (int) $newMethod->id) {
            throw new SalePaymentNotEditable($sale, 'method_unchanged');
        }
    }
}
