<?php

namespace App\Actions\Sales;

use App\Models\CashierActivityLog;
use App\Models\Sale;
use App\Models\Shift;
use Illuminate\Support\Carbon;

/**
 * When did the transaction that produced this sale actually START, on
 * camera? Not the sale's `sale_datetime` (that's when it was RUNG UP —
 * items were already in the cart well before that) — the real start is
 * whenever the cashier scanned/tapped the FIRST item of this specific
 * ring-up. The window ends 60s after the sale's own `sale_datetime`
 * (RecordStockMovement / receipt printing takes a couple seconds; a
 * one-minute trailing buffer beats cutting off mid-tap or mid-handoff).
 *
 * Reconstructed from {@see CashierActivityLog}'s `cart.add`/`cart.remove`
 * events on the sale's terminal by walking BACKWARD from the sale's
 * completion: keep extending the window earlier through consecutive
 * events as long as the gap between them stays under 2 minutes: the
 * moment a gap of 2+ minutes shows up, that's the boundary — the
 * cashier wasn't actively building THIS cart before it, whatever
 * happened before belongs to something else (a previous customer, an
 * idle till, browsing without touching the cart).
 *
 * An earlier version tried to track the cart's line COUNT (add = +1,
 * remove = -1, "empty for 2 minutes" = reset point) instead of raw time
 * gaps. That broke in exactly the cases that matter most: a completed
 * checkout clears the cart WITHOUT logging a `cart.remove` per line
 * (that logging is tied to the cashier manually removing something,
 * not the automatic post-sale reset), and a held/resumed sale restores
 * lines silently too — either one means the tracked count can just
 * never legitimately return to zero, so the "reset point" search keeps
 * walking backward past real transaction boundaries, occasionally all
 * the way to a customer from HOURS earlier (confirmed against real
 * production data: a sale rung up at 20:24 got a "start" of 16:12 —
 * four hours prior). Raw time gaps between events don't care what the
 * running count theoretically is; they just answer "was the cashier
 * actively doing something on this terminal, continuously, up to this
 * sale" — which is what actually determines whether footage is worth
 * pulling.
 *
 * Bounded below by the more recent of the shift's open time and the
 * previous completed sale on the same terminal, purely so a slow
 * (multi-hour) query window is never built even before the gap-walk
 * gets a chance to stop it — the gap detection alone would already
 * stop at the same place in the common case, this is just a cheap
 * floor on how much history the query itself has to scan.
 *
 * Falls back to a fixed 3-minute-before window around `sale_datetime`
 * when there's no usable cart trail at all — sales from before this
 * activity logging shipped have no rows to walk.
 */
class ResolveSaleVideoWindow
{
    private const IDLE_RESET_SECONDS = 120;
    private const FALLBACK_BEFORE_MINUTES = 3;
    private const TAIL_SECONDS = 60;

    /** @return array{0: Carbon, 1: Carbon} [start, end] */
    public function handle(Sale $sale): array
    {
        $saleTime = Carbon::parse($sale->sale_datetime);
        $end = $saleTime->clone()->addSeconds(self::TAIL_SECONDS);

        $start = $this->resolveFromCartTrail($sale, $saleTime);

        return [$start ?? $saleTime->clone()->subMinutes(self::FALLBACK_BEFORE_MINUTES), $end];
    }

    private function resolveFromCartTrail(Sale $sale, Carbon $saleTime): ?Carbon
    {
        if (! $sale->terminal_id) {
            return null;
        }

        $shiftOpenedAt = $sale->shift_id
            ? Shift::query()->whereKey($sale->shift_id)->value('opened_at')
            : null;
        $lowerBound = $shiftOpenedAt ? Carbon::parse($shiftOpenedAt) : $saleTime->clone()->subHours(6);

        $previousSaleTime = Sale::query()
            ->where('terminal_id', $sale->terminal_id)
            ->where('id', '!=', $sale->id)
            ->where('sale_datetime', '<', $saleTime)
            ->where('sale_datetime', '>=', $lowerBound)
            ->orderByDesc('sale_datetime')
            ->value('sale_datetime');

        if ($previousSaleTime) {
            $lowerBound = Carbon::parse($previousSaleTime)->max($lowerBound);
        }

        $events = CashierActivityLog::query()
            ->where('terminal_id', $sale->terminal_id)
            ->where('type', 'cart')
            ->whereIn('action', ['cart.add', 'cart.remove'])
            ->whereBetween('created_at', [$lowerBound, $saleTime])
            ->orderByDesc('created_at')
            ->get(['created_at']);

        if ($events->isEmpty()) {
            return null;
        }

        $sessionStart = $saleTime;
        foreach ($events as $event) {
            if ($sessionStart->diffInSeconds($event->created_at) >= self::IDLE_RESET_SECONDS) {
                break;
            }
            $sessionStart = $event->created_at;
        }

        return $sessionStart->equalTo($saleTime) ? null : $sessionStart;
    }
}
