<?php

namespace App\Actions\Sales;

use App\Models\Company;
use App\Models\Sale;
use App\Models\Store;
use App\Support\NumberFormat;

/**
 * Builds the next sale number for a given store.
 *
 * The format string comes from `company.sale_number_format` — admin can
 * change it at /admin/settings/numbering. Defaults to
 * `SALE-{store}-{Ym}-{seq:04}` → `SALE-MAIN-202606-0042`.
 *
 * Sequence lookup: `WHERE number LIKE prefix%` finds the latest matching
 * number in the current period; the trailing digit run is parsed as the
 * sequence value. Reset frequency is implicit in the format — formats
 * containing `{Ym}` reset monthly because the prefix changes; formats
 * with only `{Y}` reset yearly; formats with no date placeholder never
 * reset.
 *
 * Caller MUST run this inside the same DB transaction that inserts the
 * sale row — two concurrent cashiers reading the same MAX would collide
 * on `(store_id, number)` unique. The transaction makes the
 * sequence-then-insert atomic.
 *
 * Date arg lets tests pin a deterministic month.
 */
class GenerateSaleNumber
{
    public function __invoke(int $storeId, ?\DateTimeInterface $when = null): string
    {
        $store = Store::query()->findOrFail($storeId);
        $when  = $when ?? now();

        $format = (Company::current() ?? new Company())->numberFormat('sale');
        $prefix = NumberFormat::prefix($format, $store, $when);

        $latest = Sale::withTrashed()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $seq = $latest ? NumberFormat::extractSeq($latest) + 1 : 1;

        return NumberFormat::render($format, $store, $when, $seq);
    }
}
