<?php

namespace App\Actions\Shifts;

use App\Exceptions\ShiftNotOpen;
use App\Exceptions\VarianceReasonRequired;
use App\Models\Shift;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Close an open shift — compute expected cash, compare to counted, snapshot
 * totals + per-method payment rollup, decide `closed` vs `closed_with_variance`.
 *
 * Variance handling:
 *   - The tolerance is read from store settings (`cash_variance_tolerance`,
 *     default 50 of base). Pulled via the Settings repo so a per-store
 *     override is honoured without code changes.
 *   - If |variance| > tolerance, a `variance_reason` is required from the
 *     caller and `status` is set to `closed_with_variance`.
 *   - If the user lacks `shifts.acknowledge_variance` AND variance is past
 *     tolerance, the controller blocks earlier; this action assumes the
 *     reason has been supplied.
 *
 * Hooks:
 *   - action `shift.before_close`           → ($shift, $data, $user)
 *   - action `shift.after_close`            → ($shift)
 *   - action `shift.closed_with_variance`   → ($shift, $variance)
 *   - filter `shift.variance.tolerance`     → ($tolerance, $shift)
 */
class CloseShift
{
    public function __construct(
        private readonly ComputeShiftTotals $compute,
    ) {}

    /**
     * @param array<string, mixed> $data {
     *   closing_cash_counted: numeric-string|float,
     *   variance_reason?: ?string,
     *   variance_notes?: ?string,
     *   closing_denominations?: array<string, int>|null,
     *   notes?: ?string,
     * }
     */
    public function __invoke(Shift $shift, array $data, User $user): Shift
    {
        if (! $shift->isOpen()) {
            throw new ShiftNotOpen($shift);
        }

        do_action('shift.before_close', $shift, $data, $user);

        return DB::transaction(function () use ($shift, $data, $user) {
            // Re-fetch the row locked so we don't double-close.
            $shift = Shift::query()->lockForUpdate()->findOrFail($shift->id);
            if (! $shift->isOpen()) {
                throw new ShiftNotOpen($shift);
            }

            $totals = ($this->compute)($shift);

            $counted = $this->fmt((string) ($data['closing_cash_counted'] ?? '0'));
            $expected = $totals['expected_cash'];
            $variance = bcsub($counted, $expected, 4);

            // Tolerance: per-store column on `stores.cash_variance_tolerance`.
            // Filter exposed so plugins can shape per-cashier dynamically.
            $store = Store::query()->find($shift->store_id);
            $tolerance = $this->fmt((string) ($store?->cash_variance_tolerance ?? '0'));
            $tolerance = (string) apply_filters('shift.variance.tolerance', $tolerance, $shift);

            $absVariance = ltrim($variance, '-');
            $outsideTolerance = bccomp($absVariance, $tolerance, 4) > 0;

            if ($outsideTolerance && empty($data['variance_reason'])) {
                throw new VarianceReasonRequired($variance, $tolerance);
            }

            $status = $outsideTolerance
                ? Shift::STATUS_CLOSED_WITH_VARIANCE
                : Shift::STATUS_CLOSED;

            $extraNotes = $data['notes'] ?? null;
            $mergedNotes = $shift->notes
                ? ($extraNotes ? $shift->notes."\n\n".$extraNotes : $shift->notes)
                : $extraNotes;

            // Force-close: a manager closing someone else's shift. We stamp
            // `force_closed_by` so the audit trail shows who closed it while
            // the shift's `user_id` (the original cashier) stays intact.
            $isForceClose = (int) $shift->user_id !== (int) $user->id;

            $shift->forceFill([
                'closed_at'             => now(),
                'closing_cash_counted'  => $counted,
                'closing_denominations' => $data['closing_denominations'] ?? null,
                'expected_cash'         => $expected,
                'cash_variance'         => $variance,
                'variance_reason'       => $outsideTolerance ? ($data['variance_reason'] ?? null) : null,
                'variance_notes'        => $outsideTolerance ? ($data['variance_notes']  ?? null) : null,
                'sales_count'           => $totals['sales_count'],
                'sales_total'           => $totals['sales_total'],
                'refunds_count'         => $totals['refunds_count'],
                'refunds_total'         => $totals['refunds_total'],
                'payment_totals'        => $totals['payment_totals'],
                'force_closed_by'       => $isForceClose ? $user->id : null,
                'status'                => $status,
                'notes'                 => $mergedNotes,
                // Release the one-open-shift-per-terminal lock so the terminal
                // can open a fresh shift. `terminal_id` stays for history.
                'active_terminal_id'    => null,
            ])->save();

            $fresh = $shift->fresh();

            if ($isForceClose) {
                do_action('shift.force_closed', $fresh, $user);
            }
            if ($status === Shift::STATUS_CLOSED_WITH_VARIANCE) {
                do_action('shift.closed_with_variance', $fresh, $variance);
            }
            do_action('shift.after_close', $fresh);

            return $fresh;
        });
    }

    private function fmt(string $v): string
    {
        if ($v === '' || $v === '-' || $v === '.') return '0.0000';
        $sign = bccomp($v, '0', 8) < 0 ? '-' : '';
        $abs  = ltrim($v, '-');
        return $sign.bcadd($abs, '0', 4);
    }
}
