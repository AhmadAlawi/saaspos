<?php

namespace App\Exceptions;

/** An attempt to post into a locked fiscal period was rejected. */
class PeriodLockedException extends \RuntimeException
{
}
