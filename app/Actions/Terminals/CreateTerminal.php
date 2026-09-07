<?php

namespace App\Actions\Terminals;

use App\Models\Terminal;

/**
 * Persist a new checkout terminal.
 *
 * Extension points:
 *   - filter `terminal.attributes`    → modify the attribute array
 *   - action `terminal.before_create` → side-effects before insert
 *   - action `terminal.after_create`  → side-effects after insert
 */
class CreateTerminal
{
    /** @param array<string, mixed> $data Already-validated payload from TerminalRequest. */
    public function __invoke(array $data): Terminal
    {
        $data = apply_filters('terminal.attributes', $data);
        do_action('terminal.before_create', $data);

        $terminal = Terminal::create($data);

        do_action('terminal.after_create', $terminal);

        return $terminal;
    }
}
