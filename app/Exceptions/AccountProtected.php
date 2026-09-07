<?php

namespace App\Exceptions;

/**
 * Thrown when a chart-of-accounts edit is refused by a guard — changing the
 * code of an account with posted entries, deleting a system / referenced
 * account, or deactivating one that backs a business mapping. The message is
 * already translated; the controller surfaces it as a 422 / flash.
 */
class AccountProtected extends \RuntimeException
{
}
