<?php

namespace App\Actions\Purchases;

use App\Actions\Shifts\RecordCashDrawerEntry;
use App\Models\CashDrawerEntry;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\Shift;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Void a recorded supplier payment — reverses the cash effect on the
 * linked purchase (if any) and the supplier outstanding, then soft-marks
 * the row so history + audit stay intact.
 *
 * Voiding ONE row of a multi-allocation submission reverses only that
 * row. The other siblings (same `client_uuid`) stand. The void is
 * idempotent-rejecting: a second attempt on an already-voided row throws.
 *
 * For an allocated row (`purchase_id` set):
 *   - `purchases.paid_total -= amount`
 *   - `purchases.balance_due += amount`
 *   - status transitions:
 *       paid           → partially_paid          (if any paid_total remains)
 *       paid           → received                (if paid_total drops to zero)
 *       partially_paid → received                (if paid_total drops to zero)
 *   - `suppliers.outstanding_balance += amount`
 *
 * For an unallocated row (`purchase_id = null` — supplier credit):
 *   - No purchase to touch.
 *   - `suppliers.outstanding_balance += amount` — winds the credit back.
 *     The balance may pass back through zero or stay negative depending
 *     on the supplier's overall position.
 *
 * Hooks:
 *   - action `supplier_payment.voided` → ($payment, $supplier, $reason)
 *     The accounting slice subscribes to this for the reversal journal.
 *
 * Permission key: `suppliers.payments_void` (policy `PurchasePaymentPolicy::void`).
 */
class VoidSupplierPayment
{
    public function __invoke(PurchasePayment $payment, ?string $reason, ?User $user = null): PurchasePayment
    {
        if ($payment->isVoided()) {
            throw new RuntimeException("Payment #{$payment->id} is already voided.");
        }

        return DB::transaction(function () use ($payment, $reason, $user) {
            // Lock the supplier first. Same order as RecordSupplierPayment
            // (supplier → purchase) — deadlock-avoidant pairing.
            $supplier = Supplier::query()
                ->whereKey((int) $payment->supplier_id)
                ->lockForUpdate()
                ->firstOrFail();

            $amount = (string) $payment->amount;

            if ($payment->purchase_id !== null) {
                $purchase = Purchase::query()
                    ->whereKey((int) $payment->purchase_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $newPaid    = bcsub((string) $purchase->paid_total,  $amount, 4);
                $newBalance = bcadd((string) $purchase->balance_due, $amount, 4);

                // Safety net — paid_total should never go negative; if a
                // prior void/reconcile left it below this row's amount we
                // floor and surface in the next status transition.
                if (bccomp($newPaid, '0', 4) < 0) {
                    $newPaid = '0.0000';
                }

                // Status walks back the same ladder Record climbed up.
                // Drops all the way to `received` only when no other
                // sibling row contributes to paid_total.
                $newStatus = bccomp($newPaid, '0', 4) === 0
                    ? Purchase::STATUS_RECEIVED
                    : Purchase::STATUS_PARTIALLY_PAID;

                $purchase->forceFill([
                    'paid_total'  => $newPaid,
                    'balance_due' => $newBalance,
                    'status'      => $newStatus,
                    'updated_by'  => $user?->id,
                ])->save();
            }

            // Supplier outstanding always winds back by the row amount —
            // both allocated and unallocated rows decremented it on
            // create, so the symmetric reverse is correct in both cases.
            $supplier->forceFill([
                'outstanding_balance' => bcadd((string) $supplier->outstanding_balance, $amount, 4),
                'updated_by'          => $user?->id,
            ])->save();

            $payment->forceFill([
                'voided_at'   => now(),
                'voided_by'   => $user?->id,
                'void_reason' => $reason,
            ])->save();

            // Till linkage: if this payment left the drawer (a pay-out was
            // recorded against its uuid), voiding puts the cash back — a pay-in
            // for this row's amount into whatever shift is open NOW (the cash
            // physically returns to the current till). If no shift is open, the
            // void still stands; there's just no drawer to credit.
            $this->reverseTillPayOut($payment, $amount, $user);

            do_action('supplier_payment.voided', $payment, $supplier, $reason);

            // TODO accounting-slice: post the REVERSAL journal — Cr A/P
            // (or supplier-credit) / Dr Cash-or-Bank. The Accounting
            // subscriber treats voided rows by their (voided_at, amount)
            // pair so historical voids are picked up on rebuild.

            return $payment->refresh();
        });
    }

    /**
     * Put cash back in the till when voiding a payment that originally left it.
     * "Originally left it" = a pay-out CashDrawerEntry carries this payment's
     * `client_uuid`. The reversing pay-in goes to the currently-open shift for
     * the payment's store + the acting user; if none is open, we skip it (the
     * void still reverses the ledger — there's just no drawer to credit).
     */
    private function reverseTillPayOut(PurchasePayment $payment, string $amount, ?User $user): void
    {
        $userId = $user?->id;
        if ($userId === null || $payment->client_uuid === null) {
            return;
        }

        $wasTillPayment = CashDrawerEntry::query()
            ->where('supplier_payment_uuid', $payment->client_uuid)
            ->where('type', CashDrawerEntry::TYPE_PAY_OUT)
            ->exists();
        if (! $wasTillPayment) {
            return;
        }

        $shift = Shift::openForCashier((int) $payment->store_id, (int) $userId);
        if (! $shift) {
            return;
        }

        (new RecordCashDrawerEntry())($shift, [
            'type'                  => CashDrawerEntry::TYPE_PAY_IN,
            'amount'                => $amount,
            'reason'                => trim(__('supplier_payments.till.void_pay_in_reason')),
            'supplier_payment_uuid' => $payment->client_uuid,
        ], $user);
    }
}
