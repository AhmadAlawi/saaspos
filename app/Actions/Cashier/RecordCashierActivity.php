<?php

namespace App\Actions\Cashier;

use App\Models\CashierActivityLog;
use App\Models\Shift;
use Illuminate\Http\Request;

/**
 * Persist one cashier-activity-log row (docs/features/hardware.md §9.5).
 * Server-trusted context (store / terminal / user / shift / IP) comes
 * from the session and request, never from the client payload — the
 * client only reports the event type/action/meta.
 */
class RecordCashierActivity
{
    /** @param array<string, mixed> $data Validated payload from RecordCashierActivityRequest. */
    public function __invoke(array $data, Request $request): CashierActivityLog
    {
        $storeId = current_store_id();
        $userId  = auth()->id();
        $shiftId = ($storeId && $userId)
            ? Shift::openForCashier((int) $storeId, (int) $userId)?->id
            : null;

        return CashierActivityLog::create([
            'store_id'       => $storeId,
            'terminal_id'    => current_terminal_id(),
            'user_id'        => $userId,
            'shift_id'       => $shiftId,
            'type'           => $data['type'],
            'action'         => $data['action'],
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id'   => $data['reference_id'] ?? null,
            'meta'           => $data['meta'] ?? null,
            'ip_address'     => $request->ip(),
            'created_at'     => now(),
        ]);
    }
}
