<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by AttachCustomerToSale when a sale's claim link is submitted
 * twice (a double-submit race, or a reused/stale link) — the sale already
 * has a customer attached, so there's nothing left to claim.
 */
class SaleAlreadyClaimed extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('claim.errors.already_claimed'));
    }
}
