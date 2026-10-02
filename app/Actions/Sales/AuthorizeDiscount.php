<?php

namespace App\Actions\Sales;

use App\Exceptions\DiscountAboveThreshold;
use App\Exceptions\DiscountNotAllowed;
use App\Models\Customer;
use App\Models\Store;
use App\Models\User;
use App\Services\Sales\DiscountApprovalToken;
use App\Support\PricedCart;

/**
 * Discount governance (Checkout discounts, Slices 1 + 2) — the ONE place
 * that decides whether a priced cart's discount is actually authorized.
 *
 * Originally this lived inline in {@see CompleteSale} only. The QR/card
 * payment-session endpoint ({@see \App\Http\Controllers\Cashier\PaymentSessionController})
 * priced the SAME cart (via the same {@see PriceCart} action, so the
 * numbers matched) but never ran this check — so an unapproved or
 * above-threshold discount could still get charged to the customer's
 * card at session-create time, then get correctly rejected here at
 * ring-up, leaving the till with a charge that doesn't match any saved
 * sale. Both call sites now share this action so a discount can never be
 * charged without also being the one that gets recorded.
 *
 * @throws DiscountNotAllowed
 * @throws DiscountAboveThreshold
 */
class AuthorizeDiscount
{
    public function __construct(private DiscountApprovalToken $tokens) {}

    /**
     * @return int|null the PIN-identified approver's user id, or null when
     *                   the discount needed no governance (none applied,
     *                   or fully covered by the customer's pre-authorized
     *                   default discount).
     */
    public function handle(
        PricedCart $priced,
        Store $store,
        ?int $customerId,
        ?string $discountApprovalToken,
    ): ?int {
        if (bccomp($priced->discountTotal, '0', 4) <= 0) {
            return null;
        }

        // Effective discount % against the pre-discount gross.
        $gross = '0';
        foreach ($priced->lines as $pl) {
            $gross = bcadd(
                $gross,
                bcmul((string) ($pl['raw']['quantity'] ?? '0'), (string) ($pl['raw']['unit_price'] ?? '0'), 8),
                8,
            );
        }
        $effectivePct = bccomp($gross, '0', 4) > 0
            ? bcdiv(bcmul($priced->discountTotal, '100', 8), $gross, 4)
            : '0';

        // A customer's admin-configured default discount is pre-
        // authorized: a discount within it needs neither `sales.discount`
        // nor manager approval (the cashier just picked the customer).
        // Read from the actual customer row — NOT a client-sent value —
        // so it can't be spoofed to widen the exemption. The 0.05% hair
        // absorbs 4-dp rounding on the effective-percent computation.
        $customerDefaultPct = $customerId
            ? (string) (Customer::whereKey($customerId)->value('default_discount_percent') ?? '0')
            : '0';
        $exemptCeiling = bccomp($customerDefaultPct, '0', 4) > 0
            ? bcadd($customerDefaultPct, '0.05', 4)
            : '0';

        if (bccomp($effectivePct, $exemptCeiling, 4) <= 0) {
            return null;
        }

        // Only the portion beyond the pre-authorized default is
        // cashier-initiated → govern it. Every such discount needs a
        // PIN-verified approval token, regardless of whether the acting
        // cashier holds `sales.discount` — the PIN identifies WHO applied
        // it, not just whether the logged-in session is allowed to. Under
        // the store's threshold, any active user's PIN satisfies it;
        // above it, the resolved PIN owner must still hold
        // `sales.discount_above_threshold` (re-checked live here, not
        // trusted from the token, in case it was revoked between
        // approval and ring-up).
        $threshold = (string) ($store->discount_threshold_percent ?? '100');

        $approverId = $this->tokens->verify($discountApprovalToken, $store->id, $effectivePct);
        $approver   = $approverId ? User::find($approverId) : null;

        if (! $approver) {
            throw new DiscountNotAllowed();
        }
        if (bccomp($effectivePct, $threshold, 4) > 0
            && ! $approver->hasPermission('sales.discount_above_threshold', $store->id)) {
            throw new DiscountAboveThreshold($effectivePct, $threshold);
        }

        return $approverId;
    }
}
