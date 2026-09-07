<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by {@see App\Actions\Languages\DeleteLanguage}:
 *   - `base`    — English is the file reference and can't be removed.
 *   - `default` — the current default language can't be removed; set
 *     another default first.
 */
class LanguageNotDeletable extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("Cannot delete language: {$reason}");
    }
}
