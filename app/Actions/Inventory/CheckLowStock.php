<?php

namespace App\Actions\Inventory;

use Illuminate\Support\Facades\DB;

/**
 * Counts products at or below their reorder level (per-store override falling
 * back to the product default — same rule as the Low-stock report) and, when
 * there are any, drops a single notification in the admin bell. Deduped so a
 * daily sweep doesn't stack identical bells while the situation persists.
 *
 * Run from the scheduler; returns the distinct low-stock product count.
 */
class CheckLowStock
{
    public function __invoke(): int
    {
        $count = DB::table('product_stock_levels as l')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->whereNull('p.deleted_at')
            ->whereNotNull(DB::raw('COALESCE(l.reorder_level_override, p.reorder_level)'))
            ->whereColumn('l.quantity', '<=', DB::raw('COALESCE(l.reorder_level_override, p.reorder_level)'))
            ->distinct()
            ->count('l.product_id');

        if ($count > 0 && ! admins_have_unread_notification('low_stock')) {
            notify_admins(
                'low_stock',
                __('notifications.low_stock.title'),
                __('notifications.low_stock.message', ['count' => $count]),
                'box',
                url('/admin/inventory/low-stock'),
                'products.view',
            );
        }

        return $count;
    }
}
