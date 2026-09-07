<?php

namespace App\Actions\Shifts;

use App\Models\CashDrawerEntry;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\Shift;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Roll up everything a shift needs to compute its X-report (live) or
 * Z-report (frozen at close). Single source of math so X and Z agree.
 *
 * Returned shape — all amounts are 4dp decimal-strings, every field
 * already settled to the same scale `format_money()` consumes:
 *
 *   [
 *     'opening_cash'    => '1000.0000',
 *     'cash_sales'      => '3800.0000',
 *     'cash_refunds'    => '200.0000',
 *     'pay_ins'         => '150.0000',
 *     'pay_outs'        => '500.0000',
 *     'expected_cash'   => '4250.0000',
 *
 *     'sales_count'     => 42,
 *     'sales_total'     => '11850.0000',
 *     'refunds_count'   => 2,
 *     'refunds_total'   => '200.0000',
 *
 *     'tax_total'       => '2133.0000',
 *     'discount_total'  => '350.0000',
 *
 *     'payment_totals'  => [
 *       ['method_id' => 1, 'code' => 'cash', 'name' => 'Cash', 'type' => 'cash', 'amount' => '3800.0000'],
 *       …
 *     ],
 *     'drawer_open_no_sale_count' => 0,
 *   ]
 */
class ComputeShiftTotals
{
    /** @return array<string, mixed> */
    public function __invoke(Shift $shift): array
    {
        $shiftId = (int) $shift->id;

        // ── Sales ────────────────────────────────────────────────
        $salesAgg = Sale::query()
            ->where('shift_id', $shiftId)
            ->where('status', Sale::STATUS_COMPLETED)
            ->selectRaw('COUNT(*) AS c, COALESCE(SUM(grand_total),0) AS gt, COALESCE(SUM(tax_total),0) AS tt, COALESCE(SUM(discount_total),0) AS dt')
            ->first();

        $salesCount    = (int) ($salesAgg->c ?? 0);
        $salesTotal    = $this->fmt($salesAgg->gt ?? '0');
        $taxTotal      = $this->fmt($salesAgg->tt ?? '0');
        $discountTotal = $this->fmt($salesAgg->dt ?? '0');

        // ── Refunds ──────────────────────────────────────────────
        $refundsAgg = SaleReturn::query()
            ->where('shift_id', $shiftId)
            ->selectRaw('COUNT(*) AS c, COALESCE(SUM(grand_total),0) AS gt, COALESCE(SUM(refunded_in_cash),0) AS cash')
            ->first();

        $refundsCount = (int) ($refundsAgg->c ?? 0);
        $refundsTotal = $this->fmt($refundsAgg->gt ?? '0');
        $cashRefunds  = $this->fmt($refundsAgg->cash ?? '0');

        // ── Cash payments received during the shift ──────────────
        // Join payments to methods so we can isolate cash. Doing it as
        // one query keeps the math single-pass even on busy shifts.
        $paymentRows = SalePayment::query()
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->join('payment_methods', 'payment_methods.id', '=', 'sale_payments.payment_method_id')
            ->where('sales.shift_id', $shiftId)
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->selectRaw('payment_methods.id AS method_id, payment_methods.code, payment_methods.name, payment_methods.type, COALESCE(SUM(sale_payments.amount),0) AS amt')
            ->groupBy('payment_methods.id', 'payment_methods.code', 'payment_methods.name', 'payment_methods.type')
            ->get();

        $cashSales = '0';
        $paymentTotals = [];
        foreach ($paymentRows as $row) {
            $amt = $this->fmt($row->amt);
            if ($row->type === 'cash') {
                $cashSales = bcadd($cashSales, $amt, 4);
            }
            $paymentTotals[] = [
                'method_id' => (int) $row->method_id,
                'code'      => (string) $row->code,
                'name'      => (string) $row->name,
                'type'      => (string) $row->type,
                'amount'    => $amt,
            ];
        }

        // Backfill zero-rows for active cash + non-cash methods that
        // happened to have no payments this shift, so the Z-report has
        // a stable rendering.
        $seen = collect($paymentTotals)->pluck('method_id')->all();
        $missing = PaymentMethod::query()
            ->where('is_active', true)
            ->whereNotIn('id', $seen)
            ->get();
        foreach ($missing as $m) {
            $paymentTotals[] = [
                'method_id' => (int) $m->id,
                'code'      => (string) $m->code,
                'name'      => (string) $m->name,
                'type'      => (string) $m->type,
                'amount'    => '0.0000',
            ];
        }

        // ── Pay-ins / pay-outs / drawer-opens ────────────────────
        $entryAgg = CashDrawerEntry::query()
            ->where('shift_id', $shiftId)
            ->selectRaw("type, COALESCE(SUM(amount),0) AS amt, COUNT(*) AS c")
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $payIns    = $this->fmt($entryAgg->get(CashDrawerEntry::TYPE_PAY_IN)->amt ?? '0');
        $payOuts   = $this->fmt($entryAgg->get(CashDrawerEntry::TYPE_PAY_OUT)->amt ?? '0');
        $drawerOpenNoSale = (int) ($entryAgg->get(CashDrawerEntry::TYPE_DRAWER_OPEN_NO_SALE)->c ?? 0);

        // Supplier payments made from the till (pay-outs tagged with a
        // supplier_payment_uuid). The X/Z report lists them PER SUPPLIER by name
        // — a cash-up needs to see who was paid, not just a lump "Pay-outs".
        // Join each pay-out to a purchase_payments row (same client_uuid) to
        // recover the supplier; DISTINCT collapses the sibling allocation rows
        // that share a uuid so the amount isn't multiplied.
        $supplierRows = DB::table('cash_drawer_entries as e')
            ->leftJoin('purchase_payments as pp', 'pp.client_uuid', '=', 'e.supplier_payment_uuid')
            ->leftJoin('suppliers as s', 's.id', '=', 'pp.supplier_id')
            ->where('e.shift_id', $shiftId)
            ->where('e.type', CashDrawerEntry::TYPE_PAY_OUT)
            ->whereNotNull('e.supplier_payment_uuid')
            ->distinct()
            ->get(['e.id AS entry_id', 'e.amount AS amount', 's.name AS supplier_name']);

        // Group the per-entry amounts by supplier name (a shift may pay the same
        // supplier more than once).
        $bySupplier = [];
        $supplierPayouts     = '0';
        $supplierPayoutCount = 0;
        foreach ($supplierRows as $r) {
            $name = $r->supplier_name ?: __('shifts.totals.supplier_unknown');
            $bySupplier[$name]   = bcadd($bySupplier[$name] ?? '0', (string) $r->amount, 4);
            $supplierPayouts     = bcadd($supplierPayouts, (string) $r->amount, 4);
            $supplierPayoutCount++;
        }
        $supplierPayoutList = [];
        foreach ($bySupplier as $name => $amt) {
            $supplierPayoutList[] = ['supplier' => $name, 'amount' => $this->fmt($amt)];
        }
        $supplierPayouts = $this->fmt($supplierPayouts);

        $openingCash = $this->fmt((string) $shift->opening_cash);

        // ── Expected cash ────────────────────────────────────────
        // opening + cash sales − cash refunds + pay_ins − pay_outs
        $expectedCash = bcadd($openingCash, $cashSales, 4);
        $expectedCash = bcsub($expectedCash, $cashRefunds, 4);
        $expectedCash = bcadd($expectedCash, $payIns, 4);
        $expectedCash = bcsub($expectedCash, $payOuts, 4);

        // Plugin hook — let extensions inject custom movements
        // (custom income types, integrations).
        $expectedCash = apply_filters('shift.expected_cash_calculation', $expectedCash, $shift);

        return [
            'opening_cash'              => $openingCash,
            'cash_sales'                => $this->fmt($cashSales),
            'cash_refunds'              => $cashRefunds,
            'pay_ins'                   => $payIns,
            'pay_outs'                  => $payOuts,
            'supplier_payout_total'     => $supplierPayouts,
            'supplier_payout_count'     => $supplierPayoutCount,
            'supplier_payouts'          => $supplierPayoutList,
            'expected_cash'             => $this->fmt((string) $expectedCash),
            'sales_count'               => $salesCount,
            'sales_total'               => $salesTotal,
            'refunds_count'             => $refundsCount,
            'refunds_total'             => $refundsTotal,
            'tax_total'                 => $taxTotal,
            'discount_total'            => $discountTotal,
            'payment_totals'            => $paymentTotals,
            'drawer_open_no_sale_count' => $drawerOpenNoSale,
        ];
    }

    private function fmt(string|int|float $v): string
    {
        $s = (string) $v;
        if ($s === '' || $s === '-' || $s === '.') return '0.0000';
        return bcadd($s, '0', 4);
    }
}
