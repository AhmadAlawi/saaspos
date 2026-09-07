<?php

namespace App\Services\Accounting;

use App\Models\Company;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Maps an entry date to the {@see FiscalPeriod} it belongs in, creating the
 * fiscal year and its 12 monthly periods on demand when none exists yet.
 *
 * Fiscal years are never created at install (only `company.fiscal_year_start_month`
 * is set), so the first journal entry of any year lazily materialises it. This
 * keeps journal posting from ever failing for a missing period.
 */
class FiscalPeriodResolver
{
    public function forDate(\DateTimeInterface|string $date): FiscalPeriod
    {
        $date = $this->normalize($date);

        $period = $this->find($date);
        if ($period) {
            return $period;
        }

        $this->createYearContaining($date);

        // Re-query rather than returning a handle from creation so a concurrent
        // creator's row is honoured too.
        $period = $this->find($date);
        if (! $period) {
            throw new \RuntimeException("Could not resolve a fiscal period for {$date->toDateString()}.");
        }

        return $period;
    }

    private function find(CarbonImmutable $date): ?FiscalPeriod
    {
        return FiscalPeriod::query()
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->first();
    }

    /** The month (1-12) the fiscal year starts on; defaults to April. */
    private function startMonth(): int
    {
        $month = (int) (Company::current()?->fiscal_year_start_month ?: 4);

        return max(1, min(12, $month));
    }

    private function createYearContaining(CarbonImmutable $date): void
    {
        $startMonth = $this->startMonth();

        // The fiscal year starts at `startMonth`; a date before that month
        // belongs to the year that opened in the previous calendar year.
        $startYear = $date->month >= $startMonth ? $date->year : $date->year - 1;
        $start     = CarbonImmutable::create($startYear, $startMonth, 1)->startOfDay();
        $end       = $start->addYear()->subDay();

        $name = $start->year === $end->year
            ? 'FY '.$start->year
            : 'FY '.$start->year.'-'.$end->year;

        DB::transaction(function () use ($start, $end, $name) {
            $year = FiscalYear::firstOrCreate(
                ['start_date' => $start->toDateString()],
                ['name' => $name, 'end_date' => $end->toDateString(), 'is_locked' => false],
            );

            if ($year->periods()->exists()) {
                return; // already materialised (idempotent re-run)
            }

            $rows = [];
            for ($i = 0; $i < 12; $i++) {
                $pStart = $start->addMonths($i);
                $pEnd   = $pStart->endOfMonth();
                $rows[] = [
                    'fiscal_year_id' => $year->id,
                    'name'           => $pStart->format('M Y'),
                    'start_date'     => $pStart->toDateString(),
                    'end_date'       => $pEnd->toDateString(),
                    'is_locked'      => false,
                ];
            }

            FiscalPeriod::insert($rows);
        });
    }

    private function normalize(\DateTimeInterface|string $date): CarbonImmutable
    {
        if ($date instanceof \DateTimeInterface) {
            return CarbonImmutable::parse($date->format('Y-m-d'))->startOfDay();
        }

        return CarbonImmutable::parse($date)->startOfDay();
    }
}
