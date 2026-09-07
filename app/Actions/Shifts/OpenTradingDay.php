<?php

namespace App\Actions\Shifts;

use App\Models\Store;
use App\Models\Terminal;
use App\Models\TradingDay;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The explicit "Open Day" action — the counterpart to {@see CloseTradingDay}.
 * Store-wide by design (confirmed with the client): a manager opens the
 * whole shop once each morning rather than walking to every till, so
 * this opens one {@see TradingDay} row per active terminal at the store
 * for today (or a single terminal-less row for a store with none
 * configured), find-or-create per row. A day already open is a no-op,
 * not an error — this is safe to call from a "make sure today is open"
 * button without the caller having to check first.
 *
 * Doesn't touch shift enforcement or any employee's own {@see Shift} —
 * it only unblocks {@see OpenShift}'s day-open requirement when
 * `stores.require_day_open` is on.
 *
 * Hooks: action `trading_day.before_open` → ($store, $user) ·
 *        action `trading_day.after_open`  → ($days)
 */
class OpenTradingDay
{
    /** @return Collection<int, TradingDay> */
    public function __invoke(Store $store, User $user): Collection
    {
        do_action('trading_day.before_open', $store, $user);

        $terminalIds = Terminal::query()
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        // No terminals configured — one day wrapper for the store itself,
        // same fallback OpenShift already uses for terminal-less stores.
        if (empty($terminalIds)) {
            $terminalIds = [null];
        }

        $days = DB::transaction(function () use ($store, $user, $terminalIds) {
            $today = now()->toDateString();

            return collect($terminalIds)->map(function ($terminalId) use ($store, $user, $today) {
                $day = TradingDay::query()
                    ->where('store_id', $store->id)
                    ->where('terminal_id', $terminalId)
                    ->where('business_date', $today)
                    ->lockForUpdate()
                    ->first();

                if ($day && $day->isOpen()) {
                    return $day;
                }

                if ($day) {
                    $day->forceFill([
                        'status'    => TradingDay::STATUS_OPEN,
                        'opened_at' => now(),
                        'opened_by' => $user->id,
                        'closed_at' => null,
                        'closed_by' => null,
                    ])->save();

                    return $day->fresh();
                }

                return TradingDay::create([
                    'store_id'      => (int) $store->id,
                    'terminal_id'   => $terminalId,
                    'business_date' => $today,
                    'status'        => TradingDay::STATUS_OPEN,
                    'opened_at'     => now(),
                    'opened_by'     => (int) $user->id,
                ]);
            });
        });

        do_action('trading_day.after_open', $days);

        return $days;
    }
}
