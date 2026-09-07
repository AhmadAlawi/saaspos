<?php

namespace App\Services\Customers;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the data backbone of a Customer Statement — the dated event
 * log + running balance you hand to the customer when they ask "why do
 * I owe you this much?".
 *
 * Events captured per (customer, dateFrom..dateTo):
 *   - SALE       → debit by `grand_total` (we billed the customer)
 *   - PAYMENT    → credit by `amount` (any sale_payment row tied to
 *                  the customer, including sale-time tenders)
 *   - REFUND     → credit by `grand_total` (cash/store credit returned)
 *   - VOID       → credit by the original sale's `grand_total` (the
 *                  bill is reversed; cash refund is physical)
 *
 * Opening balance = same math applied to everything BEFORE dateFrom,
 * starting from 0. Closing balance = opening + period_debits − period_credits
 * (and matches `customers.outstanding_balance` when dateTo = today).
 *
 * The shape is deliberately pure-array — easy to render in Blade,
 * easy to attach to a Mailable, easy to seed a future PDF renderer.
 *
 * @return array{
 *   customer:           Customer,
 *   from:               CarbonImmutable,
 *   to:                 CarbonImmutable,
 *   opening_balance:    string,
 *   closing_balance:    string,
 *   debits_total:       string,
 *   credits_total:      string,
 *   events:             Collection<int, array<string, mixed>>,
 * }
 */
class GenerateCustomerStatement
{
    /** @return array<string, mixed> */
    public function __invoke(
        Customer $customer,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
    ): array {
        $to   = ($to   ?? CarbonImmutable::today())->endOfDay();
        $from = ($from ?? $to->subMonths(3))->startOfDay();

        // Opening balance — apply the same SALES − PAYMENTS − REFUNDS
        // accounting to every event dated before `from`. Starting from 0,
        // because credit history began at install time.
        $opening = $this->balanceBefore($customer, $from);

        // Period events, sorted by date. Each row is one journal-like
        // line with EITHER a debit or a credit populated, never both.
        $events = $this->periodEvents($customer, $from, $to);

        $running     = $opening;
        $debitsSum   = '0';
        $creditsSum  = '0';

        $events = $events->map(function (array $event) use (&$running, &$debitsSum, &$creditsSum) {
            $debit  = (string) ($event['debit']  ?? '0');
            $credit = (string) ($event['credit'] ?? '0');
            $debitsSum  = bcadd($debitsSum,  $debit,  4);
            $creditsSum = bcadd($creditsSum, $credit, 4);
            $running    = bcsub(bcadd($running, $debit, 4), $credit, 4);
            $event['running_balance'] = $running;
            return $event;
        });

        return [
            'customer'        => $customer,
            'from'            => $from->startOfDay(),
            'to'              => $to->startOfDay(),
            'opening_balance' => $opening,
            'closing_balance' => $running,
            'debits_total'    => $debitsSum,
            'credits_total'   => $creditsSum,
            'events'          => $events,
        ];
    }

    /** Running balance contributed by everything BEFORE `$from`. */
    private function balanceBefore(Customer $customer, CarbonImmutable $from): string
    {
        // Voided sales are excluded entirely — they never "really"
        // billed the customer, so they don't appear on the statement
        // either as a debit or as a void credit. Cleanest model: as
        // far as the customer is concerned, the sale never happened.
        $sales = DB::table('sales')
            ->where('customer_id', $customer->id)
            ->whereNull('deleted_at')
            ->where('status', '!=', Sale::STATUS_VOIDED)
            ->whereDate('sale_date', '<', $from->toDateString())
            ->sum('grand_total');

        $payments = DB::table('sale_payments as p')
            ->join('sales as s', 's.id', '=', 'p.sale_id')
            ->whereNull('s.deleted_at')
            ->where('s.customer_id', $customer->id)
            ->whereDate('p.paid_at', '<', $from->toDateString())
            ->sum('p.amount');

        // Settlement-only rows (sale_id NULL, customer_id set) — pure
        // customer credit, never tied to a sale.
        $settlementCredits = DB::table('sale_payments')
            ->where('customer_id', $customer->id)
            ->whereNull('sale_id')
            ->whereDate('paid_at', '<', $from->toDateString())
            ->sum('amount');

        $refunds = DB::table('sale_returns as r')
            ->join('sales as s', 's.id', '=', 'r.sale_id')
            ->whereNull('s.deleted_at')
            ->where('s.customer_id', $customer->id)
            ->whereDate('r.return_date', '<', $from->toDateString())
            ->sum('r.grand_total');

        // bcsub((bcadd(sales,0)), (payments+settlement+refunds), 4)
        $debit  = (string) ($sales ?? '0');
        $credit = bcadd(bcadd((string) ($payments ?? '0'), (string) ($settlementCredits ?? '0'), 4),
                        (string) ($refunds ?? '0'), 4);

        return bcsub($debit, $credit, 4);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function periodEvents(Customer $customer, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $events = collect();

        // ─── Sales ──────────────────────────────────────────────────
        // Voided sales are excluded entirely (consistent with the
        // opening-balance math in balanceBefore()).
        Sale::query()
            ->where('customer_id', $customer->id)
            ->where('status', '!=', Sale::STATUS_VOIDED)
            ->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('sale_date')->orderBy('id')
            ->get(['id', 'number', 'sale_date', 'grand_total', 'status'])
            ->each(function (Sale $s) use ($events) {
                $events->push([
                    'date'         => $s->sale_date->format('Y-m-d'),
                    'sort_seq'     => 0, // sales render before payments same day
                    'type'         => 'sale',
                    'reference'    => $s->number,
                    'sale_id'      => $s->id,
                    'debit'        => (string) $s->grand_total,
                    'credit'       => '0',
                ]);
            });

        // ─── Payments tied to this customer's sales ────────────────
        SalePayment::query()
            ->where(function ($q) use ($customer) {
                $q->where('customer_id', $customer->id)
                  ->orWhereHas('sale', fn ($q) => $q->where('customer_id', $customer->id));
            })
            ->whereBetween('paid_at', [$from->startOfDay(), $to->endOfDay()])
            ->with('sale:id,number')
            ->orderBy('paid_at')->orderBy('id')
            ->get()
            ->each(function (SalePayment $p) use ($events) {
                $events->push([
                    'date'         => CarbonImmutable::parse($p->paid_at)->format('Y-m-d'),
                    'sort_seq'     => 1,
                    'type'         => 'payment',
                    'reference'    => $p->sale?->number ?? '—',
                    'sale_id'      => $p->sale_id,
                    'debit'        => '0',
                    'credit'       => (string) $p->amount,
                ]);
            });

        // ─── Refunds ───────────────────────────────────────────────
        SaleReturn::query()
            ->whereHas('sale', fn ($q) => $q->where('customer_id', $customer->id))
            ->whereBetween('return_date', [$from->toDateString(), $to->toDateString()])
            ->with('sale:id,number')
            ->orderBy('return_date')->orderBy('id')
            ->get(['id', 'sale_id', 'number', 'return_date', 'grand_total'])
            ->each(function (SaleReturn $r) use ($events) {
                $events->push([
                    'date'         => $r->return_date->format('Y-m-d'),
                    'sort_seq'     => 2,
                    'type'         => 'refund',
                    'reference'    => $r->number,
                    'sale_id'      => $r->sale_id,
                    'debit'        => '0',
                    'credit'       => (string) $r->grand_total,
                ]);
            });

        return $events
            ->sortBy([['date', 'asc'], ['sort_seq', 'asc']])
            ->values();
    }
}
