<?php

namespace App\Actions\Terminals;

use App\Models\Terminal;

/**
 * Update an existing terminal.
 *
 * Extension points:
 *   - filter `terminal.attributes`    → modify the attribute array (shared with create)
 *   - action `terminal.before_update` → receives ($terminal, $data)
 *   - action `terminal.after_update`  → receives ($terminal, $original)
 */
class UpdateTerminal
{
    /** @param array<string, mixed> $data Already-validated payload from TerminalRequest. */
    public function __invoke(Terminal $terminal, array $data): Terminal
    {
        $original = $terminal->getOriginal();

        $data = apply_filters('terminal.attributes', $data, $terminal);
        do_action('terminal.before_update', $terminal, $data);

        $terminal->update($data);

        do_action('terminal.after_update', $terminal, $original);

        return $terminal;
    }
}
