<?php

namespace App\Exceptions;

/** A journal line that isn't exactly one of debit-only or credit-only. */
class InvalidJournalLineException extends \RuntimeException
{
}
