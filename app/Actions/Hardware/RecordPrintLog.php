<?php

namespace App\Actions\Hardware;

use App\Models\PrintLog;

/**
 * Persist a print-attempt audit row (docs/features/hardware.md §9.4).
 * Server-trusted context (store / terminal / user) comes from the
 * session, not the client. Fires the documented hooks so plugins can
 * react to print outcomes.
 *
 * Hooks:
 *   - action `print.after_send` → ($printLog)            on success
 *   - action `print.failed`     → ($printLog)            on failure
 *   - action `print.queued`     → ($printLog)            when queued
 */
class RecordPrintLog
{
    /** @param array<string, mixed> $data Validated payload from RecordPrintLogRequest. */
    public function __invoke(array $data): PrintLog
    {
        $log = PrintLog::create([
            'store_id'       => current_store_id(),
            'terminal_id'    => current_terminal_id(),
            'user_id'        => auth()->id(),
            'reference_type' => $data['reference_type'],
            'reference_id'   => $data['reference_id'] ?? null,
            'printer_type'   => $data['printer_type'],
            'mode'           => $data['mode'],
            'status'         => $data['status'],
            'error_message'  => $data['error_message'] ?? null,
            'bytes_size'     => $data['bytes_size'] ?? null,
            'created_at'     => now(),
        ]);

        match ($log->status) {
            'failed' => do_action('print.failed', $log),
            'queued' => do_action('print.queued', $log),
            default  => do_action('print.after_send', $log),
        };

        return $log;
    }
}
