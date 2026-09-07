<?php

namespace App\Exceptions;

/** A journal entry whose debits don't equal its credits was rejected. */
class UnbalancedJournalException extends \RuntimeException
{
}
