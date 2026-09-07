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
    private const TTL_SECONDS = 300; // 5 minutes: approve → ring up.

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
        // The rung-up discount must be within the approved ceiling.
        if (bccomp($effectivePercent, (string) ($data['max'] ?? '0'), 4) > 0) {
            return null;
        }

        $aid = (int) ($data['aid'] ?? 0);

        return $aid > 0 ? $aid : null;
    }
}
