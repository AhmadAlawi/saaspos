<?php

namespace App\Actions\Accounting;

use App\Models\JournalEntry;
use App\Models\PaymentMethod;
use App\Models\PurchasePayment;
use App\Models\Supplier;
use App\Services\Accounting\AccountMappingResolver;
use App\Services\Accounting\PaymentAccountResolver;

/**
 * Posts the journal entry for a supplier payment — money out that clears our
 * payable (and banks any over-payment as an advance):
 *
 *   Dr  A/P — Suppliers        allocated to purchases
 *   Dr  Advances to Suppliers  unallocated (over-payment)
 *     Cr  Cash / Bank (payment method)   total paid
 *
 * A payment is a batch of PurchasePayment rows; rows with a purchase_id reduce
 * A/P, the purchase_id-null row is the over-payment advance. Idempotent on the
 * batch's first row; the `supplier_payment.after_create` listener wraps it so a
 * posting failure never blocks the payment.
 *
 * @param  list<PurchasePayment>  $inserted
 */
class PostSupplierPaymentEntry
{
    public function __construct(
        private PostJournalEntry $post,
        private AccountMappingResolver $mappings,
        private PaymentAccountResolver $paymentAccounts,
    ) {}

    public function __invoke(array $inserted, Supplier $supplier): ?JournalEntry
    {
        $rows = collect($inserted);
        if ($rows->isEmpty()) {
            return null;
        }

        $refId = (int) $rows->first()->id;
        if ($this->alreadyPosted($refId)) {
            return null;
        }

        $total = $allocated = $unallocated = '0';
        $methodId = null;
        foreach ($rows as $r) {
            $amount = (string) $r->amount;
            $total  = bcadd($total, $amount, 4);
            if ($r->purchase_id) {
                $allocated = bcadd($allocated, $amount, 4);
            } else {
                $unallocated = bcadd($unallocated, $amount, 4);
            }
            if (! $methodId && $r->payment_method_id) {
                $methodId = (int) $r->payment_method_id;
            }
        }

        if (bccomp($total, '0', 4) <= 0) {
            return null;
        }

        $storeId        = (int) $rows->first()->store_id;
        $method         = $methodId ? PaymentMethod::find($methodId) : null;
        $paymentAccount = $this->paymentAccounts->resolve($method, $storeId);

        $lines = [];
        if (bccomp($allocated, '0', 4) > 0) {
            $lines[] = ['account_id' => $this->mappings->id('accounts_payable_suppliers', $storeId), 'debit' => $allocated, 'credit' => 0, 'description' => __('accounting.lines.payable')];
        }
        if (bccomp($unallocated, '0', 4) > 0) {
            $lines[] = ['account_id' => $this->mappings->id('advances_to_suppliers', $storeId), 'debit' => $unallocated, 'credit' => 0, 'description' => __('accounting.lines.supplier_advance')];
        }
        $lines[] = ['account_id' => $paymentAccount->id, 'debit' => 0, 'credit' => $total, 'description' => __('accounting.lines.payment')];

        if (count($lines) < 2) {
            return null;
        }

        return ($this->post)([
            'store_id'       => $storeId,
            'entry_date'     => $rows->first()->payment_date,
            'source'         => JournalEntry::SOURCE_SUPPLIER_PAYMENT,
            'reference_type' => PurchasePayment::class,
            'reference_id'   => $refId,
            'description'    => __('accounting.descriptions.supplier_payment', ['name' => $supplier->name]),
            'created_by'     => $rows->first()->created_by,
            'lines'          => $lines,
        ]);
    }

    private function alreadyPosted(int $refId): bool
    {
        return JournalEntry::where('source', JournalEntry::SOURCE_SUPPLIER_PAYMENT)
            ->where('reference_type', PurchasePayment::class)
            ->where('reference_id', $refId)
            ->exists();
    }
}
