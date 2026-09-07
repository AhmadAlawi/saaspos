<?php

namespace Database\Seeders;

use App\Actions\Purchases\CreatePurchase;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\TaxGroup;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Demo purchases — 20 records spread across every status the UI shows.
 *
 *   8 × draft           — newest, no stock impact (matches Slice 2 reality)
 *   5 × received        — older, balance still open
 *   4 × partially_paid  — middle of payment cycle
 *   2 × paid            — fully settled
 *   1 × cancelled       — pre-receive cancel for filter testing
 *
 * Every row is built through {@see CreatePurchase} so totals match the
 * line items exactly. Non-draft statuses are stamped AFTER creation —
 * since the Receive / Record-Payment / Cancel actions don't exist yet
 * (Slices 3-4), this seeder writes `status` + `paid_total` + `balance_due`
 * directly for demo realism. **This is the only place doing that** —
 * production code goes through the dedicated actions.
 *
 * Idempotent: skips if any row's notes start with `[DEMO]`.
 *
 *   /seed?class=PurchasesDemoSeeder
 *
 * Depends on `SuppliersDemoSeeder`, `ProductsDemoSeeder`, a store, and
 * at least one user being in place. Skips with a warn if anything's missing.
 */
class PurchasesDemoSeeder extends Seeder
{
    public function run(CreatePurchase $create): void
    {
        if (Purchase::query()->where('notes', 'like', '[DEMO]%')->exists()) {
            $this->command?->line('Purchase demo data already present.');
            return;
        }

        $user      = User::query()->orderBy('id')->first();
        $store     = Store::query()->where('is_active', true)->orderBy('id')->first();
        $suppliers = Supplier::query()->active()->get();
        $products  = Product::query()->where('is_active', true)->limit(40)->get();
        $taxGroups = TaxGroup::query()->orderBy('id')->pluck('id');

        if (!$user || !$store || $suppliers->isEmpty() || $products->isEmpty()) {
            $this->command?->warn('Skipping — need a user, an active store, at least one supplier, and products.');
            return;
        }

        // 1 ← oldest, 20 ← newest. Status assignment uses the same index
        // so the index page (sorted DESC by id) opens with drafts on top.
        $statusByIndex = [
            // index → [status, paid_share]   (paid_share: fraction of grand_total already paid)
             1 => ['paid',           1.0],
             2 => ['paid',           1.0],
             3 => ['partially_paid', 0.4],
             4 => ['partially_paid', 0.6],
             5 => ['partially_paid', 0.3],
             6 => ['partially_paid', 0.7],
             7 => ['received',       0.0],
             8 => ['received',       0.0],
             9 => ['received',       0.0],
            10 => ['received',       0.0],
            11 => ['received',       0.0],
            12 => ['cancelled',      0.0],
            13 => ['draft',          0.0],
            14 => ['draft',          0.0],
            15 => ['draft',          0.0],
            16 => ['draft',          0.0],
            17 => ['draft',          0.0],
            18 => ['draft',          0.0],
            19 => ['draft',          0.0],
            20 => ['draft',          0.0],
        ];

        $created = 0;

        for ($i = 1; $i <= 20; $i++) {
            [$status, $paidShare] = $statusByIndex[$i];

            // Spread purchase_date across the last ~120 days. Older
            // records get older dates so the timeline reads naturally.
            $daysAgo = ($i <= 12) ? (130 - ($i * 8)) : (50 - (($i - 12) * 6));
            $date    = Carbon::now()->subDays(max($daysAgo, 1));

            $supplier = $suppliers[($i - 1) % $suppliers->count()];
            $currency = $supplier->default_currency_code ?: 'USD';
            $terms    = $supplier->payment_terms_days ?? 30;

            // 2-5 line items per purchase, randomised products.
            $lineCount = (($i * 3) % 4) + 2;
            $picks     = $products->shuffle()->take($lineCount);

            $lines = [];
            foreach ($picks as $j => $p) {
                $qty       = ((($i + $j) * 3) % 9) + 2;        // 2-10
                $unitCost  = round(((($i * 13) + ($j * 7)) % 950) + 50, 2); // 50-999.99
                $discount  = $j === 0 && ($i % 5 === 0) ? 5 : 0;
                $taxGroup  = $taxGroups->isNotEmpty() ? $taxGroups[$j % $taxGroups->count()] : null;
                $lines[] = [
                    'product_id'       => $p->id,
                    'quantity'         => (string) $qty,
                    'unit_cost'        => (string) $unitCost,
                    'discount_percent' => (string) $discount,
                    'tax_group_id'     => $taxGroup,
                ];
            }

            $header = [
                'supplier_id'             => $supplier->id,
                'store_id'                => $store->id,
                'purchase_date'           => $date->toDateString(),
                'due_date'                => $date->copy()->addDays($terms)->toDateString(),
                'supplier_invoice_number' => 'INV-'.strtoupper(str_pad((string) $i, 5, '0', STR_PAD_LEFT)),
                'currency_code'           => $currency,
                'exchange_rate_to_base'   => '1',
                'notes'                   => "[DEMO] Sample purchase #{$i} — status: {$status}.",
            ];

            $purchase = $create($header, $lines, $user);

            if ($status !== 'draft') {
                // Stamp the post-draft state directly. Once Slices 3-4
                // ship the Receive / RecordSupplierPayment actions, the
                // seeder should call those instead — for now this is
                // pure demo data, no stock or journal side effects.
                $grand     = (float) $purchase->grand_total;
                $paidTotal = $status === 'cancelled' ? 0 : round($grand * $paidShare, 4);
                $balance   = $status === 'cancelled' ? 0 : round($grand - $paidTotal, 4);

                $purchase->forceFill([
                    'status'      => $status,
                    'paid_total'  => $paidTotal,
                    'balance_due' => $balance,
                    'is_received_in_full' => in_array($status, ['received', 'partially_paid', 'paid'], true),
                ])->save();
            }

            $this->command?->info("Created purchase {$purchase->number} ({$status}, {$lineCount} lines).");
            $created++;
        }

        $this->command?->info("Purchases demo: created {$created} records.");
    }
}
