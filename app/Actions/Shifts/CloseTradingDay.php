<?php

namespace App\Actions\Shifts;

use App\Exceptions\TradingDayNotClosable;
use App\Models\Shift;
use App\Models\TradingDay;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The explicit "Close Day" action — distinct from closing an individual
 * employee's {@see Shift}. Only possible once every shift under the
 * trading day has already been closed (the existing unique
 * `active_terminal_id` index already guarantees at most one OPEN shift
 * per terminal at any moment, so this just checks none are left).
 *
 * Doesn't touch any shift's own cash-drawer numbers — those were
 * already finalised individually when each employee closed out. This
 * only stamps the day itself closed so {@see \App\Actions\Hardware\PrepareDayReportPayload}
 * has a frozen point to report against.
 *
 * Hooks: action `trading_day.before_close` → ($day, $user) ·
 *        action `trading_day.after_close`  → ($day)
 */
class CloseTradingDay
{
    public function __invoke(TradingDay $day, User $user): TradingDay
    {
        if (! $day->isOpen()) {
            throw new TradingDayNotClosable($day, 'already_closed');
        }

        $stillOpen = Shift::query()
            ->where('trading_day_id', $day->id)
            ->where('status', Shift::STATUS_OPEN)
            ->exists();

        if ($stillOpen) {
            throw new TradingDayNotClosable($day, 'shift_still_open');
        }

        do_action('trading_day.before_close', $day, $user);

        return DB::transaction(function () use ($day, $user) {
            $day = TradingDay::query()->lockForUpdate()->findOrFail($day->id);

            if (! $day->isOpen()) {
                throw new TradingDayNotClosable($day, 'already_closed');
            }

            $day->forceFill([
                'status'    => TradingDay::STATUS_CLOSED,
                'closed_at' => now(),
                'closed_by' => $user->id,
            ])->save();

            $fresh = $day->fresh();

            do_action('trading_day.after_close', $fresh);

            return $fresh;
        });
    }
}
