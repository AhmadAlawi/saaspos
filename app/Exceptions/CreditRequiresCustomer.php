<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when payments total less than the grand total but no customer
 * is attached — credit sales (partial pay) demand a customer because
 * the balance is tracked against that customer's outstanding ledger.
 * Walk-in sales must be fully paid.
 */
class CreditRequiresCustomer extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('sales.errors.credit_requires_customer'));
    }
}
