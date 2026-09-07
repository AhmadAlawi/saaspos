<?php

namespace App\Actions\Purchases;

use App\Actions\Shifts\RecordCashDrawerEntry;
use App\Models\CashDrawerEntry;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\Shift;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Record a supplier payment — the action that finally takes the
 * receive→paid loop full-circle.
 *
 * Input shape:
 *   $header = [
 *     'supplier_id'       => int,
 *     'store_id'          => int,
 *     'payment_method_id' => int,
 *     'payment_date'      => 'YYYY-MM-DD',
 *     'amount'            => decimal-string,   // sum of allocations
 *     'reference'         => string|null,      // cheque #, UPI txn, etc.
 *     'notes'             => string|null,
 *   ]
 *   $allocations = [
 *     ['purchase_id' => int, 'amount' => decimal-string],
 *     …
 *   ]
 *
 * Per allocation (atomic inside a single transaction with
 * lockForUpdate on the supplier + each affected purchase):
 *   1. Insert a `purchase_payments` row. All rows share a `client_uuid`
 *      generated here so they're recognisable as one user action.
 *   2. Increment `purchases.paid_total` and decrement `balance_due`.
 *   3. Transition `purchases.status`:
 *        received        → partially_paid    (when paid_total > 0 && balance_due > 0)
 *        partially_paid  → paid              (when balance_due == 0)
 *        received        → paid              (full payment in one go)
 *   4. Decrement `suppliers.outstanding_balance` by the SUM of all
 *      allocations (not per-row — one supplier write per submission).
 *
 * Guards:
 *   - sum(allocations.amount) must be <= $header['amount']. Any leftover
 *     (amount - sumAlloc) lands as ONE extra row with `purchase_id = null`
 *     — that's supplier credit per feature doc §7.3, recognised by the
 *     supplier's `outstanding_balance` going negative.
 *   - Each allocation's amount must be <= the PO's current balance_due
 *     (no over-payment to a specific PO; surplus lands as unallocated).
 *   - Allocation's purchase must belong to the same supplier.
 *   - Purchase status must be in [received, partially_paid] — paid /
 *     draft / cancelled purchases are ineligible.
 *
 * Hooks:
 *   - filter `supplier_payment.fillable`     → mutate header before insert
 *   - action `supplier_payment.before_create`→ ($header, $allocations)
 *   - action `supplier_payment.after_create` → (array of inserted rows, $supplier)
 *
 * Journal posting deferred to the Accounting slice — same pattern as
 * Slice 3 ({@see ReceivePurchase}). The `supplier_payment.after_create`
 * event lets the future journal poster write Dr A/P / Cr Cash entries
 * for both new and historical payments.
 *
 * @param array<string, mixed>           $header
 * @param array<int, array<string, mixed>> $allocations
 * @return array<int, PurchasePayment>
 */
class RecordSupplierPayment
{
    public function __invoke(array $header, array $allocations, ?User $user = null): array
    {
        $header = apply_filters('supplier_payment.fillable', $header);

        // Pure-credit (no allocations) is allowed since Slice 4b — the
        // entire amount lands as one `purchase_id=null` row. The action
        // still requires a positive total amount.

        $totalAmount = (string) ($header['amount'] ?? '0');
        $sumAlloc    = array_reduce(
            $allocations,
            fn ($s, $a) => bcadd($s, (string) ($a['amount'] ?? '0'), 4),
            '0',
        );
        if (bccomp($sumAlloc, $totalAmount, 4) > 0) {
            throw new RuntimeException("Allocations sum ({$sumAlloc}) exceeds payment amount ({$totalAmount}).");
        }
        if (bccomp($totalAmount, '0', 4) <= 0) {
            throw new RuntimeException('Payment amount must be positive.');
        }

        // Any leftover after the per-PO allocations is supplier credit
        // (advance / overpayment / on-account deposit). Written as one
        // extra row with `purchase_id = null`. Per feature doc §7.3 the
        // credit is reflected by pushing `outstanding_balance` negative
        // — no separate credit column in v1.0.
        $unallocated = bcsub($totalAmount, $sumAlloc, 4);
        $hasCredit   = bccomp($unallocated, '0', 4) > 0;

        do_action('supplier_payment.before_create', $header, $allocations);

        return DB::transaction(function () use ($header, $allocations, $totalAmount, $unallocated, $hasCredit, $user) {
            $supplier = Supplier::query()
                ->whereKey((int) $header['supplier_id'])
                ->lockForUpdate()
                ->firstOrFail();

            // NOTE: we no longer pre-check `$sumAlloc <= supplier->outstanding_balance`.
            // That cached column drifts (e.g. legacy data, half-finished migrations,
            // a future receipt path that forgets to bump it) and produced false
            // positives that blocked legitimate payments. The per-PO `lockForUpdate()`
            // + per-PO `<= balance_due` checks below are the authoritative defense
            // against over-allocation: each purchase's balance_due reflects every
            // payment that has landed against it, so concurrent payments narrow the
            // per-PO budget correctly. We still decrement the cached supplier
            // outstanding at the end with bcsub for display consistency.

            $clientUuid = $header['client_uuid'] ?? (string) Str::uuid();
            $inserted = [];

            foreach ($allocations as $alloc) {
                $purchase = Purchase::query()
                    ->whereKey((int) $alloc['purchase_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $purchase->supplier_id !== (int) $supplier->id) {
                    throw new RuntimeException("Purchase {$purchase->number} doesn't belong to this supplier.");
                }
                if (! \in_array($purchase->status, [Purchase::STATUS_RECEIVED, Purchase::STATUS_PARTIALLY_PAID], true)) {
                    throw new RuntimeException("Purchase {$purchase->number} is {$purchase->status} — not eligible for payment.");
                }

                $allocAmount = (string) $alloc['amount'];
                if (bccomp($allocAmount, '0', 4) <= 0) {
                    throw new RuntimeException("Allocation to {$purchase->number} must be positive.");
                }
                if (bccomp($allocAmount, (string) $purchase->balance_due, 4) > 0) {
                    throw new RuntimeException("Allocation {$allocAmount} exceeds {$purchase->number}'s balance due of {$purchase->balance_due}.");
                }

                $row = PurchasePayment::create([
                    'purchase_id'       => $purchase->id,
                    'supplier_id'       => $supplier->id,
                    'store_id'          => (int) $header['store_id'],
                    'payment_method_id' => (int) $header['payment_method_id'],
                    'payment_date'      => $header['payment_date'],
                    'amount'            => $allocAmount,
                    'reference'         => $header['reference']  ?? null,
                    'notes'             => $header['notes']      ?? null,
                    'client_uuid'       => $clientUuid,
                    'created_by'        => $user?->id,
                ]);

                $newPaid    = bcadd((string) $purchase->paid_total,  $allocAmount, 4);
                $newBalance = bcsub((string) $purchase->balance_due, $allocAmount, 4);

                // Status transition based on the new balance.
                $newStatus = bccomp($newBalance, '0', 4) === 0
                    ? Purchase::STATUS_PAID
                    : Purchase::STATUS_PARTIALLY_PAID;

                // `forceFill()->save()` because `paid_total`,
                // `balance_due`, and `status` are non-fillable on the
                // Purchase model — only transition actions like this
                // one are allowed to write them.
                $purchase->forceFill([
                    'paid_total'  => $newPaid,
                    'balance_due' => $newBalance,
                    'status'      => $newStatus,
                    'updated_by'  => $user?->id,
                ])->save();

                $inserted[] = $row;
            }

            // Write the unallocated-credit row (if any) — same shape as
            // an allocation row but with `purchase_id = null`. The
            // method + reference are deliberately NULLED: this row
            // represents a residual balance carried on the supplier
            // account, not a real cash movement. Copying the originating
            // payment's method ("Cash") onto it mis-reads as another
            // ₹X cash outflow in the index — the credit isn't paid
            // anywhere, it's owed back to us.
            if ($hasCredit) {
                $inserted[] = PurchasePayment::create([
                    'purchase_id'       => null,
                    'supplier_id'       => $supplier->id,
                    'store_id'          => (int) $header['store_id'],
                    'payment_method_id' => null,
                    'payment_date'      => $header['payment_date'],
                    'amount'            => $unallocated,
                    'reference'         => null,
                    'notes'             => $header['notes']      ?? null,
                    'client_uuid'       => $clientUuid,
                    'created_by'        => $user?->id,
                ]);
            }

            // One supplier write per submission. `forceFill()->save()`
            // because `outstanding_balance` is non-fillable.
            //
            // Floor at zero ONLY when the payment is fully allocated. When
            // there's an unallocated remainder (supplier credit per §7.3),
            // the balance is meant to go negative — the negative reads as
            // "credit available" on the supplier card. Flooring there would
            // erase the credit. For the no-credit path we keep the floor
            // as a safety net against cache drift (per the prior fix).
            $nextOutstanding = bcsub((string) $supplier->outstanding_balance, $totalAmount, 4);
            if (! $hasCredit && bccomp($nextOutstanding, '0', 4) < 0) {
                $nextOutstanding = '0.0000';
            }
            $supplier->forceFill([
                'outstanding_balance' => $nextOutstanding,
                'updated_by'          => $user?->id,
            ])->save();

            // Till linkage: a CASH payment made during the cashier's OPEN shift
            // is money leaving the drawer, so record a pay-out against it. The
            // Z/X-report + expected-cash math (ComputeShiftTotals) already sum
            // pay-outs, so this keeps the till reconciliation honest. Non-cash
            // methods (bank / cheque / UPI) don't touch the drawer.
            $this->recordTillPayOut($header, $totalAmount, $clientUuid, $supplier, $user);

            do_action('supplier_payment.after_create', $inserted, $supplier);

            // TODO accounting-slice: post Dr A/P (one line per supplier
            // OR per PO) / Cr Cash-or-Bank journal entry. The Accounting
            // slice subscribes to `supplier_payment.after_create` and
            // backfills entries for existing payments via the
            // `purchase_payments.client_uuid` group.

            return $inserted;
        });
    }

    /**
     * Write a cash-drawer pay-out for a cash supplier payment made during the
     * cashier's open shift. No-op when: the method isn't cash, there's no user
     * (system/seed call), or no shift is open for this store+cashier — in which
     * case the payment is simply recorded with no drawer effect (per spec, a
     * cash payment outside a shift is still allowed).
     *
     * @param array<string, mixed> $header
     */
    private function recordTillPayOut(array $header, string $totalAmount, string $clientUuid, Supplier $supplier, ?User $user): void
    {
        $userId = $user?->id;
        if ($userId === null) {
            return;
        }

        $method = PaymentMethod::query()->find((int) $header['payment_method_id']);
        if (! $method || $method->type !== 'cash') {
            return;
        }

        $shift = Shift::openForCashier((int) $header['store_id'], (int) $userId);
        if (! $shift) {
            return;
        }

        (new RecordCashDrawerEntry())($shift, [
            'type'                  => CashDrawerEntry::TYPE_PAY_OUT,
            'amount'                => $totalAmount,
            'reason'                => trim(__('supplier_payments.till.pay_out_reason', ['supplier' => $supplier->name])),
            'supplier_payment_uuid' => $clientUuid,
        ], $user);
    }
}
