<?php

namespace App\Actions\Accounting;

use App\Models\JournalEntry;
use App\Models\Purchase;
use App\Services\Accounting\AccountMappingResolver;

/**
 * Posts the journal entry for a received purchase (docs/features/accounting.md
 * §5.3) — the entry the `ReceivePurchase` action deliberately deferred:
 *
 *   Dr  Inventory        grand_total − tax_total   (goods + capitalised charges)
 *   Dr  Tax Input        tax_total
 *     Cr  A/P — Suppliers   grand_total
 *
 * Balances by construction. Dated the receipt (today, when the goods land).
 * Idempotent; the `purchase.after_receive` listener wraps it so a posting
 * failure is logged, never blocking the receive.
 */
class PostPurchaseEntry
{
    public function __construct(
        private PostJournalEntry $post,
        private AccountMappingResolver $mappings,
    ) {}

    public function __invoke(Purchase $purchase): ?JournalEntry
    {
        if ($this->alreadyPosted($purchase)) {
            return null;
        }

        $storeId   = (int) $purchase->store_id;
        $grand     = (string) $purchase->grand_total;
        $tax       = (string) $purchase->tax_total;
        $inventory = bcsub($grand, $tax, 4);

        if (bccomp($grand, '0', 4) <= 0) {
            return null;
        }

        $lines = [];

        if (bccomp($inventory, '0', 4) > 0) {
            $lines[] = ['account_id' => $this->mappings->id('inventory', $storeId),  'debit' => $inventory, 'credit' => 0, 'description' => __('accounting.lines.inventory')];
        }
        if (bccomp($tax, '0', 4) > 0) {
            $lines[] = ['account_id' => $this->mappings->id('tax_input', $storeId),   'debit' => $tax,       'credit' => 0, 'description' => __('accounting.lines.tax_input')];
        }
        $lines[] = ['account_id' => $this->mappings->id('accounts_payable_suppliers', $storeId), 'debit' => 0, 'credit' => $grand, 'description' => __('accounting.lines.payable')];

        if (count($lines) < 2) {
            return null;
        }

        return ($this->post)([
            'store_id'       => $storeId,
            'entry_date'     => now()->toDateString(),
            'source'         => JournalEntry::SOURCE_PURCHASE,
            'reference_type' => Purchase::class,
            'reference_id'   => $purchase->id,
            'description'    => __('accounting.descriptions.purchase', ['number' => $purchase->number ?? ('#'.$purchase->id)]),
            'created_by'     => $purchase->updated_by ?? $purchase->created_by,
            'lines'          => $lines,
        ]);
    }

    private function alreadyPosted(Purchase $purchase): bool
    {
        return JournalEntry::where('source', JournalEntry::SOURCE_PURCHASE)
            ->where('reference_type', Purchase::class)
            ->where('reference_id', $purchase->id)
            ->exists();
    }
}
