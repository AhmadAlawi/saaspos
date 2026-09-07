<?php

namespace App\Actions\Accounting;

use App\Models\JournalEntry;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\StockLevel;
use App\Services\Accounting\AccountMappingResolver;
use App\Services\Accounting\PaymentAccountResolver;

/**
 * Posts the journal entry for a sale return (docs/features/accounting.md §5.3):
 *
 *   Dr  Sales Returns (contra-revenue)   subtotal
 *   Dr  Tax Output                        tax_total
 *     Cr  Store Credit Liability            refunded to store credit
 *     Cr  Cash / Bank (refund method)       refunded in cash / original method
 *   Dr  Inventory  /  Cr COGS              cost of restocked goods (WAC)
 *
 * Balances by construction (subtotal + tax = grand_total = the refund split).
 * Idempotent; the `sale.after_return` listener wraps it so a failure never
 * blocks the refund.
 */
class PostSaleReturnEntry
{
    public function __construct(
        private PostJournalEntry $post,
        private AccountMappingResolver $mappings,
        private PaymentAccountResolver $paymentAccounts,
    ) {}

    public function __invoke(SaleReturn $return): ?JournalEntry
    {
        if ($this->alreadyPosted($return)) {
            return null;
        }

        $return->loadMissing(['items', 'refundMethod']);
        $storeId = (int) $return->store_id;

        $subtotal    = (string) $return->subtotal;
        $tax         = (string) $return->tax_total;
        $storeCredit = (string) ($return->refunded_to_store_credit ?? '0');
        $moneyOut    = bcadd((string) ($return->refunded_in_cash ?? '0'), (string) ($return->refunded_to_original_method ?? '0'), 4);

        if (bccomp((string) $return->grand_total, '0', 4) <= 0) {
            return null;
        }

        $lines = [];

        // Reverse revenue + output tax.
        if (bccomp($subtotal, '0', 4) > 0) {
            $lines[] = $this->debit($this->mappings->id('sales_returns', $storeId), $subtotal, __('accounting.lines.sales_return'));
        }
        if (bccomp($tax, '0', 4) > 0) {
            $lines[] = $this->debit($this->mappings->id('tax_output', $storeId), $tax, __('accounting.lines.tax_output'));
        }

        // Where the money went.
        if (bccomp($storeCredit, '0', 4) > 0) {
            $lines[] = $this->credit($this->mappings->id('store_credit_liability', $storeId), $storeCredit, __('accounting.lines.store_credit'));
        }
        if (bccomp($moneyOut, '0', 4) > 0) {
            $account = $this->paymentAccounts->resolve($return->refundMethod, $storeId);
            $lines[] = $this->credit($account->id, $moneyOut, __('accounting.lines.refund'));
        }

        // Put the cost of restocked goods back into inventory.
        $cost = $this->restockCost($return, $storeId);
        if (bccomp($cost, '0', 4) > 0) {
            $lines[] = $this->debit($this->mappings->id('inventory', $storeId), $cost, __('accounting.lines.inventory'));
            $lines[] = $this->credit($this->mappings->id('cogs', $storeId), $cost, __('accounting.lines.cogs'));
        }

        if (count($lines) < 2) {
            return null;
        }

        return ($this->post)([
            'store_id'       => $storeId,
            'entry_date'     => $return->return_date,
            'source'         => JournalEntry::SOURCE_SALE_RETURN,
            'reference_type' => SaleReturn::class,
            'reference_id'   => $return->id,
            'description'    => __('accounting.descriptions.sale_return', ['number' => $return->number]),
            'created_by'     => $return->created_by ?? $return->cashier_id,
            'lines'          => $lines,
        ]);
    }

    /** Σ (restocked qty × store WAC) — only lines that actually go back on the shelf. */
    private function restockCost(SaleReturn $return, int $storeId): string
    {
        $headerRestock = (bool) $return->restock;
        $total = '0';

        foreach ($return->items as $item) {
            $restock = $item->restock === null ? $headerRestock : (bool) $item->restock;
            if (! $restock) {
                continue;
            }

            $saleItem = SaleItem::find($item->sale_item_id);
            if (! $saleItem) {
                continue;
            }

            $wac = StockLevel::query()
                ->where('store_id', $storeId)
                ->where('product_id', $saleItem->product_id)
                ->when($saleItem->variant_id === null,
                    fn ($q) => $q->whereNull('variant_id'),
                    fn ($q) => $q->where('variant_id', $saleItem->variant_id))
                ->value('weighted_average_cost');

            if ($wac !== null) {
                $total = bcadd($total, bcmul((string) $item->quantity, (string) $wac, 4), 4);
            }
        }

        return $total;
    }

    private function alreadyPosted(SaleReturn $return): bool
    {
        return JournalEntry::where('source', JournalEntry::SOURCE_SALE_RETURN)
            ->where('reference_type', SaleReturn::class)
            ->where('reference_id', $return->id)
            ->exists();
    }

    private function debit(int $accountId, string $amount, string $desc): array
    {
        return ['account_id' => $accountId, 'debit' => $amount, 'credit' => 0, 'description' => $desc];
    }

    private function credit(int $accountId, string $amount, string $desc): array
    {
        return ['account_id' => $accountId, 'debit' => 0, 'credit' => $amount, 'description' => $desc];
    }
}
