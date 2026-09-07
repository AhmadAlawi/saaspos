<?php

namespace App\Actions\Accounting;

use App\Models\JournalEntry;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\StockLevel;
use App\Services\Accounting\AccountMappingResolver;
use App\Services\Accounting\PaymentAccountResolver;

/**
 * Turns a completed sale into its journal entry (docs/features/accounting.md
 * §5.3). Built from the sale's own money columns so it balances by
 * construction:
 *
 *   Dr  payment account(s)   net cash received per tender
 *   Dr  A/R — Customers      balance_due          (credit sales)
 *   Dr  Sales Discounts      discount_total       (contra-revenue, if any)
 *     Cr  Sales — Revenue      subtotal + discount_total   (gross)
 *     Cr  Tax Output           tax_total            (if any)
 *   Dr  COGS  /  Cr Inventory  Σ qty × WAC          (cost relief, if any)
 *
 * COGS uses the store's current weighted-average cost — sale movements don't
 * change WAC, so it equals the sale-time cost — until per-line cost snapshots
 * are captured on `sale_items`.
 *
 * Idempotent (skips a sale that already has an entry) and side-effect-light:
 * the `sale.after_complete` listener wraps this so a posting failure is logged
 * but never blocks checkout.
 */
class PostSaleEntry
{
    public function __construct(
        private PostJournalEntry $post,
        private AccountMappingResolver $mappings,
        private PaymentAccountResolver $paymentAccounts,
    ) {}

    public function __invoke(Sale $sale): ?JournalEntry
    {
        if ($this->alreadyPosted($sale)) {
            return null;
        }

        $sale->loadMissing(['items', 'payments']);
        $storeId = (int) $sale->store_id;

        $subtotal = (string) $sale->subtotal;
        $discount = (string) $sale->discount_total;
        $tax      = (string) $sale->tax_total;
        $gross    = bcadd($subtotal, $discount, 4); // revenue credited at gross

        $lines = [];

        // ── Debits — assets received ───────────────────────────────────
        $methods = PaymentMethod::whereIn('id', $sale->payments->pluck('payment_method_id')->unique()->all())
            ->get()->keyBy('id');

        foreach ($sale->payments as $payment) {
            $net = bcsub((string) $payment->amount, (string) ($payment->change_returned ?? '0'), 4);
            if (bccomp($net, '0', 4) <= 0) {
                continue;
            }
            $account = $this->paymentAccounts->resolve($methods->get($payment->payment_method_id), $storeId);
            $lines[] = $this->debit($account->id, $net, __('accounting.lines.payment'));
        }

        if ($sale->customer_id && bccomp((string) $sale->balance_due, '0', 4) > 0) {
            $lines[] = $this->debit($this->mappings->id('accounts_receivable_customers', $storeId), (string) $sale->balance_due, __('accounting.lines.receivable'));
        }

        if (bccomp($discount, '0', 4) > 0) {
            $lines[] = $this->debit($this->mappings->id('sales_discounts', $storeId), $discount, __('accounting.lines.discount'));
        }

        // ── Credits — revenue + tax ────────────────────────────────────
        if (bccomp($gross, '0', 4) > 0) {
            $lines[] = $this->credit($this->mappings->id('sales_revenue', $storeId), $gross, __('accounting.lines.sales_revenue'));
        }
        if (bccomp($tax, '0', 4) > 0) {
            $lines[] = $this->credit($this->mappings->id('tax_output', $storeId), $tax, __('accounting.lines.tax_output'));
        }

        // Cash-denomination rounding at checkout (e.g. rounding to the
        // nearest 5 fils) — omitting this line is exactly what causes
        // UnbalancedJournalException on any sale with a nonzero rounding
        // adjustment, since the customer's actual payment reflects the
        // rounded total but subtotal+tax above don't.
        $rounding = (string) ($sale->rounding_adjustment ?? '0');
        if (bccomp($rounding, '0', 4) < 0) {
            // Rounded down — the business collected less than subtotal+tax.
            $lines[] = $this->debit($this->mappings->id('rounding_adjustment_expense', $storeId), bcmul($rounding, '-1', 4), __('accounting.lines.rounding'));
        } elseif (bccomp($rounding, '0', 4) > 0) {
            // Rounded up — the business collected slightly more.
            $lines[] = $this->credit($this->mappings->id('rounding_adjustment_income', $storeId), $rounding, __('accounting.lines.rounding'));
        }

        // ── Cost of goods sold + inventory relief ──────────────────────
        $cogs = $this->cogs($sale, $storeId);
        if (bccomp($cogs, '0', 4) > 0) {
            $lines[] = $this->debit($this->mappings->id('cogs', $storeId), $cogs, __('accounting.lines.cogs'));
            $lines[] = $this->credit($this->mappings->id('inventory', $storeId), $cogs, __('accounting.lines.inventory'));
        }

        if (count($lines) < 2) {
            return null; // nothing meaningful to post (e.g. a zero-value sale)
        }

        return ($this->post)([
            'store_id'       => $storeId,
            'entry_date'     => $sale->sale_date,
            'source'         => JournalEntry::SOURCE_SALE,
            'reference_type' => Sale::class,
            'reference_id'   => $sale->id,
            'description'    => __('accounting.descriptions.sale', ['number' => $sale->number]),
            'created_by'     => $sale->created_by ?? $sale->cashier_id,
            'lines'          => $lines,
        ]);
    }

    /** Σ (quantity × store WAC) across the sale's lines. */
    private function cogs(Sale $sale, int $storeId): string
    {
        $total = '0';

        foreach ($sale->items as $item) {
            $wac = StockLevel::query()
                ->where('store_id', $storeId)
                ->where('product_id', $item->product_id)
                ->when($item->variant_id === null,
                    fn ($q) => $q->whereNull('variant_id'),
                    fn ($q) => $q->where('variant_id', $item->variant_id))
                ->value('weighted_average_cost');

            if ($wac === null) {
                continue;
            }

            $total = bcadd($total, bcmul((string) $item->quantity, (string) $wac, 4), 4);
        }

        return $total;
    }

    private function alreadyPosted(Sale $sale): bool
    {
        return JournalEntry::where('source', JournalEntry::SOURCE_SALE)
            ->where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
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
