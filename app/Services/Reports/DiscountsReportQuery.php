<?php

namespace App\Services\Reports;

use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Query engine for the Discounts report — one row per order-level discount
 * (from `sale_discounts`), showing the sale, who applied it, the amount +
 * type/value, the reason + category, and the manager who approved it when
 * it was over threshold. The "catch discount abuse" view.
 *
 * (Line-level discounts live on `sale_items.discount_amount`; surfacing
 * those individually is a later enhancement — this report covers the
 * governed order-level discounts.)
 */
class DiscountsReportQuery
{
    /** @return Collection<int, array<string, mixed>> */
    public function __invoke(CarbonImmutable $from, CarbonImmutable $to, ?int $storeId = null): Collection
    {
        $fromDate = $from->toDateString();
        $toDate   = $to->toDateString();

        $rows = DB::table('sale_discounts as sd')
            ->join('sales as s', 's.id', '=', 'sd.sale_id')
            ->leftJoin('users as u', 'u.id', '=', 'sd.applied_by')
            ->leftJoin('users as ap', 'ap.id', '=', 's.discount_approved_by')
            ->whereNull('s.deleted_at')
            ->whereNull('s.voided_at')
            ->where('s.status', Sale::STATUS_COMPLETED)
            ->whereBetween('s.sale_date', [$fromDate, $toDate])
            ->when($storeId, fn ($q) => $q->where('s.store_id', $storeId))
            ->selectRaw('
                s.id            AS sale_id,
                s.number,
                s.sale_date,
                u.name          AS cashier,
                ap.name         AS approver,
                sd.type,
                sd.value,
                sd.amount,
                sd.reason,
                sd.reason_category
            ')
            ->orderByDesc('s.sale_date')
            ->orderByDesc('sd.amount')
            ->get();

        return $rows->map(fn ($r) => [
            'sale_id'         => $r->sale_id,
            'number'          => (string) $r->number,
            'date'            => (string) $r->sale_date,
            'cashier'         => (string) ($r->cashier ?? '—'),
            'approver'        => $r->approver ? (string) $r->approver : null,
            'type'            => (string) $r->type,
            'value'           => number_format((float) $r->value, 4, '.', ''),
            'amount'          => number_format((float) $r->amount, 4, '.', ''),
            'reason'          => $r->reason ? (string) $r->reason : null,
            'reason_category' => $r->reason_category ? (string) $r->reason_category : null,
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, string|int>
     */
    public function summarise(Collection $rows): array
    {
        $total = 0.0;
        foreach ($rows as $r) {
            $total += (float) $r['amount'];
        }

        return [
            'count'    => $rows->count(),
            'total'    => number_format($total, 4, '.', ''),
            'avg'      => number_format($rows->count() > 0 ? $total / $rows->count() : 0, 4, '.', ''),
            'approved' => $rows->whereNotNull('approver')->count(),
        ];
    }
}
