<?php

namespace App\Services\Sales;

use Illuminate\Support\Facades\Crypt;

/**
 * Stateless, tamper-proof manager-approval token for over-threshold
 * discounts (Checkout discounts, Slice 2).
 *
 * The cashier can't be trusted to just send an "approved" flag, so the
 * approval endpoint (which verifies the manager's password + permission)
 * issues a short-lived encrypted token bound to (approver, store, max
 * percent, expiry). {@see \App\Actions\Sales\CompleteSale} decrypts and
 * re-checks it at ring-up. Encryption (Laravel Crypt = AES + MAC) means a
 * forged/edited token fails to decrypt.
 */
class DiscountApprovalToken
{
    private const TTL_SECONDS = 900; // 15 minutes: approve → ring up — 5 was tripping on real, non-idle checkouts (drawer opens, split tenders, customer questions).

    /** Issue a token authorising up to `$maxPercent` on `$storeId`. */
    public function issue(int $approverId, int $storeId, float $maxPercent): string
    {
        return Crypt::encryptString(json_encode([
            'aid' => $approverId,
            'sid' => $storeId,
            'max' => $maxPercent,
            'exp' => now()->addSeconds(self::TTL_SECONDS)->getTimestamp(),
        ]));
    }

    /**
     * Approver id when the token is valid AND authorises `$effectivePercent`
     * for `$storeId`; null otherwise (missing/forged/expired/wrong-store/
     * exceeds-approved-percent).
     */
    public function verify(?string $token, int $storeId, string $effectivePercent): ?int
    {
        if (! $token) {
            return null;
        }

        try {
            $data = json_decode(Crypt::decryptString($token), true);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($data)) {
            return null;
        }
        if ((int) ($data['sid'] ?? 0) !== $storeId) {
            return null;
        }
        if ((int) ($data['exp'] ?? 0) < now()->getTimestamp()) {
            return null;
        }
        // The rung-up discount must be within the approved ceiling. The
        // approved `max` is whatever percent the CASHIER'S BROWSER
        // computed in JS float math at approval time; the ring-up side
        // recomputes `$effectivePercent` from scratch via bcmath off the
        // server's own per-line-rounded PriceCart totals. On a single
        // round-number discount these agree exactly, but a cart with
        // several differently-discounted lines accumulates enough
        // float-vs-decimal rounding drift (confirmed: a legitimately
        // manager-approved multi-item discount came back a few
        // thousandths of a percent higher server-side) to fail a
        // zero-tolerance compare — rejecting an approval the manager
        // already granted. Same 0.05% hair {@see AuthorizeDiscount}
        // already uses for its own rounding-drift absorption.
        $approvedCeiling = bcadd((string) ($data['max'] ?? '0'), '0.05', 4);
        if (bccomp($effectivePercent, $approvedCeiling, 4) > 0) {
            return null;
        }

        $aid = (int) ($data['aid'] ?? 0);

        return $aid > 0 ? $aid : null;
    }
}
