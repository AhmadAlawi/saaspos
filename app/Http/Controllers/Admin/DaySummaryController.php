<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "End of day" report — a topbar modal the admin can open from any screen to
 * see the whole day's activity for the active store at a glance: sales +
 * purchase summaries, the cash/bank money-in vs money-out breakdown, a profit
 * snapshot (COGS, gross & net profit, margins) and tax collected vs paid —
 * without opening the dashboard or any report page.
 *
 * Read-only JSON; the modal renders + formats it client-side. Gated by
 * `reports.view_financial`.
 */
class DaySummaryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('reports.view_financial'), 403);

        $storeId = session('active_store_id') ?: \App\Models\Store::query()->value('id');
        $today   = now()->toDateString();

        $sales     = $this->sales($storeId, $today);
        $purchases = $this->purchases($storeId, $today);
        $cogs      = $this->cogs($storeId, $today);
        $expenses  = $this->expensesToday($storeId, $today);

        // Profit. Revenue net of tax = taxable sales (gross − discounts).
        $taxableSales = $sales['gross'] - $sales['discounts'];
        $grossProfit  = $taxableSales - $cogs;
        $netProfit    = $grossProfit - $expenses;

        return response()->json([
            'date'  => now()->format('M j, Y'),
            'sales' => $sales,
            'purchases' => $purchases,
            'payments' => [
                'received' => $this->received($storeId, $today),
                'given'    => $this->given($storeId, $today),
            ],
            'stock'  => $this->stock($storeId),
            'profit' => [
                'cogs'           => $cogs,
                'gross_profit'   => $grossProfit,
                'gross_margin'   => $taxableSales > 0 ? round($grossProfit / $taxableSales * 100, 1) : 0.0,
                'expenses'       => $expenses,
                'net_profit'     => $netProfit,
                'net_margin'     => $taxableSales > 0 ? round($netProfit / $taxableSales * 100, 1) : 0.0,
                'tax_collected'  => $sales['tax'],
                'tax_paid'       => $purchases['tax'],
            ],
            'ops' => [
                'new_customers'     => (int) Customer::query()->whereDate('created_at', $today)->count(),
                'low_stock'         => $this->lowStock($storeId),
                'cashiers_on_shift' => (int) Shift::forStore($storeId)->open()->count(),
            ],
        ]);
    }

    /** @return array<string, float|int> */
    private function sales(mixed $storeId, string $today): array
    {
        $s = Sale::completed()
            ->forStore($storeId)
            ->whereDate('sale_date', $today)
            ->selectRaw('COUNT(*) txns, COALESCE(SUM(subtotal),0) gross, COALESCE(SUM(discount_total),0) disc, COALESCE(SUM(tax_total),0) tax, COALESCE(SUM(grand_total),0) net')
            ->first();

        $items = (float) SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.store_id', $storeId)
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereDate('sales.sale_date', $today)
            ->sum('sale_items.quantity');

        $txns = (int) ($s->txns ?? 0);
        $net  = (float) ($s->net ?? 0);

        return [
            'transactions' => $txns,
            'gross'        => (float) ($s->gross ?? 0),
            'discounts'    => (float) ($s->disc ?? 0),
            'taxable'      => (float) ($s->gross ?? 0) - (float) ($s->disc ?? 0),
            'tax'          => (float) ($s->tax ?? 0),
            'net'          => $net,
            'items'        => $items,
            'returns'      => $this->returnsTotal('sale_returns', 'return_date', $storeId, $today),
            'average'      => $txns > 0 ? round($net / $txns, 4) : 0.0,
        ];
    }

    /** @return array<string, float|int> */
    private function purchases(mixed $storeId, string $today): array
    {
        if (! Schema::hasTable('purchases')) {
            return $this->emptySummary();
        }

        $base = fn () => DB::table('purchases')
            ->where('store_id', $storeId)
            ->whereDate('purchase_date', $today)
            ->whereNotIn('status', ['draft', 'cancelled']);

        $p = $base()
            ->selectRaw('COUNT(*) txns, COALESCE(SUM(subtotal),0) gross, COALESCE(SUM(discount_total),0) disc, COALESCE(SUM(tax_total),0) tax, COALESCE(SUM(grand_total),0) net')
            ->first();

        $items = (float) DB::table('purchase_items')
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->where('purchases.store_id', $storeId)
            ->whereDate('purchases.purchase_date', $today)
            ->whereNotIn('purchases.status', ['draft', 'cancelled'])
            ->sum('purchase_items.quantity');

        $txns = (int) ($p->txns ?? 0);
        $net  = (float) ($p->net ?? 0);

        return [
            'transactions' => $txns,
            'gross'        => (float) ($p->gross ?? 0),
            'discounts'    => (float) ($p->disc ?? 0),
            'taxable'      => (float) ($p->gross ?? 0) - (float) ($p->disc ?? 0),
            'tax'          => (float) ($p->tax ?? 0),
            'net'          => $net,
            'items'        => $items,
            'returns'      => $this->returnsTotal('purchase_returns', 'return_date', $storeId, $today),
            'average'      => $txns > 0 ? round($net / $txns, 4) : 0.0,
        ];
    }

    /** Money IN today — sale tenders split cash vs bank (everything non-cash). @return array<string,float> */
    private function received(mixed $storeId, string $today): array
    {
        $r = DB::table('sale_payments as sp')
            ->join('sales', 'sales.id', '=', 'sp.sale_id')
            ->join('payment_methods as pm', 'pm.id', '=', 'sp.payment_method_id')
            ->where('sales.store_id', $storeId)
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereDate('sales.sale_date', $today)
            ->selectRaw("COALESCE(SUM(CASE WHEN pm.type = 'cash' THEN sp.amount ELSE 0 END),0) cash,
                         COALESCE(SUM(CASE WHEN pm.type <> 'cash' THEN sp.amount ELSE 0 END),0) bank")
            ->first();

        return $this->cashBank((float) ($r->cash ?? 0), (float) ($r->bank ?? 0));
    }

    /** Money OUT today — supplier (purchase) payments split cash vs bank. @return array<string,float> */
    private function given(mixed $storeId, string $today): array
    {
        if (! Schema::hasTable('purchase_payments')) {
            return $this->cashBank(0, 0);
        }

        $q = DB::table('purchase_payments as pp')
            ->join('payment_methods as pm', 'pm.id', '=', 'pp.payment_method_id')
            ->where('pp.store_id', $storeId)
            ->whereDate('pp.payment_date', $today);

        if (Schema::hasColumn('purchase_payments', 'voided_at')) {
            $q->whereNull('pp.voided_at');
        }

        $g = $q->selectRaw("COALESCE(SUM(CASE WHEN pm.type = 'cash' THEN pp.amount ELSE 0 END),0) cash,
                            COALESCE(SUM(CASE WHEN pm.type <> 'cash' THEN pp.amount ELSE 0 END),0) bank")
            ->first();

        return $this->cashBank((float) ($g->cash ?? 0), (float) ($g->bank ?? 0));
    }

    /** Cost of goods sold today — sum of sold items' cost snapshot × qty. */
    private function cogs(mixed $storeId, string $today): float
    {
        return (float) DB::table('sale_items as si')
            ->join('sales', 'sales.id', '=', 'si.sale_id')
            ->where('sales.store_id', $storeId)
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereDate('sales.sale_date', $today)
            ->selectRaw('COALESCE(SUM(si.unit_cost_snapshot * si.quantity),0) c')
            ->value('c');
    }

    /**
     * Current stock position for the store — on-hand valued at cost and at
     * retail, plus low/out-of-stock counts. Valuation covers product-level
     * stock rows (variant_id IS NULL) at the product's cost/selling price; a
     * true daily opening/closing snapshot isn't kept, so this is the live
     * position rather than an accounting roll-forward.
     *
     * @return array<string, float|int>
     */
    private function stock(mixed $storeId): array
    {
        $v = DB::table('product_stock_levels as psl')
            ->join('products', 'products.id', '=', 'psl.product_id')
            ->where('psl.store_id', $storeId)
            ->whereNull('psl.variant_id')
            ->whereNull('products.deleted_at')
            ->selectRaw('COALESCE(SUM(psl.quantity * products.cost_price),0) cost,
                         COALESCE(SUM(psl.quantity * products.selling_price),0) retail,
                         COALESCE(SUM(psl.quantity),0) units')
            ->first();

        $outOfStock = (int) DB::table('product_stock_levels as psl')
            ->join('products', 'products.id', '=', 'psl.product_id')
            ->where('psl.store_id', $storeId)
            ->whereNull('psl.variant_id')
            ->whereNull('products.deleted_at')
            ->where('products.track_stock', true)
            ->where('psl.quantity', '<=', 0)
            ->count();

        $cost   = (float) ($v->cost ?? 0);
        $retail = (float) ($v->retail ?? 0);

        return [
            'value_cost'    => $cost,
            'value_retail'  => $retail,
            'potential'     => $retail - $cost,   // unrealised margin sitting on the shelf
            'units'         => (float) ($v->units ?? 0),
            'low_stock'     => $this->lowStock($storeId),
            'out_of_stock'  => $outOfStock,
        ];
    }

    private function lowStock(mixed $storeId): int
    {
        return (int) DB::table('product_stock_levels as psl')
            ->where('psl.store_id', $storeId)
            ->whereNull('psl.variant_id')
            ->join('products', 'products.id', '=', 'psl.product_id')
            ->whereRaw('psl.quantity <= COALESCE(psl.reorder_level_override, products.reorder_level, 0)')
            ->count();
    }

    private function returnsTotal(string $table, string $dateCol, mixed $storeId, string $today): float
    {
        try {
            if (! Schema::hasTable($table)) {
                return 0.0;
            }

            return (float) DB::table($table)
                ->where('store_id', $storeId)
                ->whereDate($dateCol, $today)
                ->sum('grand_total');
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private function expensesToday(mixed $storeId, string $today): float
    {
        try {
            if (! Schema::hasTable('expenses')) {
                return 0.0;
            }

            return (float) DB::table('expenses')
                ->where('store_id', $storeId)
                ->whereNull('deleted_at')
                ->where('status', 'approved')
                ->whereDate('expense_date', $today)
                ->selectRaw('COALESCE(SUM(amount + tax_amount),0) t')
                ->value('t');
        } catch (\Throwable) {
            return 0.0;
        }
    }

    /** @return array<string, float> */
    private function cashBank(float $cash, float $bank): array
    {
        return ['cash' => $cash, 'bank' => $bank, 'total' => $cash + $bank];
    }

    /** @return array<string, float|int> */
    private function emptySummary(): array
    {
        return [
            'transactions' => 0, 'gross' => 0.0, 'discounts' => 0.0, 'taxable' => 0.0,
            'tax' => 0.0, 'net' => 0.0, 'items' => 0.0, 'returns' => 0.0, 'average' => 0.0,
        ];
    }
}
