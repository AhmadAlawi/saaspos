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
 * Manager-only correction that turns ONE of a sale's tenders into SEVERAL
 * across different payment methods — e.g. a single 95.79 card payment
 * becomes 60.00 card + 35.79 cash, discovered after the sale (and its
 * shift) already closed. Sibling of {@see ChangeSalePaymentMethod}, same
 * "closed sale, closed shift" eligibility and the same accepted
 * consequence: a closed shift's Z-report is a frozen snapshot, so this
 * won't retroactively regenerate it — the audit trail
 * ({@see SalePaymentMethodChange}, `old_amount`/`amount` columns) is
 * what makes the after-the-fact discrepancy explainable.
 *
 * The ORIGINAL `sale_payments` row is never deleted (this codebase never
 * hard-deletes a financial row — see VoidSale/ChangeSalePaymentMethod).
 * Instead it's repurposed as the FIRST split piece (method + amount
 * mutated in place, its id and history intact), and one NEW row is
 * inserted per additional piece.
 *
 * @param array<int, array{payment_method_id:int, amount:numeric-string}> $splits
 */
class SplitSalePayment
{
    public function __invoke(Sale $sale, SalePayment $payment, array $splits, ?string $reason, User $user): array
    {
        return DB::transaction(function () use ($sale, $payment, $splits, $reason, $user) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            $payment = SalePayment::query()->lockForUpdate()->where('sale_id', $sale->id)->findOrFail($payment->id);

            $methods = PaymentMethod::query()->whereIn('id', array_column($splits, 'payment_method_id'))->get()->keyBy('id');
            $this->guard($sale, $payment, $splits, $methods);

            $oldMethodId = $payment->payment_method_id;
            $oldAmount   = (string) $payment->amount;

            $results = [];
            foreach ($splits as $i => $s) {
                $amount = $this->fmt((string) $s['amount']);
                $methodId = (int) $s['payment_method_id'];

                if ($i === 0) {
                    // Repurpose the original row as the first piece — same
                    // "mutate in place" shape ChangeSalePaymentMethod uses.
                    $payment->forceFill([
                        'payment_method_id' => $methodId,
                        'amount'            => $amount,
                    ])->save();
                    $row = $payment->fresh();
                } else {
                    $row = SalePayment::create([
                        'sale_id'           => $sale->id,
                        'payment_method_id' => $methodId,
                        'amount'            => $amount,
                        'reference'         => $payment->reference,
                        'currency_code'     => $payment->currency_code,
                        'paid_at'           => $payment->paid_at,
                        'created_by'        => $user->id,
                    ]);
                }

                SalePaymentMethodChange::create([
                    'sale_id'               => $sale->id,
                    'sale_payment_id'       => $row->id,
                    'old_payment_method_id' => $oldMethodId,
                    'new_payment_method_id' => $methodId,
                    'old_amount'            => $oldAmount,
                    'amount'                => $amount,
                    'reason'                => $reason,
                    'changed_by'            => $user->id,
                    'changed_at'            => now(),
                ]);

                $results[] = $row;
            }

            do_action('sale.payment_split', $sale, $payment, $results, $user);

            return $results;
        });
    }

    /**
     * @param array<int, array{payment_method_id:int, amount:numeric-string}> $splits
     * @param \Illuminate\Support\Collection<int, PaymentMethod> $methods
     */
    private function guard(Sale $sale, SalePayment $payment, array $splits, $methods): void
    {
        if ($sale->status === Sale::STATUS_VOIDED) {
            throw new SalePaymentNotEditable($sale, 'sale_voided');
        }
        if (count($splits) < 2) {
            throw new SalePaymentNotEditable($sale, 'split_count');
        }

        $sum = '0';
        foreach ($splits as $s) {
            $amount = (string) ($s['amount'] ?? '0');
            if (bccomp($amount, '0', 4) <= 0) {
                throw new SalePaymentNotEditable($sale, 'split_amount_zero');
            }
            $method = $methods->get((int) ($s['payment_method_id'] ?? 0));
            if (! $method || ! $method->is_active) {
                throw new SalePaymentNotEditable($sale, 'method_inactive');
            }
            $sum = bcadd($sum, $amount, 4);
        }

        if (bccomp($sum, (string) $payment->amount, 4) !== 0) {
            throw new SalePaymentNotEditable($sale, 'split_amount_mismatch');
        }
    }

    private function fmt(string $v): string
    {
        return bcadd($v, '0', 4);
    }
}
