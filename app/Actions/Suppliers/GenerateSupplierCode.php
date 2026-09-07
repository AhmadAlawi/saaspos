<?php

namespace App\Actions\Suppliers;

use App\Models\Supplier;

/**
 * Builds the next supplier code on create. Format: `S-NNNNNN`
 * (six-digit zero-padded sequence, e.g. `S-000123`). Global sequence
 * so codes stay stable across fiscal years.
 *
 * Race-safety: the composite `(code, deleted_at)` unique on `suppliers`
 * forces the second racing INSERT to fail; the caller can retry.
 */
class GenerateSupplierCode
{
    public function __invoke(): string
    {
        $latest = Supplier::query()
            ->whereNotNull('code')
            ->where('code', 'like', 'S-%')
            ->orderByDesc('id')
            ->value('code');

        $seq = 1;
        if ($latest && preg_match('/^S-(\d+)$/', $latest, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return sprintf('S-%06d', $seq);
    }
}
