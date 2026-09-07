<?php

namespace App\Actions\Terminals;

use App\Models\Terminal;

/**
 * Delete a terminal. Sales, shifts, and print logs reference terminals
 * with `nullOnDelete`, so removing one detaches it from past records
 * without destroying them — the history stays, it just loses the
 * terminal label.
 *
 * Extension points:
 *   - action `terminal.before_delete` → fires before delete
 *   - action `terminal.after_delete`  → fires after delete
 */
class DeleteTerminal
{
    public function __invoke(Terminal $terminal): void
    {
        do_action('terminal.before_delete', $terminal);

        $terminal->delete();

        do_action('terminal.after_delete', $terminal);
    }
}
