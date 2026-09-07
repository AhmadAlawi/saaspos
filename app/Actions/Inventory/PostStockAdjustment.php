<?php

namespace App\Actions\Inventory;

use App\Exceptions\InsufficientStock;
use App\Models\ProductBatch;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockLevel;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Post a draft stock adjustment: walk every line and call
 * {@see RecordStockMovement} so it lands in the ledger + updates the
 * per-store stock level, then flip the document's status to `posted`.
 *
 * Everything happens in one transaction — either every line writes a
 * movement, or nothing does. A line failing the underlying mutation
 * (e.g. RecordStockMovement throwing) rolls the entire post back, so
 * a partially-posted adjustment is structurally impossible.
 *
 * Hooks:
 *   - `stock_adjustment.before_post` (action, $adjustment) — listeners
 *     may throw to abort the post (e.g. closed-period guards).
 *   - `stock_adjustment.after_post`  (action, $adjustment) — listeners
 *     may notify, log, or push to Pusher.
 */
class PostStockAdjustment
{
    public function __construct(private RecordStockMovement $record) {}

    public function __invoke(StockAdjustment $adjustment, User $user): StockAdjustment
    {
        if (! $adjustment->isDraft()) {
            throw new RuntimeException('Only draft adjustments can be posted.');
        }

        $adjustment->load('items.product:id,name,track_batches');
        if ($adjustment->items->isEmpty()) {
            throw new RuntimeException('Cannot post an adjustment with no line items.');
        }

        return DB::transaction(function () use ($adjustment, $user) {
            do_action('stock_adjustment.before_post', $adjustment);

            // An "Out" line can't remove more than the store actually holds —
            // including the "no stock row at all" case (available = 0).
            $this->assertSufficientStock($adjustment);

            foreach ($adjustment->items as $item) {
                // New-batch lines carry a typed batch_number but no batch_id
                // yet. Materialise the ProductBatch row here (qty 0) — exactly
                // like ReceivePurchase — then feed its id to RecordStockMovement,
                // which owns the quantity bump. Existing-batch picks pass through.
                $batchId = $this->resolveBatchId($adjustment, $item);

                ($this->record)(
                    storeId:        $adjustment->store_id,
                    productId:      $item->product_id,
                    variantId:      $item->variant_id,
                    batchId:        $batchId,
                    quantityDelta:  $item->quantity_delta,
                    type:           'adjustment',
                    referenceType:  StockAdjustment::class,
                    referenceId:    $adjustment->id,
                    unitCost:       $item->unit_cost,
                    notes:          $item->notes,
                    createdBy:      $user->id,
                );
            }

            $adjustment->update([
                'status'     => StockAdjustment::STATUS_POSTED,
                'posted_at'  => now(),
                'updated_by' => $user->id,
            ]);

            do_action('stock_adjustment.after_post', $adjustment);

            return $adjustment->refresh();
        });
    }

    /**
     * Reject the post if any "Out" line would drive on-hand stock negative.
     * Net Out is accumulated and compared to the current (locked) on-hand:
     *   - a line targeting a specific batch checks THAT batch's quantity;
     *   - everything else checks the store-level quantity (missing row = 0).
     */
    private function assertSufficientStock(StockAdjustment $adjustment): void
    {
        /** @var array<int, array{qty:string, item:StockAdjustmentItem}>    $byBatch */
        $byBatch = [];
        /** @var array<string, array{qty:string, item:StockAdjustmentItem}> $byLevel */
        $byLevel = [];

        foreach ($adjustment->items as $item) {
            $delta = (string) $item->quantity_delta;
            if (bccomp($delta, '0', 4) >= 0) {
                continue; // In / zero lines never deplete stock
            }
            $outQty = bcmul($delta, '-1', 4);

            if ($item->batch_id !== null) {
                $byBatch[$item->batch_id]['qty']  = bcadd($byBatch[$item->batch_id]['qty'] ?? '0', $outQty, 4);
                $byBatch[$item->batch_id]['item'] = $item;
            } else {
                $key = $item->product_id.':'.($item->variant_id ?? '0');
                $byLevel[$key]['qty']  = bcadd($byLevel[$key]['qty'] ?? '0', $outQty, 4);
                $byLevel[$key]['item'] = $item;
            }
        }

        // Batch-targeted lines check that specific batch's on-hand.
        foreach ($byBatch as $batchId => $data) {
            $batch     = ProductBatch::query()->whereKey($batchId)->lockForUpdate()->first(['quantity', 'batch_number']);
            $available = (string) ($batch?->quantity ?? '0');

            if (bccomp($available, $data['qty'], 4) < 0) {
                $this->throwShort($data['item'], $available, $data['qty'], $batch?->batch_number);
            }
        }

        // Remaining lines check the store-level on-hand.
        foreach ($byLevel as $key => $data) {
            [$productId, $variantId] = explode(':', $key);

            $available = (string) (StockLevel::query()
                ->where('store_id', $adjustment->store_id)
                ->where('product_id', (int) $productId)
                ->when(
                    $variantId !== '0',
                    fn ($q) => $q->where('variant_id', (int) $variantId),
                    fn ($q) => $q->whereNull('variant_id'),
                )
                ->lockForUpdate()
                ->value('quantity') ?? '0');

            if (bccomp($available, $data['qty'], 4) < 0) {
                $this->throwShort($data['item'], $available, $data['qty']);
            }
        }
    }

    private function throwShort(StockAdjustmentItem $item, string $available, string $requested, ?string $batchNumber = null): void
    {
        $name = $item->product?->name ?? "Product #{$item->product_id}";
        if ($batchNumber !== null && $batchNumber !== '') {
            $name .= " (batch {$batchNumber})";
        }

        throw new InsufficientStock(
            productName: $name,
            available:   rtrim(rtrim($available, '0'), '.') ?: '0',
            requested:   rtrim(rtrim($requested, '0'), '.') ?: '0',
        );
    }

    /**
     * Resolve the batch a line should post against:
     *   - an existing-batch pick already carries `batch_id` → use it as-is;
     *   - a new-batch inflow carries `batch_number` (+ optional dates) →
     *     find-or-create the `product_batches` row (qty 0) and back-fill
     *     `batch_id` on the line so the posted document is self-describing;
     *   - anything else (no batch info, non-tracked product) → null.
     *
     * Batch creation is gated to inflow lines; the form request already
     * rejects a new batch on an "Out" line, so this is the structural
     * backstop. The unique key is (store, product, variant, batch_number),
     * matching ReceivePurchase, so two posts naming the same number land
     * on the same batch row.
     */
    private function resolveBatchId(StockAdjustment $adjustment, StockAdjustmentItem $item): ?int
    {
        if ($item->batch_id !== null) {
            return $item->batch_id;
        }

        $product = $item->product;
        if (! $product?->track_batches
            || empty($item->batch_number)
            || (float) $item->quantity_delta <= 0) {
            return null;
        }

        $batch = $this->findBatch($adjustment, $item)
            ?? $this->createBatch($adjustment, $item);

        $item->update(['batch_id' => $batch->id]);

        return $batch->id;
    }

    /**
     * `withTrashed()` on purpose: an ARCHIVED batch still holds its slot in the
     * (store, product, variant, batch_number) unique index, so ignoring it here
     * would push us into a create() that can only fail. Adjusting that batch
     * number back in means the same physical batch is in stock again — restore
     * the row rather than split its history across two.
     */
    private function findBatch(StockAdjustment $adjustment, StockAdjustmentItem $item): ?ProductBatch
    {
        $batch = ProductBatch::query()
            ->withTrashed()
            ->where('store_id',     $adjustment->store_id)
            ->where('product_id',   $item->product_id)
            ->where('variant_id',   $item->variant_id)
            ->where('batch_number', $item->batch_number)
            ->first();

        if ($batch && $batch->trashed()) {
            $batch->restore();
        }

        return $batch;
    }

    private function createBatch(StockAdjustment $adjustment, StockAdjustmentItem $item): ProductBatch
    {
        try {
            // `quantity` starts at 0; RecordStockMovement adds the line's
            // delta in the same transaction. `initial_quantity` is captured
            // immutably here for batch-life reporting.
            return ProductBatch::create([
                'store_id'         => $adjustment->store_id,
                'product_id'       => $item->product_id,
                'variant_id'       => $item->variant_id,
                'batch_number'     => $item->batch_number,
                'manufacture_date' => $item->manufacture_date,
                'expiry_date'      => $item->expiry_date,
                'cost_price'       => $item->unit_cost,
                'quantity'         => 0,
                'initial_quantity' => $item->quantity_delta,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // Lost a race against a concurrent create — re-fetch the row.
            $batch = $this->findBatch($adjustment, $item);
            if (! $batch) {
                throw $e;
            }
            return $batch;
        }
    }
}
