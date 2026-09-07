<?php

namespace App\Actions\Sales;

use App\Models\Sale;

/**
 * The short human code a kiosk customer is called by at the counter ("K007").
 *
 * Shared by both kiosk outcomes, because both need an identity the customer
 * can show and staff can shout:
 *   - {@see PlaceKioskOrder}  → a `placed` order paid at the counter,
 *   - kiosk `checkout` mode   → a `completed`, already-paid sale whose goods
 *     still have to be handed over.
 *
 * Scoped per store per day, so codes stay short and restart each morning.
 * `sale_datetime` (not `placed_at`) is the day anchor: a completed kiosk sale
 * never gets a `placed_at`, and counting on it would restart the sequence at
 * K001 for every paid order.
 *
 * `$prefix` comes from the terminal (`kiosk_config.pickup_prefix`), so a store
 * running two kiosks can label them A / B. The sequence is drawn from EVERY
 * code issued in the store today regardless of prefix — staff read the number,
 * not the letter, and A001 sitting next to B001 on the counter is a mix-up
 * waiting to happen.
 *
 * The count+1 is re-checked against existing rows because two kiosks can mint
 * a code in the same instant; on a collision we walk forward rather than hand
 * two customers the same number.
 */
class GenerateKioskPickupCode
{
    /** Give up walking forward after this many collisions (never realistic). */
    private const MAX_PROBES = 200;

    public function __invoke(int $storeId, string $prefix = 'K'): string
    {
        $prefix = $this->sanitize($prefix);
        $today  = now()->toDateString();

        $used = Sale::query()
            ->where('store_id', $storeId)
            ->whereNotNull('pickup_code')
            ->whereDate('sale_datetime', $today)
            ->pluck('pickup_code')
            ->flip();

        $seq = $used->count() + 1;

        for ($probe = 0; $probe < self::MAX_PROBES; $probe++, $seq++) {
            $code = $prefix.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
            if (! $used->has($code)) {
                return $code;
            }
        }

        // Pathological — fall back to something unique rather than a duplicate.
        return $prefix.now()->format('His');
    }

    /** Letters + digits only, max 4, uppercase. Empty falls back to "K". */
    private function sanitize(string $prefix): string
    {
        $clean = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($prefix))) ?? '';

        return $clean !== '' ? substr($clean, 0, 4) : 'K';
    }
}
