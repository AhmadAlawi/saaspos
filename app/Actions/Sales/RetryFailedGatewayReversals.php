<?php

namespace App\Actions\Sales;

use App\Models\SaleReturn;

/**
 * Re-attempts refunds whose gateway reversal failed (a gateway outage, a
 * transient error). Run on a schedule (routes/console.php). Attempts are
 * capped so a permanently-failing reversal (e.g. the charge is too old to
 * refund) stops retrying and stays visible for a manual "Retry reversal".
 */
class RetryFailedGatewayReversals
{
    private const MAX_ATTEMPTS = 6;

    public function __construct(private readonly ReverseGatewayCharge $reverse) {}

    public function __invoke(): int
    {
        $due = SaleReturn::query()
            ->where('gateway_refund_status', SaleReturn::GATEWAY_REFUND_FAILED)
            ->where('gateway_refund_attempts', '<', self::MAX_ATTEMPTS)
            ->orderBy('id')
            ->limit(50)
            ->get();

        foreach ($due as $return) {
            ($this->reverse)($return);
        }

        return $due->count();
    }
}
