<?php

namespace App\Services\Sales;

use Illuminate\Support\Facades\Crypt;

/**
 * Stateless, tamper-proof manager-approval token for a refund made by a
 * cashier who doesn't hold `sales.refund` — mirrors
 * {@see DiscountApprovalToken} exactly, just capped by a maximum refund
 * AMOUNT instead of a maximum discount percent (a refund has no natural
 * "percent" to bound; the manager approves the specific total they saw
 * on screen, and the token can't be stretched to cover a larger one).
 *
 * Issued by the PIN-based approval endpoint after verifying the manager's
 * PIN + `sales.refund` permission; re-verified server-side wherever the
 * actual refund gets recorded ({@see \App\Http\Controllers\Cashier\RefundController},
 * {@see \App\Actions\Sales\RecordBlindReturn}).
 */
class RefundApprovalToken
{
    private const TTL_SECONDS = 900; // 15 minutes: approve → submit the refund — 5 was tripping on real, non-idle refunds (same fix as DiscountApprovalToken).

    /** Issue a token authorising up to `$maxAmount` on `$storeId`. */
    public function issue(int $approverId, int $storeId, string $maxAmount): string
    {
        return Crypt::encryptString(json_encode([
            'aid' => $approverId,
            'sid' => $storeId,
            'max' => $maxAmount,
            'exp' => now()->addSeconds(self::TTL_SECONDS)->getTimestamp(),
        ]));
    }

    /**
     * Approver id when the token is valid AND authorises a refund total of
     * `$refundTotal` for `$storeId`; null otherwise (missing/forged/
     * expired/wrong-store/exceeds-approved-amount).
     */
    public function verify(?string $token, int $storeId, string $refundTotal): ?int
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
        // The submitted refund must be within the approved ceiling. A
        // small absolute hair absorbs sub-cent rounding drift between
        // the client's displayed total at approval time and the
        // server's own recomputation at submit time (same reasoning as
        // DiscountApprovalToken's 0.05% hair, just an absolute money
        // amount here since a refund has no natural percent to scale
        // the tolerance by).
        $approvedCeiling = bcadd((string) ($data['max'] ?? '0'), '0.01', 4);
        if (bccomp($refundTotal, $approvedCeiling, 4) > 0) {
            return null;
        }

        $aid = (int) ($data['aid'] ?? 0);

        return $aid > 0 ? $aid : null;
    }
}
