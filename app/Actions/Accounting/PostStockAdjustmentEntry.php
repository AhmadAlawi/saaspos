<?php

namespace App\Actions\Accounting;

use App\Models\JournalEntry;
use App\Models\StockAdjustment;
use App\Models\StockLevel;
use App\Services\Accounting\AccountMappingResolver;

/**
 * Posts the journal entry for a posted stock adjustment
 * (docs/features/accounting.md §5.2, source `stock_adjustment`). A line's value
 * is |quantity_delta| × cost (the line's `unit_cost` if set, else store WAC):
 *
 *   inflows  →  Cr Inventory Adjustment Income   (found stock)
 *   outflows →  Dr Inventory Shrinkage           (loss / damage)
 *   net inventory change on the Inventory account
 *
 * A mixed adjustment nets its inflows and outflows on Inventory. Idempotent;
 * the `stock_adjustment.after_post` listener wraps it so a failure never blocks
 * the adjustment.
 */
class PostStockAdjustmentEntry
{
    public function __construct(
        private PostJournalEntry $post,
        private AccountMappingResolver $mappings,
    ) {}

    public function __invoke(StockAdjustment $adjustment): ?JournalEntry
    {
        if ($this->alreadyPosted($adjustment)) {
            return null;
        }

        $adjustment->loadMissing('items');
        $storeId = (int) $adjustment->store_id;

        $inTotal = $outTotal = '0';
        foreach ($adjustment->items as $item) {
            $delta = (string) $item->quantity_delta;
            $cmp   = bccomp($delta, '0', 4);
            if ($cmp === 0) {
                continue;
            }

            $value = bcmul($this->abs($delta), $this->cost($item, $storeId), 4);
            if ($cmp > 0) {
                $inTotal = bcadd($inTotal, $value, 4);
            } else {
                $outTotal = bcadd($outTotal, $value, 4);
            }
        }

        $inventoryNet = bcsub($inTotal, $outTotal, 4);

        $lines = [];
        if (bccomp($inventoryNet, '0', 4) > 0) {
            $lines[] = $this->debit($this->mappings->id('inventory', $storeId), $inventoryNet, __('accounting.lines.inventory'));
        } elseif (bccomp($inventoryNet, '0', 4) < 0) {
            $lines[] = $this->credit($this->mappings->id('inventory', $storeId), $this->abs($inventoryNet), __('accounting.lines.inventory'));
        }
        if (bccomp($inTotal, '0', 4) > 0) {
            $lines[] = $this->credit($this->mappings->id('inventory_adjustment_income', $storeId), $inTotal, __('accounting.lines.adjustment_income'));
        }
        if (bccomp($outTotal, '0', 4) > 0) {
            $lines[] = $this->debit($this->mappings->id('inventory_shrinkage', $storeId), $outTotal, __('accounting.lines.shrinkage'));
        }

        if (count($lines) < 2) {
            return null;
        }

        return ($this->post)([
            'store_id'       => $storeId,
            'entry_date'     => $adjustment->adjustment_date,
            'source'         => JournalEntry::SOURCE_STOCK_ADJUSTMENT,
            'reference_type' => StockAdjustment::class,
            'reference_id'   => $adjustment->id,
            'description'    => __('accounting.descriptions.stock_adjustment', ['number' => $adjustment->number]),
            'created_by'     => $adjustment->created_by,
            'lines'          => $lines,
        ]);
    }

    private function cost($item, int $storeId): string
    {
        $unitCost = (string) ($item->unit_cost ?? '0');
        if (bccomp($unitCost, '0', 4) > 0) {
            return $unitCost;
        }

        $wac = StockLevel::query()
            ->where('store_id', $storeId)
            ->where('product_id', $item->product_id)
            ->when($item->variant_id === null,
                fn ($q) => $q->whereNull('variant_id'),
                fn ($q) => $q->where('variant_id', $item->variant_id))
            ->value('weighted_average_cost');

        return (string) ($wac ?? '0');
    }

    private function abs(string $v): string
    {
        return bccomp($v, '0', 4) < 0 ? bcmul($v, '-1', 4) : $v;
    }

    private function alreadyPosted(StockAdjustment $adjustment): bool
    {
        return JournalEntry::where('source', JournalEntry::SOURCE_STOCK_ADJUSTMENT)
            ->where('reference_type', StockAdjustment::class)
            ->where('reference_id', $adjustment->id)
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
