<?php

namespace App\Actions\Purchases;

use App\Actions\Inventory\RecordStockMovement;
use App\Exceptions\PurchaseNotEditable;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Receive a draft purchase — the keystone action of the supplier side.
 *
 * What runs (atomically, inside a single transaction with row locks):
 *   1. Lock the purchase row + the supplier row.
 *   2. For each line:
 *      - If the product is batch-tracked AND `batch_number` is set on
 *        the line, find-or-create the corresponding `product_batches`
 *        row and stash its id on `purchase_items.batch_id`.
 *      - Set `purchase_items.received_quantity = quantity` (v1.0 forces
 *        full receipt; the per-line variance flow is a v1.1 thing).
 *      - Call {@see RecordStockMovement} with `type='purchase'`. That
 *        handles the `product_stock_levels` upsert, the
 *        `lockForUpdate()` on the level row, the WAC roll-forward, and
 *        the `stock_movements` ledger insert.
 *   3. Update the purchase header — `status='received'`,
 *      `is_received_in_full=true`, `balance_due=grand_total`.
 *   4. Increment the supplier's `outstanding_balance` by `grand_total`.
 *   5. Fire `purchase.after_receive` for plugins.
 *
 * What DOESN'T run yet (deferred to the Accounting slice — flagged with
 * a TODO + a `purchase.received` event that the future journal poster
 * subscribes to and backfills from `journal_entries.reference_type`):
 *   - Posting Dr Inventory / Dr Tax Input / Cr A/P journal entries.
 *
 * **This choice is deliberate.** The inventory + supplier-balance state
 * is what the shop owner needs to keep operating. Books-level accuracy
 * is important but landing a tested journal-posting subsystem responsibly
 * requires more surface area than this slice can cover. The receive
 * event leaves enough breadcrumbs (purchase row, line items with
 * tax_amount, batches, store_id) for the accounting slice to backfill
 * entries from the historical record without losing data.
 *
 * Idempotency: relies on the database-level `purchase.status` check.
 * Calling Receive on a non-draft purchase throws {@see PurchaseNotEditable}
 * so a double-submit (network retry, double-click) is a no-op + flash.
 */
class ReceivePurchase
{
    public function __construct(private RecordStockMovement $recordMovement) {}

    /**
     * Accumulator for selling-price changes triggered by the auto-apply
     * markup workflow during a single receive transaction. Each entry:
     *   ['product_id' => int, 'sku' => string, 'name' => string,
     *    'old_price' => string, 'new_price' => string]
     * Captured here so the controller can surface a per-SKU breakdown
     * to the receiver after the receive flash.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $priceChanges = [];

    public function __invoke(Purchase $purchase, ?User $receiver = null, ?bool $applyMarkup = null): Purchase
    {
        // Outside the transaction: an early bail saves us a write lock
        // when the call is a stale retry.
        if ($purchase->status !== Purchase::STATUS_DRAFT) {
            throw new PurchaseNotEditable($purchase->status);
        }

        // Reset the accumulator on every invocation so a re-used action
        // instance doesn't leak rows from a prior call.
        $this->priceChanges = [];

        // Per-PO override wins; otherwise read the company default.
        //   - $applyMarkup=true  → recompute selling prices for managed SKUs
        //   - $applyMarkup=false → don't touch selling prices
        //   - $applyMarkup=null  → fall back to the company-level default
        $autoApplyMarkup = $applyMarkup
            ?? (bool) (Company::current()?->auto_apply_markup_on_receive ?? false);

        do_action('purchase.before_receive', $purchase);

        return DB::transaction(function () use ($purchase, $receiver, $autoApplyMarkup) {
            // Lock the purchase row + re-check status inside the txn.
            // Catches the race where two clients race to receive the
            // same draft — the second one sees `received` and bails.
            $locked = Purchase::query()
                ->whereKey($purchase->id)
                ->lockForUpdate()
                ->first();
            if (! $locked || $locked->status !== Purchase::STATUS_DRAFT) {
                throw new PurchaseNotEditable($locked?->status ?? 'unknown');
            }
            $purchase = $locked;

            $supplier = Supplier::query()
                ->whereKey($purchase->supplier_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Eager-load items with their products so we can check
            // `track_batches` per line in one query. `markup_percent` +
            // `selling_price` + identity fields are along for the ride
            // so the auto-apply-markup branch below doesn't issue an
            // extra SELECT per line.
            $purchase->load(['items.product:id,sku,name,track_batches,track_expiry,markup_percent,selling_price']);

            foreach ($purchase->items as $item) {
                $this->receiveLine($purchase, $item, $receiver);

                if ($autoApplyMarkup) {
                    $this->maybeApplyMarkup($item, $receiver);
                }
            }

            // Header state. `forceFill()->save()` because `status`,
            // `is_received_in_full`, and `balance_due` are deliberately
            // non-fillable on the model — only transition actions like
            // this one write them. `markup_applied` records the
            // per-PO decision (true/false) so audits don't need to
            // reconstruct intent from product diffs.
            $purchase->forceFill([
                'status'              => Purchase::STATUS_RECEIVED,
                'is_received_in_full' => true,
                'balance_due'         => $purchase->grand_total,
                'markup_applied'      => $autoApplyMarkup,
                'updated_by'          => $receiver?->id,
            ])->save();

            // Supplier outstanding. Multi-currency simplification (per
            // feature doc §19.2): outstanding tracked in supplier's
            // default currency only. The future Sales/Money slice
            // upgrades this with proper conversion via
            // `purchase.exchange_rate_to_base`.
            //
            // `forceFill()->save()` because `outstanding_balance` is
            // non-fillable — only payment / receive / return actions
            // are allowed to touch it.
            $supplier->forceFill([
                'outstanding_balance' => bcadd((string) $supplier->outstanding_balance, (string) $purchase->grand_total, 4),
                'updated_by'          => $receiver?->id,
            ])->save();

            // The receipt journal entry (Dr Inventory / Dr Tax Input / Cr A/P)
            // is posted by App\Actions\Accounting\PostPurchaseEntry, which
            // subscribes to this hook (see HookServiceProvider). Best-effort:
            // a posting failure is logged, never failing the receive.
            do_action('purchase.after_receive', $purchase);

            return $purchase->refresh();
        });
    }

    /**
     * Receive a single line: capture batch if relevant, write the stock
     * movement, persist the received quantity.
     */
    private function receiveLine(Purchase $purchase, PurchaseItem $item, ?User $receiver): void
    {
        $product = $item->product;
        $batchId = $item->batch_id;

        // Batch capture — only when the product wants batches AND the
        // user provided a batch number. (Some shops don't always record
        // batches even on batch-tracked products; we don't force them.)
        //
        // We only ensure the batch ROW exists here (with quantity = 0).
        // The actual quantity bump happens inside RecordStockMovement
        // below — that action is the single source of truth for both
        // `product_stock_levels.quantity` and `product_batches.quantity`,
        // so sales decrements and adjustment outflows stay symmetric
        // with receive inflows. Mutating batch.quantity in two places
        // is how a "$N over-stock" bug sneaks in.
        if ($product && $product->track_batches && ! empty($item->batch_number)) {
            $batch = $this->findBatch($purchase, $item);

            if (! $batch) {
                try {
                    // Brand-new batch — `initial_quantity` is captured
                    // at create time and is immutable thereafter (used
                    // for batch-life reporting). `quantity` starts at
                    // zero; RecordStockMovement adds item.quantity to
                    // it in the same transaction.
                    $batch = ProductBatch::create([
                        'store_id'         => $purchase->store_id,
                        'product_id'       => $item->product_id,
                        'variant_id'       => $item->variant_id,
                        'batch_number'     => $item->batch_number,
                        'manufacture_date' => $item->manufacture_date,
                        'expiry_date'      => $item->expiry_date,
                        'cost_price'       => $item->unit_cost,
                        'quantity'         => 0,
                        'initial_quantity' => $item->quantity,
                    ]);
                } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                    // Lost the race — another receive just created this
                    // batch. Re-fetch the row; RecordStockMovement will
                    // still apply our quantity on top of it.
                    $batch = $this->findBatch($purchase, $item);
                    if (! $batch) {
                        throw $e; // re-throw if somehow STILL missing
                    }
                }
            }
            // EXISTING batch on a subsequent receipt → no manual quantity
            // bump here either; RecordStockMovement owns it.
            $batchId = $batch->id;
        }

        // V1.0: force received_quantity = ordered quantity. Per-line
        // shortage capture is a v1.1 enhancement (feature doc §6.4).
        $item->update([
            'received_quantity' => $item->quantity,
            'batch_id'          => $batchId,
        ]);

        // The big one: stock-level update + WAC + ledger row.
        ($this->recordMovement)(
            storeId:       (int) $purchase->store_id,
            productId:     (int) $item->product_id,
            variantId:     $item->variant_id,
            batchId:       $batchId,
            quantityDelta: $item->quantity,
            type:          'purchase',
            referenceType: Purchase::class,
            referenceId:   $purchase->id,
            unitCost:      $item->unit_cost,
            notes:         "Purchase {$purchase->number}",
            createdBy:     $receiver?->id,
        );
    }

    /**
     * Apply the product's target markup to its selling price if both
     * conditions are met:
     *   - the product has a non-null, positive `markup_percent`
     *   - the received `unit_cost` is non-zero (cost × 1 + markup/100)
     *
     * Idempotent across multi-line receives — if two lines reference
     * the same product, the second one sees the already-updated row
     * and is a no-op when the math agrees.
     *
     * No-op (silently) when the product has no markup set, the receive
     * cost is zero, or the resulting selling price equals what's
     * already stored. Every other branch logs to `$this->priceChanges`
     * so the controller can surface a per-SKU summary.
     */
    private function maybeApplyMarkup(PurchaseItem $item, ?User $receiver): void
    {
        $product = $item->product;
        if (! $product) return;
        if ($product->markup_percent === null) return;

        // Lock the product row for the duration of the bump so a
        // concurrent receive on the same SKU can't race us.
        $locked = Product::query()->lockForUpdate()->find($item->product_id);
        if (! $locked || $locked->markup_percent === null) return;

        $suggested = $locked->suggestedSellingFromCost((string) $item->unit_cost);
        if ($suggested === null) return;

        $current = (string) $locked->selling_price;
        if (bccomp($suggested, $current, 4) === 0) return; // no change

        $locked->forceFill([
            'selling_price' => $suggested,
            'updated_by'    => $receiver?->id,
        ])->save();

        $this->priceChanges[] = [
            'product_id' => (int) $locked->id,
            'sku'        => (string) $locked->sku,
            'name'       => (string) $locked->name,
            'old_price'  => $current,
            'new_price'  => $suggested,
        ];
    }

    /**
     * Locate an existing batch row by its natural key.
     *
     * `withTrashed()` on purpose: an ARCHIVED batch still owns its slot in the
     * (store, product, variant, batch_number) unique index, so skipping it here
     * would send us into a create() that can only fail. Receiving that batch
     * number again means the same physical batch is back in stock — restore the
     * row so its history stays on one batch instead of splitting across two.
     */
    private function findBatch(Purchase $purchase, PurchaseItem $item, bool $lockForUpdate = false): ?ProductBatch
    {
        $q = ProductBatch::query()
            ->withTrashed()
            ->where('store_id',     $purchase->store_id)
            ->where('product_id',   $item->product_id)
            ->where('variant_id',   $item->variant_id)
            ->where('batch_number', $item->batch_number);
        if ($lockForUpdate) {
            $q->lockForUpdate();
        }

        $batch = $q->first();

        if ($batch && $batch->trashed()) {
            $batch->restore();
        }

        return $batch;
    }
}
