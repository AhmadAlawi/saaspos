<?php

namespace App\Actions\Accounting;

use App\Models\Account;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Store;
use App\Services\Accounting\FiscalPeriodResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Year-end close (docs/features/accounting.md §4.4). Zeroes every income /
 * expense account into Retained Earnings, locks the year, and rolls forward to
 * the next one. After a close the P&L accounts start the new year at zero and
 * the profit lives permanently in equity.
 *
 * Requires every period in the year to be locked first. The closing entry is
 * dated the year-end (inside those locked periods), so it posts with `force`.
 */
class RunYearEndClose
{
    public function __construct(private PostJournalEntry $post) {}

    public function __invoke(FiscalYear $year): FiscalYear
    {
        if ($year->is_locked) {
            throw new \RuntimeException(__('accounting.close.errors.already_closed'));
        }
        if ($year->periods()->where('is_locked', false)->exists()) {
            throw new \RuntimeException(__('accounting.close.errors.not_locked'));
        }

        $from = CarbonImmutable::parse($year->start_date);
        $to   = CarbonImmutable::parse($year->end_date);

        // Net position of each P&L account over the year.
        $accounts = DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'jl.account_id')
            ->where('je.is_posted', true)
            ->whereIn('a.type', ['income', 'expense'])
            ->whereDate('je.entry_date', '>=', $from->toDateString())
            ->whereDate('je.entry_date', '<=', $to->toDateString())
            ->groupBy('a.id')
            ->selectRaw('a.id, COALESCE(SUM(jl.debit) - SUM(jl.credit), 0) AS net')
            ->get();

        $lines  = [];
        $zeroDr = '0';
        $zeroCr = '0';
        foreach ($accounts as $acc) {
            $net = (string) $acc->net;
            $cmp = bccomp($net, '0', 4);
            if ($cmp === 0) {
                continue;
            }

            if ($cmp > 0) { // debit balance (expense) → credit it to zero
                $lines[] = ['account_id' => $acc->id, 'debit' => 0, 'credit' => $net, 'description' => __('accounting.close.zero_expense')];
                $zeroCr  = bcadd($zeroCr, $net, 4);
            } else {         // credit balance (income) → debit it to zero
                $amount  = bcmul($net, '-1', 4);
                $lines[] = ['account_id' => $acc->id, 'debit' => $amount, 'credit' => 0, 'description' => __('accounting.close.zero_income')];
                $zeroDr  = bcadd($zeroDr, $amount, 4);
            }
        }

        // Retained Earnings takes the balancing net profit / loss.
        $netProfit = bcsub($zeroDr, $zeroCr, 4);
        if (bccomp($netProfit, '0', 4) !== 0) {
            $retainedId = (int) Account::where('code', '3030')->value('id');
            $lines[] = bccomp($netProfit, '0', 4) > 0
                ? ['account_id' => $retainedId, 'debit' => 0, 'credit' => $netProfit, 'description' => __('accounting.close.retained')]
                : ['account_id' => $retainedId, 'debit' => bcmul($netProfit, '-1', 4), 'credit' => 0, 'description' => __('accounting.close.retained')];
        }

        return DB::transaction(function () use ($year, $lines, $to) {
            if (count($lines) >= 2) {
                ($this->post)([
                    'store_id'       => (int) (current_store_id() ?: Store::query()->value('id')),
                    'entry_date'     => $to->toDateString(),
                    'source'         => JournalEntry::SOURCE_CLOSING_ENTRY,
                    'reference_type' => FiscalYear::class,
                    'reference_id'   => $year->id,
                    'description'    => __('accounting.close.description', ['year' => $year->name]),
                    'force'          => true, // year-end sits inside locked periods
                    'lines'          => $lines,
                ]);
            }

            $year->forceFill([
                'is_locked' => true,
                'locked_at' => now(),
                'locked_by' => auth()->id(),
            ])->save();

            // Roll forward: materialise the next fiscal year + its periods.
            app(FiscalPeriodResolver::class)->forDate($to->addDay());

            do_action('fiscal_year.closed', $year);

            return $year->fresh();
        });
    }
}
