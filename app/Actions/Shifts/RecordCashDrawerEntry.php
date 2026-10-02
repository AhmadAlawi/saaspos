<?php

namespace App\Actions\Shifts;

use App\Exceptions\ShiftNotOpen;
use App\Models\CashDrawerEntry;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Record a cash-drawer movement against an open shift — pay-in,
 * pay-out, or drawer-open-no-sale. The Z/X-report math (see
 * {@see ComputeShiftTotals}) already aggregates `cash_drawer_entries`
 * for the shift, so once this writes the row the expected-cash figure
 * shifts on the next render.
 *
 * Type semantics:
 *   - `pay_in`               → amount > 0, expected cash GOES UP
 *   - `pay_out`              → amount > 0, expected cash GOES DOWN
 *   - `drawer_open_no_sale`  → amount ignored (stored as 0), audit-only;
 *                              counts on the Z-report under "Drawer
 *                              opens (no sale)" but doesn't move money.
 *
 * `drawer_open_no_sale` is the ONE type that may be recorded with
 * `$shift = null` — a manager popping the drawer before any cashier has
 * clocked in on this terminal. A shift-less entry (`shift_id` NULL)
 * never shows up in any Z-report (ComputeShiftTotals always filters by
 * a real shift_id); it's audit-only, visible in the cashier activity
 * log. `pay_in`/`pay_out` always require a real open shift — those move
 * money and MUST reconcile against a specific shift's expected cash.
 *
 * Hooks:
 *   - action `cash_drawer.pay_in_recorded`     → ($entry, $shift)
 *   - action `cash_drawer.pay_out_recorded`    → ($entry, $shift)
 *   - action `cash_drawer.opened_no_sale`      → ($entry, $shift)
 */
class RecordCashDrawerEntry
{
    /**
     * @param array<string, mixed> $data {
     *   type: 'pay_in'|'pay_out'|'drawer_open_no_sale',
     *   amount?: numeric-string|float|null,  // required for pay_in / pay_out
     *   reason: string,
     *   expense_id?: ?int,                   // pay_out optional link
     * }
     */
    public function __invoke(?Shift $shift, array $data, User $user): CashDrawerEntry
    {
        $type = (string) ($data['type'] ?? '');

        if (! $shift) {
            if ($type !== CashDrawerEntry::TYPE_DRAWER_OPEN_NO_SALE) {
                // pay_in/pay_out move real money — always needs a shift
                // to reconcile against. Only a bare drawer-open may be
                // shift-less.
                throw new InvalidArgumentException('A shift is required for '.$type.'.');
            }
        } elseif (! $shift->isOpen()) {
            throw new ShiftNotOpen($shift);
        }
        if (! in_array($type, [
            CashDrawerEntry::TYPE_PAY_IN,
            CashDrawerEntry::TYPE_PAY_OUT,
            CashDrawerEntry::TYPE_DRAWER_OPEN_NO_SALE,
        ], true)) {
            throw new InvalidArgumentException("Unknown cash-drawer entry type: {$type}");
        }

        $amount = $type === CashDrawerEntry::TYPE_DRAWER_OPEN_NO_SALE
            ? '0.0000'
            : $this->fmt((string) ($data['amount'] ?? '0'));

        if ($type !== CashDrawerEntry::TYPE_DRAWER_OPEN_NO_SALE
            && bccomp($amount, '0', 4) <= 0
        ) {
            throw new InvalidArgumentException(__('cash_drawer.errors.amount_must_be_positive'));
        }

        return DB::transaction(function () use ($shift, $type, $amount, $data, $user) {
            $entry = CashDrawerEntry::create([
                'shift_id'              => $shift?->id,
                'type'                  => $type,
                'amount'                => $amount,
                'reason'                => (string) ($data['reason'] ?? ''),
                'expense_id'            => $data['expense_id'] ?? null,
                'supplier_payment_uuid' => $data['supplier_payment_uuid'] ?? null,
                'created_by'            => (int) $user->id,
            ]);

            $hook = match ($type) {
                CashDrawerEntry::TYPE_PAY_IN              => 'cash_drawer.pay_in_recorded',
                CashDrawerEntry::TYPE_PAY_OUT             => 'cash_drawer.pay_out_recorded',
                CashDrawerEntry::TYPE_DRAWER_OPEN_NO_SALE => 'cash_drawer.opened_no_sale',
            };
            do_action($hook, $entry, $shift);

            return $entry->fresh();
        });
    }

    private function fmt(string $v): string
    {
        if ($v === '' || $v === '-' || $v === '.') return '0.0000';
        return bcadd($v, '0', 4);
    }
}
