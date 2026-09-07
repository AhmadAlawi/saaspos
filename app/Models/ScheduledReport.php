<?php

namespace App\Models;

use App\Models\Concerns\HasUtcDisplayTimes;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A recurring report delivery. Holds the report + its stored parameters, a
 * cadence (daily / weekly / monthly), and the recipients it emails on each
 * tick. The runner ({@see \App\Console\Commands\RunDueReports}) picks up rows
 * whose `next_run_at` has passed and hands them to
 * {@see \App\Actions\Reports\RunScheduledReport}.
 *
 * WhatsApp delivery is deferred; `recipients_whatsapp` is stored but unused.
 */
class ScheduledReport extends Model
{
    use HasUtcDisplayTimes;

    public const FREQ_DAILY   = 'daily';
    public const FREQ_WEEKLY  = 'weekly';
    public const FREQ_MONTHLY = 'monthly';

    public const FREQUENCIES = [self::FREQ_DAILY, self::FREQ_WEEKLY, self::FREQ_MONTHLY];

    protected $fillable = [
        'saved_report_id', 'user_id', 'store_id', 'report_key', 'name', 'parameters',
        'frequency', 'day_of_week', 'day_of_month', 'time_of_day', 'timezone', 'format',
        'recipients_email', 'recipients_whatsapp', 'subject_template', 'message_template',
        'is_active', 'last_run_at', 'next_run_at',
    ];

    protected $casts = [
        'parameters'          => 'array',
        'recipients_email'    => 'array',
        'recipients_whatsapp' => 'array',
        'day_of_week'         => 'integer',
        'day_of_month'        => 'integer',
        'is_active'           => 'boolean',
        'last_run_at'         => 'datetime',
        'next_run_at'         => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function savedReport(): BelongsTo
    {
        return $this->belongsTo(SavedReport::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ScheduledReportRun::class);
    }

    public function latestRun(): HasOne
    {
        return $this->hasOne(ScheduledReportRun::class)->latestOfMany('run_at');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Own schedules, plus every schedule for a super admin. */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->is_super_admin) {
            $query->where('user_id', $user->id);
        }
    }

    /**
     * The next time this schedule should fire, as a UTC timestamp, strictly
     * after `$after` (default: now). Computed in the schedule's own timezone
     * so "08:00 weekly on Monday" means 08:00 local, then stored UTC per the
     * project's UTC-storage rule.
     */
    public function computeNextRun(?CarbonImmutable $after = null): CarbonImmutable
    {
        $tz    = $this->timezone ?: (string) config('app.timezone', 'UTC');
        $after = ($after ?? CarbonImmutable::now())->setTimezone($tz);

        [$hour, $minute] = $this->timeParts();

        $candidate = $after->setTime($hour, $minute, 0);

        // Walk forward a day at a time until the candidate satisfies the
        // frequency constraint AND is strictly in the future. Simple and
        // exact — it sidesteps month-length edge cases (e.g. "day 31" in
        // February falls back to the month's last day naturally).
        for ($i = 0; $i <= 800; $i++) {
            if ($candidate->greaterThan($after) && $this->matchesFrequency($candidate)) {
                break;
            }
            $candidate = $candidate->addDay()->setTime($hour, $minute, 0);
        }

        return $candidate->utc();
    }

    private function matchesFrequency(CarbonImmutable $candidate): bool
    {
        return match ($this->frequency) {
            self::FREQ_WEEKLY  => $candidate->dayOfWeekIso === ($this->day_of_week ?: 1),
            self::FREQ_MONTHLY => $candidate->day === min($this->day_of_month ?: 1, $candidate->daysInMonth),
            default            => true, // daily
        };
    }

    /** @return array{0: int, 1: int} [hour, minute] parsed from time_of_day */
    private function timeParts(): array
    {
        $parts  = explode(':', (string) ($this->time_of_day ?: '08:00'));
        $hour   = (int) ($parts[0] ?? 8);
        $minute = (int) ($parts[1] ?? 0);

        return [max(0, min(23, $hour)), max(0, min(59, $minute))];
    }
}
