<?php

namespace App\Actions\Shifts;

use App\Exceptions\ShiftAlreadyOpen;
use App\Exceptions\TerminalShiftOpen;
use App\Exceptions\TradingDayNotOpen;
use App\Models\Shift;
use App\Models\Store;
use App\Models\TradingDay;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Open a shift for the given cashier on the given store.
 *
 * Slice 1 scopes uniqueness by (store, user) — one open shift per
 * cashier per store. Terminal binding lands later when we surface a
 * terminal picker on the cashier screen.
 *
 * Hooks:
 *   - action `shift.before_open` → ($data, $user, $store)
 *   - action `shift.after_open`  → ($shift)
 */
class OpenShift
{
    /**
     * @param array<string, mixed> $data {
     *   opening_cash: numeric-string|float,
     *   notes?: ?string,
     *   terminal_id?: ?int,
     *   opening_denominations?: array<string, int>|null,
     * }
     */
    public function __invoke(array $data, User $user, Store $store): Shift
    {
        do_action('shift.before_open', $data, $user, $store);

        $terminalId = isset($data['terminal_id']) && $data['terminal_id'] !== ''
            ? (int) $data['terminal_id']
            : null;

        return DB::transaction(function () use ($data, $user, $store, $terminalId) {
            // One open shift per (store, user) at a time. Lock the
            // candidate row to keep two clicks from racing each other.
            $existing = Shift::query()
                ->where('store_id', $store->id)
                ->where('user_id', $user->id)
                ->where('status', Shift::STATUS_OPEN)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw new ShiftAlreadyOpen($existing);
            }

            // One open shift per terminal (Slice B). A second cashier
            // can't run a parallel drawer on the same physical till —
            // they take over via handover (close + reopen).
            if ($terminalId) {
                $terminalShift = Shift::query()
                    ->where('terminal_id', $terminalId)
                    ->where('status', Shift::STATUS_OPEN)
                    ->lockForUpdate()
                    ->first();

                if ($terminalShift) {
                    throw new TerminalShiftOpen($terminalShift);
                }
            }

            // Find-or-create today's trading day for this (store, terminal)
            // — the day wrapper that lets "Close Day" produce one combined
            // report across every staff handover, without touching this
            // shift's own cash-drawer accounting at all.
            $tradingDay = TradingDay::query()
                ->where('store_id', $store->id)
                ->where('terminal_id', $terminalId)
                ->where('business_date', now()->toDateString())
                ->where('status', TradingDay::STATUS_OPEN)
                ->lockForUpdate()
                ->first();

            if (! $tradingDay) {
                // `require_day_open` flips this from find-or-create to a
                // hard precondition — a manager must run OpenTradingDay
                // first (see Cashier\ShiftController::openDay()) before
                // anyone can open a shift at all.
                if ($store->require_day_open) {
                    throw new TradingDayNotOpen((int) $store->id, $terminalId);
                }

                $tradingDay = TradingDay::create([
                    'store_id'      => (int) $store->id,
                    'terminal_id'   => $terminalId,
                    'business_date' => now()->toDateString(),
                    'status'        => TradingDay::STATUS_OPEN,
                    'opened_at'     => now(),
                    'opened_by'     => (int) $user->id,
                ]);
            }

            $shift = new Shift();
            $shift->fill([
                'store_id'              => (int) $store->id,
                'terminal_id'           => $terminalId,
                'trading_day_id'        => $tradingDay->id,
                'user_id'               => (int) $user->id,
                'opened_at'             => now(),
                'opening_cash'          => $this->fmt((string) ($data['opening_cash'] ?? '0')),
                'opening_denominations' => $data['opening_denominations'] ?? null,
                'notes'                 => $data['notes'] ?? null,
            ]);
            $shift->forceFill([
                'status' => Shift::STATUS_OPEN,
                // Mirrors terminal_id WHILE open (NULLed on close). The UNIQUE
                // index on it is the race-proof backstop for one-open-per-terminal
                // the app-level check above can't guarantee (can't lock a
                // not-yet-existing row).
                'active_terminal_id' => $terminalId,
            ]);

            try {
                $shift->save();
            } catch (QueryException $e) {
                // The unique index fired: another request opened this terminal's
                // shift between our check and our insert. Surface the same
                // friendly error the app-level check would have.
                if ($terminalId && $this->isDuplicateKey($e)) {
                    $winner = Shift::query()
                        ->where('active_terminal_id', $terminalId)
                        ->where('status', Shift::STATUS_OPEN)
                        ->first();
                    throw new TerminalShiftOpen($winner ?? $shift);
                }
                throw $e;
            }

            do_action('shift.after_open', $shift);

            return $shift->fresh();
        });
    }

    private function fmt(string $v): string
    {
        if ($v === '' || $v === '-' || $v === '.') return '0.0000';
        return bcadd($v, '0', 4);
    }

    /** True when the query error is a unique/integrity violation (SQLSTATE 23xxx
     *  — MySQL 1062, SQLite 19). */
    private function isDuplicateKey(QueryException $e): bool
    {
        return ($e->getCode() === '23000')
            || str_starts_with((string) $e->getCode(), '23')
            || str_contains(strtolower($e->getMessage()), 'unique');
    }
}
