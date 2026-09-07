<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * The report period-preset vocabulary, resolved to concrete [from, to]
 * ranges. Shared by the interactive report toolbar
 * ({@see \App\Http\Controllers\Admin\Concerns\ResolvesReportFilters}) and the
 * headless scheduled-report runner ({@see \App\Services\Reports\ReportRunner})
 * so both agree on exactly what "last_month" means.
 *
 * "This"-periods run period-start → now (month-to-date, week-to-date,
 * year-to-date) — the most useful framing for a shop owner — while past
 * periods (yesterday, last month, …) use their full range.
 */
class ReportPeriod
{
    /** The preset keys offered in the toolbar, in display order. */
    public static function presets(): array
    {
        return [
            'today', 'yesterday', 'this_week', 'last_week',
            'this_month', 'last_month', 'last_7_days', 'last_30_days',
            'last_90_days', 'this_year', 'last_year', 'custom',
        ];
    }

    /**
     * Map a preset key to a [from, to] range, or null for custom/unknown.
     * Resolved against the given timezone (defaults to the app timezone) so a
     * scheduled report can compute "today" in the store's clock, not UTC.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public static function resolve(string $preset, ?string $timezone = null): ?array
    {
        $now = $timezone
            ? CarbonImmutable::now($timezone)
            : CarbonImmutable::now();

        return match ($preset) {
            'today'        => [$now->startOfDay(), $now->endOfDay()],
            'yesterday'    => [$now->subDay()->startOfDay(), $now->subDay()->endOfDay()],
            'this_week'    => [$now->startOfWeek(), $now->endOfDay()],
            'last_week'    => [$now->subWeek()->startOfWeek(), $now->subWeek()->endOfWeek()],
            'this_month'   => [$now->startOfMonth(), $now->endOfDay()],
            'last_month'   => [$now->subMonth()->startOfMonth(), $now->subMonth()->endOfMonth()],
            'last_7_days'  => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            'last_30_days' => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
            'last_90_days' => [$now->subDays(89)->startOfDay(), $now->endOfDay()],
            'this_year'    => [$now->startOfYear(), $now->endOfDay()],
            'last_year'    => [$now->subYear()->startOfYear(), $now->subYear()->endOfYear()],
            default        => null,
        };
    }
}
