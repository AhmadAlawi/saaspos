<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Shared date-range + store resolution for the report screens. Backs the
 * universal toolbar's period selector (docs/features/reports.md §3.1): a
 * `period` preset ("this_month", "last_7_days", …) is resolved server-side
 * (authoritative for the store's timezone), falling back to explicit
 * from/to dates when the preset is "custom".
 *
 * "This"-periods run period-start → today (month-to-date, week-to-date,
 * year-to-date) — the most useful framing for a shop owner — while past
 * periods (yesterday, last month, …) use their full range.
 */
trait ResolvesReportFilters
{
    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: ?int, 3: string} */
    protected function reportFilters(Request $request): array
    {
        $storeId = enforce_store_access($request->integer('store_id') ?: null);
        $preset  = (string) $request->query('period', '');

        if ($preset === '') {
            // No preset given: custom when explicit dates are present,
            // otherwise fall back to the default "this month" view.
            $preset = ($request->filled('from') || $request->filled('to')) ? 'custom' : 'this_month';
        }

        $range = $this->resolvePeriod($preset);
        if ($range !== null) {
            return [$range[0], $range[1], $storeId, $preset];
        }

        // Custom / unknown preset → explicit from/to (clamped).
        $from = $this->parseReportDate($request->query('from'), 'start');
        $to   = $this->parseReportDate($request->query('to'), 'end');
        if ($from->isAfter($to)) {
            $from = $to->startOfMonth();
        }

        return [$from, $to, $storeId, 'custom'];
    }

    /**
     * Map a preset key to a [from, to] range, or null for custom/unknown.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    protected function resolvePeriod(string $preset): ?array
    {
        return ReportPeriod::resolve($preset);
    }

    protected function parseReportDate(?string $raw, string $fallback): CarbonImmutable
    {
        try {
            if ($raw) {
                return CarbonImmutable::parse($raw)->startOfDay();
            }
        } catch (\Throwable) {}

        return $fallback === 'start'
            ? CarbonImmutable::now()->startOfMonth()->startOfDay()
            : CarbonImmutable::today()->startOfDay();
    }

    /** Normalise the requested export format to one we support. */
    protected function reportFormat(Request $request): string
    {
        $format = strtolower((string) $request->query('format'));

        return in_array($format, ['csv', 'xlsx', 'pdf'], true) ? $format : 'csv';
    }

    /** The preset keys offered in the toolbar, in display order. */
    public static function reportPeriodPresets(): array
    {
        return ReportPeriod::presets();
    }
}
