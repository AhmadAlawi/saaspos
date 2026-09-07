<?php

namespace App\Actions\Accounting;

use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Store;
use App\Services\Accounting\AccountMappingResolver;
use App\Services\Accounting\PaymentAccountResolver;

/**
 * Posts the journal entry for a customer payment — money in that clears the
 * customer's receivable (and banks any over-payment as a credit):
 *
 *   Dr  Cash / Bank (payment method)   total received
 *     Cr  A/R — Customers                allocated to sales
 *     Cr  Customer Advances              unallocated (over-payment credit)
 *
 * A payment is a batch of SalePayment rows sharing a client_uuid; rows with a
 * sale_id reduce A/R, the sale_id-null row is the over-tender credit. Idempotent
 * on the batch's first row; the `customer_payment.after_create` listener wraps
 * it so a posting failure never blocks the payment.
 *
 * @param  list<SalePayment>  $inserted
 */
class PostCustomerPaymentEntry
{
    public function __construct(
        private PostJournalEntry $post,
        private AccountMappingResolver $mappings,
        private PaymentAccountResolver $paymentAccounts,
    ) {}

    public function __invoke(array $inserted, Customer $customer): ?JournalEntry
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
        $allocatedSaleId = null;
        foreach ($rows as $r) {
            $amount = (string) $r->amount;
            $total  = bcadd($total, $amount, 4);
            if ($r->sale_id) {
                $allocated       = bcadd($allocated, $amount, 4);
                $allocatedSaleId ??= (int) $r->sale_id;
            } else {
                $unallocated = bcadd($unallocated, $amount, 4);
            }
        }

        if (bccomp($total, '0', 4) <= 0) {
            return null;
        }

        $storeId        = $this->resolveStore($allocatedSaleId);
        $method         = PaymentMethod::find($rows->first()->payment_method_id);
        $paymentAccount = $this->paymentAccounts->resolve($method, $storeId);

        $lines = [];
        $lines[] = ['account_id' => $paymentAccount->id, 'debit' => $total, 'credit' => 0, 'description' => __('accounting.lines.payment')];
        if (bccomp($allocated, '0', 4) > 0) {
            $lines[] = ['account_id' => $this->mappings->id('accounts_receivable_customers', $storeId), 'debit' => 0, 'credit' => $allocated, 'description' => __('accounting.lines.receivable')];
        }
        if (bccomp($unallocated, '0', 4) > 0) {
            $lines[] = ['account_id' => $this->mappings->id('customer_advances', $storeId), 'debit' => 0, 'credit' => $unallocated, 'description' => __('accounting.lines.customer_advance')];
        }

        if (count($lines) < 2) {
            return null;
        }

        return ($this->post)([
            'store_id'       => $storeId,
            'entry_date'     => $rows->first()->paid_at,
            'source'         => JournalEntry::SOURCE_CUSTOMER_PAYMENT,
            'reference_type' => SalePayment::class,
            'reference_id'   => $refId,
            'description'    => __('accounting.descriptions.customer_payment', ['name' => $customer->name]),
            'created_by'     => $rows->first()->created_by,
            'lines'          => $lines,
        ]);
    }

    /** Customer payments aren't store-scoped; take the allocated sale's store. */
    private function resolveStore(?int $saleId): int
    {
        if ($saleId && ($storeId = Sale::whereKey($saleId)->value('store_id'))) {
            return (int) $storeId;
        }

        return (int) (current_store_id() ?: Store::query()->value('id'));
    }

    private function alreadyPosted(int $refId): bool
    {
        return JournalEntry::where('source', JournalEntry::SOURCE_CUSTOMER_PAYMENT)
            ->where('reference_type', SalePayment::class)
            ->where('reference_id', $refId)
            ->exists();
    }
}
