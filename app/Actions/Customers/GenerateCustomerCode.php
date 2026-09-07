<?php

namespace App\Actions\Customers;

use App\Models\Customer;

/**
 * Builds the next customer code on create. Format: `C-NNNNNN` (six-digit
 * zero-padded sequence, e.g. `C-000123`). The sequence is global, not
 * per-year, so codes are stable across fiscal boundaries — customers
 * are long-lived records and reusing a code for a new person would be
 * confusing.
 *
 * Race-safety: the `customers.code` column is UNIQUE, so if two creates
 * race to the same number, the second INSERT fails and the caller can
 * retry. Good enough for v1.0 — install-level traffic.
 */
class GenerateCustomerCode
{
    public function __invoke(): string
    {
        $latest = Customer::query()
            ->whereNotNull('code')
            ->where('code', 'like', 'C-%')
            ->orderByDesc('id')
            ->value('code');

        $seq = 1;
        if ($latest && preg_match('/^C-(\d+)$/', $latest, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return sprintf('C-%06d', $seq);
    }
}
