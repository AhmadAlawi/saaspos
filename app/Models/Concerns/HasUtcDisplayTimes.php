<?php

namespace App\Models\Concerns;

use Carbon\CarbonImmutable;

/**
 * The scheduler columns (next_run_at, last_run_at, run_at) are stored as a
 * fixed UTC instant so the cron-side "is it due yet?" comparison is
 * unambiguous regardless of the ambient application timezone (which the
 * ApplyCompanySettings middleware swaps per request). This helper converts one
 * of those UTC values into the current display timezone for the admin screens.
 */
trait HasUtcDisplayTimes
{
    public function displayTime(string $attribute): ?CarbonImmutable
    {
        $value = $this->{$attribute};
        if (! $value) {
            return null;
        }

        // The cast reads the stored wall-clock using the active app timezone;
        // re-label its digits as UTC (their true frame) then convert to the
        // company's display timezone.
        return CarbonImmutable::parse($value->format('Y-m-d H:i:s'), 'UTC')
            ->setTimezone((string) config('app.timezone', 'UTC'));
    }
}
