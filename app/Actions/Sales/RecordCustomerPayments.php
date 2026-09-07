<?php

namespace App\Actions\Sales;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Multi-sale customer payment — the canonical "walk-in to settle the
 * tab" flow. Plural sibling of {@see RecordCustomerPayment} (single
 * sale); shape mirrors {@see \App\Actions\Purchases\RecordSupplierPayment}
 * so the mental model carries across.
 *
 * Input shape:
 *   $header = [
 *     'customer_id'       => int,
 *     'payment_method_id' => int,
 *     'payment_date'      => 'YYYY-MM-DD',
 *     'amount'            => decimal-string,        // total amount tendered
 *     'reference'         => string|null,           // cheque #, UPI txn, etc.
 *     'notes'             => string|null,
 *   ]
 *   $allocations = [
 *     ['sale_id' => int, 'amount' => decimal-string],
 *     …
 *   ]
 *
 * Per allocation (atomic, single transaction with lockForUpdate on
 * the customer + each affected sale):
 *   1. Insert a `sale_payments` row tied to the sale. All rows share
 *      a `client_uuid` generated here so they're recognisable as one
 *      user action.
 *   2. Increment `sales.paid_total` + decrement `sales.balance_due`.
 *   3. Decrement `customers.outstanding_balance` by the SUM of all
 *      allocations + the unallocated remainder (one customer write
 *      per submission).
 *
 * Guards:
 *   - sum(allocations.amount) must be <= $header['amount']. Leftover
 *     (amount - sumAlloc) lands as ONE row with `sale_id = null` →
 *     customer credit (`outstanding_balance` goes negative).
 *   - Each allocation's amount must be <= the sale's current balance_due.
 *   - Allocation's sale must belong to the same customer.
 *   - Sale must have a positive balance_due (already-settled / refunded
 *     sales are ineligible).
 *
 * Hooks:
 *   - filter `customer_payment.fillable`     → mutate header before insert
 *   - action `customer_payment.before_create`→ ($header, $allocations)
 *   - action `customer_payment.after_create` → (array of inserted rows, $customer)
 *
 * @param array<string, mixed>           $header
 * @param array<int, array<string, mixed>> $allocations
 * @return array<int, SalePayment>
 */
class RecordCustomerPayments
{
    public function __invoke(array $header, array $allocations, ?User $user = null): array
    {
        $header = apply_filters('customer_payment.fillable', $header);

        $totalAmount = $this->fmt((string) ($header['amount'] ?? '0'));
        if (bccomp($totalAmount, '0', 4) <= 0) {
            throw new RuntimeException('Payment amount must be positive.');
        }

        $sumAlloc = array_reduce(
            $allocations,
            fn ($carry, $row) => bcadd($carry, $this->fmt((string) ($row['amount'] ?? '0')), 4),
            '0',
        );
        if (bccomp($sumAlloc, $totalAmount, 4) > 0) {
            throw new RuntimeException(
                "Allocations ({$sumAlloc}) cannot exceed payment amount ({$totalAmount})."
            );
        }
        $unallocated = bcsub($totalAmount, $sumAlloc, 4);
        $hasCredit   = bccomp($unallocated, '0', 4) > 0;

        do_action('customer_payment.before_create', $header, $allocations);

        return DB::transaction(function () use ($header, $allocations, $totalAmount, $unallocated, $hasCredit, $user) {
            $customer = Customer::query()
                ->whereKey((int) $header['customer_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $clientUuid = $header['client_uuid'] ?? (string) Str::uuid();
            $inserted   = [];

            foreach ($allocations as $alloc) {
                $sale = Sale::query()
                    ->whereKey((int) $alloc['sale_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $sale->customer_id !== (int) $customer->id) {
                    throw new RuntimeException("Sale {$sale->number} doesn't belong to this customer.");
                }
                if (bccomp((string) $sale->balance_due, '0', 4) <= 0) {
                    throw new RuntimeException("Sale {$sale->number} has no outstanding balance.");
                }

                $allocAmount = $this->fmt((string) $alloc['amount']);
                if (bccomp($allocAmount, '0', 4) <= 0) {
                    throw new RuntimeException("Allocation to {$sale->number} must be positive.");
                }
                if (bccomp($allocAmount, (string) $sale->balance_due, 4) > 0) {
                    throw new RuntimeException(
                        "Allocation {$allocAmount} exceeds {$sale->number}'s balance due of {$sale->balance_due}."
                    );
                }

                $row = SalePayment::create([
                    'sale_id'           => $sale->id,
                    'customer_id'       => $customer->id,
                    'payment_method_id' => (int) $header['payment_method_id'],
                    'amount'            => $allocAmount,
                    'reference'         => $header['reference'] ?? null,
                    'currency_code'     => (string) $sale->currency_code,
                    'client_uuid'       => $clientUuid,
                    'notes'             => $header['notes'] ?? null,
                    'paid_at'           => $header['payment_date'] ?? now(),
                    'created_by'        => $user?->id,
                ]);

                $newPaid    = bcadd((string) $sale->paid_total,  $allocAmount, 4);
                $newBalance = bcsub((string) $sale->balance_due, $allocAmount, 4);

                $sale->forceFill([
                    'paid_total'  => $newPaid,
                    'balance_due' => $newBalance,
                ])->save();

                $inserted[] = $row;
            }

            // Unallocated remainder = customer credit. One row with
            // sale_id=null; customer.outstanding_balance gets pushed
            // negative once we account for it below.
            if ($hasCredit) {
                $inserted[] = SalePayment::create([
                    'sale_id'           => null,
                    'customer_id'       => $customer->id,
                    'payment_method_id' => (int) $header['payment_method_id'],
                    'amount'            => $unallocated,
                    'reference'         => $header['reference'] ?? null,
                    'currency_code'     => null,
                    'client_uuid'       => $clientUuid,
                    'notes'             => $header['notes'] ?? null,
                    'paid_at'           => $header['payment_date'] ?? now(),
                    'created_by'        => $user?->id,
                ]);
            }

            // Customer outstanding goes down by the TOTAL tendered (the
            // unallocated portion lands as negative balance = credit).
            $customer->forceFill([
                'outstanding_balance' => bcsub(
                    (string) ($customer->outstanding_balance ?? '0'),
                    $totalAmount,
                    4,
                ),
            ])->save();

            do_action('customer_payment.after_create', $inserted, $customer->fresh());

            return $inserted;
        });
    }

    private function fmt(string $v): string
    {
        if ($v === '' || $v === '-' || $v === '.') return '0.0000';
        return bcadd($v, '0', 4);
    }
}
