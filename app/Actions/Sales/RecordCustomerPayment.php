<?php

namespace App\Actions\Sales;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Record a customer payment against a single open sale — closes the
 * credit-sale loop. Slice 6a only handles single-sale allocations;
 * Slice 6b adds a multi-sale CustomerPayment surface mirroring
 * SupplierPayment.
 *
 * Input:
 *   $data = [
 *     'payment_method_id' => int,
 *     'amount'            => decimal-string,   // <= sale.balance_due
 *     'reference'         => ?string,
 *     'notes'             => ?string,
 *     'paid_at'           => ?datetime,        // defaults to now()
 *   ]
 *
 * Atomic transaction:
 *   1. Lock the sale row + the customer row.
 *   2. Validate the sale has an outstanding balance.
 *   3. Validate the amount doesn't overpay the balance.
 *   4. Insert a `sale_payments` row tied to the sale.
 *   5. Bump `sales.paid_total`, decrement `sales.balance_due`.
 *   6. Decrement `customers.outstanding_balance` by the same amount.
 *
 * Hooks:
 *   - action `sale_payment.recorded` → ($payment, $sale)
 *
 * @param array<string, mixed> $data
 */
class RecordCustomerPayment
{
    public function __invoke(Sale $sale, array $data, User $user): SalePayment
    {
        return DB::transaction(function () use ($sale, $data, $user) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);

            if (bccomp((string) $sale->balance_due, '0', 4) <= 0) {
                throw new RuntimeException(__('sales.errors.sale_not_open_for_payment'));
            }

            $amount = $this->fmt((string) ($data['amount'] ?? '0'));
            if (bccomp($amount, '0', 4) <= 0) {
                throw new RuntimeException(__('sales.errors.refund_qty_zero'));
            }
            if (bccomp($amount, (string) $sale->balance_due, 4) > 0) {
                throw new RuntimeException(__('sales.errors.overpay_balance', [
                    'paid'    => format_money($amount),
                    'balance' => format_money($sale->balance_due),
                ]));
            }

            $payment = SalePayment::create([
                'sale_id'           => (int) $sale->id,
                'payment_method_id' => (int) $data['payment_method_id'],
                'amount'            => $amount,
                'reference'         => $data['reference'] ?? null,
                'currency_code'     => (string) $sale->currency_code,
                'paid_at'           => $data['paid_at'] ?? now(),
                'created_by'        => (int) $user->id,
            ]);

            // Bump sale totals.
            $newPaid = bcadd((string) $sale->paid_total, $amount, 4);
            $newBal  = bcsub((string) $sale->grand_total, $newPaid, 4);
            $sale->forceFill([
                'paid_total'  => $newPaid,
                'balance_due' => $newBal,
            ])->save();

            // Bump customer outstanding balance down.
            if ($sale->customer_id) {
                $customer = Customer::query()->lockForUpdate()->find($sale->customer_id);
                if ($customer) {
                    $customer->forceFill([
                        'outstanding_balance' => bcsub(
                            (string) ($customer->outstanding_balance ?? '0'),
                            $amount,
                            4,
                        ),
                    ])->save();
                }
            }

            do_action('sale_payment.recorded', $payment, $sale);

            return $payment;
        });
    }

    private function fmt(string $v): string
    {
        if ($v === '' || $v === '-' || $v === '.') return '0.0000';
        return bcadd($v, '0', 4);
    }
}
