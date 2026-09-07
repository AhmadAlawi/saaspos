<?php

namespace App\Exceptions;

/**
 * Thrown when a chart-of-accounts group edit is refused by a guard —
 * moving/deleting a system group, deleting a group that still holds accounts
 * or sub-groups, or a reparent that would cross a reporting type or form a
 * cycle. The message is already translated; the controller surfaces it as a
 * 422 / flash.
 */
class AccountGroupProtected extends \RuntimeException
{
}
