<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Demo historical sales — ~90 days of completed sales so the dashboard, charts,
 * and reports look alive on first login (docs/features/installer.md §9.3).
 *
 * Run by the installer's `SeedDemoData` action in "full" mode only (after
 * products + customers + received stock exist). Also runnable standalone via
 * /seed?class=HistoricalSalesDemoSeeder.
 *
 * Deliberate demo simplifications (it's visual data, not a real ledger):
 *   - Sales are inserted directly with historical dates; stock is NOT
 *     decremented, so the demo catalogue stays comfortably in stock and no
 *     negative-stock guard trips over 90 days of activity.
 *   - Tax is the simple sum of the product's tax-group component rates
 *     (exclusive). Returns are a separate follow-up.
 *
 * Idempotent: every demo sale number is prefixed `DEMO-`; if any exist the
 * seeder no-ops, so re-running (or re-installing over data) is safe.
 */
class HistoricalSalesDemoSeeder extends Seeder
{
    private const DAYS = 90;
    private const NUMBER_PREFIX = 'DEMO-';

    public function run(): void
    {
        if (DB::table('sales')->where('number', 'like', self::NUMBER_PREFIX.'%')->exists()) {
            $this->command?->line('Demo sales already present — skipping.');
            return;
        }

        $store = DB::table('stores')->where('is_active', true)->orderBy('id')->first();
        $cashierId = DB::table('users')->orderBy('id')->value('id');

        if (! $store || ! $cashierId) {
            $this->command?->line('No store / user yet — skipping demo sales.');
            return;
        }

        // Sellable products with a positive price. Without these there's
        // nothing to ring up.
        $units = DB::table('units')->pluck('code', 'id');
        $products = DB::table('products')
            ->where('is_active', true)
            ->where('selling_price', '>', 0)
            ->get(['id', 'name', 'sku', 'barcode', 'selling_price', 'cost_price', 'unit_id', 'tax_group_id'])
            ->all();

        if ($products === []) {
            $this->command?->line('No sellable products — skipping demo sales.');
            return;
        }

        $rateMap        = $this->taxRateMap();
        $customerIds    = DB::table('customers')->where('is_active', true)->orderBy('id')->pluck('id')->all();
        $frequentIds    = array_slice($customerIds, 0, 15);  // a loyal core, per §9.3
        $paymentPool    = $this->weightedPaymentPool();

        if ($paymentPool === []) {
            $this->command?->line('No payment methods — skipping demo sales.');
            return;
        }

        $currency = $store->currency_code;
        $seq = 0;
        $itemRows = [];
        $paymentRows = [];
        $saleCount = 0;

        for ($offset = self::DAYS - 1; $offset >= 0; $offset--) {
            $date = Carbon::today()->subDays($offset);
            $isWeekend = in_array($date->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY], true);

            // Weekends are busier (§9.3). Ranges land the 90-day total in a
            // believable few-thousand-sale band.
            $salesToday = $isWeekend ? mt_rand(18, 45) : mt_rand(5, 22);

            for ($i = 0; $i < $salesToday; $i++) {
                $when = $date->copy()->setTime(mt_rand(9, 20), mt_rand(0, 59), mt_rand(0, 59));

                // Build 1–5 line items from distinct random products.
                $lineCount = min(mt_rand(1, 5), count($products));
                $picks = (array) array_rand($products, $lineCount);

                $lines = [];
                $subtotal = '0';
                $taxTotal = '0';
                $sort = 0;
                foreach ($picks as $idx) {
                    $p = $products[$idx];
                    $qty = mt_rand(1, 3);
                    $price = (string) $p->selling_price;
                    $lineSub = bcmul((string) $qty, $price, 4);
                    $rate = $rateMap[$p->tax_group_id] ?? '0';
                    $taxAmt = bcdiv(bcmul($lineSub, $rate, 6), '100', 4);
                    $lineTotal = bcadd($lineSub, $taxAmt, 4);

                    $subtotal = bcadd($subtotal, $lineSub, 4);
                    $taxTotal = bcadd($taxTotal, $taxAmt, 4);

                    $lines[] = [
                        'product_id'           => $p->id,
                        'product_name_snapshot' => $p->name,
                        'sku_snapshot'         => $p->sku,
                        'barcode_snapshot'     => $p->barcode,
                        'quantity'             => $qty,
                        'unit'                 => $units[$p->unit_id] ?? 'pc',
                        'unit_price'           => $price,
                        'unit_cost_snapshot'   => (string) ($p->cost_price ?? 0),
                        'tax_group_id'         => $p->tax_group_id,
                        'tax_amount'           => $taxAmt,
                        'line_subtotal'        => $lineSub,
                        'line_total'           => $lineTotal,
                        'sort_order'           => $sort++,
                    ];
                }

                $grand = bcadd($subtotal, $taxTotal, 4);

                $saleId = DB::table('sales')->insertGetId([
                    'store_id'      => $store->id,
                    'cashier_id'    => $cashierId,
                    'customer_id'   => $this->pickCustomer($frequentIds, $customerIds),
                    'number'        => self::NUMBER_PREFIX.sprintf('%06d', ++$seq),
                    'sale_date'     => $when->toDateString(),
                    'sale_datetime' => $when,
                    'status'        => 'completed',
                    'currency_code' => $currency,
                    'subtotal'      => $subtotal,
                    'tax_total'     => $taxTotal,
                    'grand_total'   => $grand,
                    'paid_total'    => $grand,
                    'balance_due'   => '0',
                    'change_returned' => '0',
                    'created_at'    => $when,
                    'updated_at'    => $when,
                ]);

                foreach ($lines as $line) {
                    $itemRows[] = array_merge($line, ['sale_id' => $saleId]);
                }

                $paymentRows[] = [
                    'sale_id'           => $saleId,
                    'payment_method_id' => $paymentPool[array_rand($paymentPool)],
                    'amount'            => $grand,
                    'tendered_amount'   => $grand,
                    'change_returned'   => '0',
                    'currency_code'     => $currency,
                    'created_at'        => $when,
                    'updated_at'        => $when,
                ];
                $saleCount++;

                // Flush in chunks to keep memory flat over thousands of rows.
                if (count($itemRows) >= 500) {
                    DB::table('sale_items')->insert($itemRows);
                    $itemRows = [];
                }
            }
        }

        if ($itemRows !== []) {
            DB::table('sale_items')->insert($itemRows);
        }
        foreach (array_chunk($paymentRows, 500) as $chunk) {
            DB::table('sale_payments')->insert($chunk);
        }

        $this->command?->info("Seeded {$saleCount} demo sales across ".self::DAYS.' days.');
    }

    /** tax_group_id → total component rate (string), for simple exclusive tax. */
    private function taxRateMap(): array
    {
        return DB::table('tax_group_components')
            ->join('tax_components', 'tax_components.id', '=', 'tax_group_components.tax_component_id')
            ->groupBy('tax_group_components.tax_group_id')
            ->selectRaw('tax_group_components.tax_group_id as gid, COALESCE(SUM(tax_components.rate), 0) as rate')
            ->pluck('rate', 'gid')
            ->map(fn ($r) => (string) $r)
            ->all();
    }

    /**
     * A pool of payment-method ids weighted toward cash/card so the payment-mix
     * report looks like a real store. Returns ids repeated by weight.
     *
     * @return array<int, int>
     */
    private function weightedPaymentPool(): array
    {
        $weights = ['cash' => 55, 'card' => 28, 'upi' => 10, 'bank_transfer' => 4, 'cheque' => 3];
        $methods = DB::table('payment_methods')->where('is_active', true)->get(['id', 'code']);

        $pool = [];
        foreach ($methods as $m) {
            $w = $weights[$m->code] ?? 5;
            for ($i = 0; $i < $w; $i++) {
                $pool[] = (int) $m->id;
            }
        }

        return $pool;
    }

    /**
     * Realistic customer distribution: loyal regulars carry most of the named
     * sales, a long tail are one-offs, and roughly half are anonymous walk-ins.
     *
     * @param array<int,int> $frequent
     * @param array<int,int> $all
     */
    private function pickCustomer(array $frequent, array $all): ?int
    {
        if ($all === []) {
            return null;
        }

        $roll = mt_rand(1, 100);
        if ($roll <= 35 && $frequent !== []) {
            return $frequent[array_rand($frequent)];
        }
        if ($roll <= 50) {
            return $all[array_rand($all)];
        }

        return null; // walk-in
    }
}
