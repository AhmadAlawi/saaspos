<?php

namespace App\Actions\Inventory;

use App\Models\ProductBatch;
use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The single write path for every stock change in the system.
 *
 * Adjustments, sales, returns, purchases, and transfers all funnel
 * through here so that:
 *
 *   1. `product_stock_levels` is updated atomically with an insert into
 *      `stock_movements` — they can never disagree about how a number
 *      changed.
 *   2. The level row is `lockForUpdate`'d so concurrent writers can't
 *      race the read-then-write of `quantity_after`.
 *   3. The weighted-average cost is recomputed on positive, costed
 *      movements: `(old_qty * old_wac + delta * unit_cost) / new_qty`.
 *      Negative movements leave WAC alone (the cost of an outgoing unit
 *      is what we last bought it at, not what we're selling it for).
 *
 * Hooks:
 *   - filter `stock.before_movement` on the array of fields about to
 *     hit the ledger; a plugin may mutate quantity_delta, notes, etc.
 *   - action `stock.after_movement` on the persisted {@see StockMovement}.
 *
 * **Does not enforce negative-stock policy.** That's the caller's job —
 * the cashier flow may block, an adjustment may legitimately go negative
 * (a write-off correcting an overcount).
 */
class RecordStockMovement
{
    /**
     * @param  string  $type One of: opening, adjustment, sale, return,
     *                       purchase, transfer_out, transfer_in.
     * @param  string|null  $referenceType  Class name of the document
     *                       that caused this change (e.g. StockAdjustment::class).
     */
    public function __invoke(
        int $storeId,
        int $productId,
        ?int $variantId,
        ?int $batchId,
        float|string $quantityDelta,
        string $type,
        ?string $referenceType = null,
        ?int $referenceId = null,
        float|string|null $unitCost = null,
        ?string $notes = null,
        ?int $createdBy = null,
    ): StockMovement {
        return DB::transaction(function () use (
            $storeId, $productId, $variantId, $batchId,
            $quantityDelta, $type, $referenceType, $referenceId,
            $unitCost, $notes, $createdBy
        ) {
            // Find-or-create against the
            // (store_id, product_id, variant_id) UNIQUE. Two concurrent
            // first-ever receipts can both miss the SELECT and try to
            // INSERT — the loser hits the unique violation. We catch
            // and re-fetch the winner's row with the lock, then proceed
            // normally.
            $level = StockLevel::query()
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->where('variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if ($level === null) {
                try {
                    $level = new StockLevel([
                        'store_id'              => $storeId,
                        'product_id'            => $productId,
                        'variant_id'            => $variantId,
                        'quantity'              => 0,
                        'reserved_quantity'     => 0,
                        'weighted_average_cost' => 0,
                    ]);
                    $level->save();
                } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                    // Lost the race — another receive just initialised
                    // this (store, product, variant) tuple. Re-fetch
                    // with the lock and continue.
                    $level = StockLevel::query()
                        ->where('store_id', $storeId)
                        ->where('product_id', $productId)
                        ->where('variant_id', $variantId)
                        ->lockForUpdate()
                        ->first();
                    if ($level === null) {
                        throw $e; // truly missing — re-throw
                    }
                }
            }

            // bcmath throughout — money + quantity columns are DECIMAL(15,4)
            // and IEEE 754 floats accumulate rounding error over thousands
            // of receipts (drift was found by the data-integrity audit).
            // Scale 8 for the WAC intermediate so we can round HALF_UP to
            // 4 cleanly at the end without compounding error.
            $oldQty   = (string) $level->quantity;
            $delta    = (string) $quantityDelta;
            $newQty   = bcadd($oldQty, $delta, 8);
            $unitCost = $unitCost !== null ? (string) $unitCost : null;

            // Roll the weighted-average cost forward only on incoming
            // stock with a known cost. Outgoing stock keeps the WAC where
            // it was — its cost basis stays whatever we last paid.
            //   new_wac = (old_qty * old_wac + delta * unit_cost) / new_qty
            if (bccomp($delta, '0', 8) > 0 && $unitCost !== null && bccomp($newQty, '0', 8) > 0) {
                if (bccomp($oldQty, '0', 8) <= 0) {
                    // Receiving into empty OR oversold (negative) stock: every
                    // unit that actually remains came from THIS receipt, so its
                    // unit cost IS the new average. The classic formula would
                    // spread the receipt value over only the netted-off units
                    // (e.g. -2 + 10 → value/8), inflating per-unit cost. See
                    // docs/features/inventory.md (negative stock).
                    $level->weighted_average_cost = $this->roundHalfUp($unitCost, 4);
                } else {
                    $oldWac   = (string) $level->weighted_average_cost;
                    $oldValue = bcmul($oldQty, $oldWac,   8);
                    $newValue = bcmul($delta,  $unitCost, 8);
                    $wacRaw   = bcdiv(bcadd($oldValue, $newValue, 8), $newQty, 8);
                    $level->weighted_average_cost = $this->roundHalfUp($wacRaw, 4);
                }
            }

            // Persist quantities at the column's native 4dp scale so
            // the level row + ledger row use the same string everywhere
            // downstream (no DB-side rounding ambiguity).
            $deltaPersist  = $this->roundHalfUp($delta,  4);
            $newQtyPersist = $this->roundHalfUp($newQty, 4);

            $fields = apply_filters('stock.before_movement', [
                'store_id'       => $storeId,
                'product_id'     => $productId,
                'variant_id'     => $variantId,
                'batch_id'       => $batchId,
                'quantity_delta' => $deltaPersist,
                'quantity_after' => $newQtyPersist,
                'type'           => $type,
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
                'unit_cost'      => $unitCost,
                'notes'          => $notes,
                'created_by'     => $createdBy,
                'created_at'     => Carbon::now(),
            ]);

            // Apply any mutations from the filter back onto the level
            // we're about to persist, so the ledger row and the level
            // continue to agree.
            $level->quantity         = $fields['quantity_after'];
            $level->last_movement_at = $fields['created_at'];
            $level->save();

            // Batch-tracked products: also adjust the specific batch's
            // running quantity. Purchase receive seeds the batch with
            // its starting quantity; every subsequent movement that
            // names a batch_id (sale, return, adjustment, transfer) must
            // keep the batch row in step with the level so per-batch
            // FEFO picks stay accurate. Locked first to serialize
            // concurrent decrements on the same batch.
            if (! empty($fields['batch_id'])) {
                // `withTrashed()`: an ARCHIVED batch must still track its
                // quantity. A batch sold down to zero can be archived and then
                // have stock come back — a void or a return reverses the
                // original movement against that same historical batch_id.
                // Skipping it would push the quantity onto the store level with
                // no batch behind it, exactly the stranding archiving exists to
                // prevent.
                $batch = ProductBatch::query()
                    ->withTrashed()
                    ->where('id', $fields['batch_id'])
                    ->lockForUpdate()
                    ->first();

                if ($batch) {
                    $batch->quantity = bcadd(
                        (string) $batch->quantity,
                        (string) $fields['quantity_delta'],
                        4,
                    );
                    $batch->save();

                    // Stock is physically back in an archived batch — un-archive
                    // it so FEFO can pick it and the count is visible again.
                    if ($batch->trashed() && bccomp((string) $batch->quantity, '0', 4) > 0) {
                        $batch->restore();
                    }
                }
            }

            $movement = StockMovement::create($fields);

            do_action('stock.after_movement', $movement);

            return $movement;
        });
    }

    /**
     * Round-half-up (away from zero) for a bcmath string at the given scale.
     * Convention matches PHP_ROUND_HALF_UP and ComputePurchaseTotals — the
     * financial standard. e.g. 0.00005 → 0.0001; -0.00005 → -0.0001.
     */
    private function roundHalfUp(string $value, int $scale): string
    {
        $sign = bccomp($value, '0', $scale + 4) < 0 ? '-' : '';
        $abs  = ltrim($value, '-');
        $half = '0.'.str_repeat('0', $scale).'5';
        $nudged = bcadd($abs, $half, $scale + 1);
        return $sign.bcadd($nudged, '0', $scale);
    }
}
