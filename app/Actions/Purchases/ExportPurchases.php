<?php

namespace App\Actions\Purchases;

use App\Events\PurchasesExported;
use App\Models\Purchase;
use App\Services\Excel\SpreadsheetWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Build a downloadable spreadsheet of all purchases.
 *
 * Extension points:
 *   - filter `purchases.export.header`  → adjust the header row
 *   - filter `purchases.export.row`     → adjust each data row (row array + Purchase)
 *   - action `purchases.before_export`  → side effects before stream
 *   - action `purchases.after_export`   → fires after rows are buffered
 *   - event  PurchasesExported          → decoupled listeners
 */
class ExportPurchases
{
    public function __construct(private SpreadsheetWriter $writer) {}

    public function __invoke(string $format = 'csv'): StreamedResponse
    {
        $format   = strtolower($format);
        $filename = 'purchases-'.now()->format('Y-m-d').".{$format}";

        $purchases = Purchase::query()
            ->with(['supplier', 'store'])
            ->orderBy('order_date', 'desc')
            ->get();

        $header = apply_filters('purchases.export.header', [
            'ID',
            'Number',
            'Order Date',
            'Expected Date',
            'Supplier',
            'Store',
            'Status',
            'Subtotal',
            'Tax',
            'Discount',
            'Grand Total',
            'Amount Paid',
            'Balance Due',
            'Currency',
            'Created at',
        ]);

        $rows = $purchases->map(function (Purchase $p) {
            $row = [
                (string) $p->id,
                (string) $p->number,
                optional($p->order_date)->toDateString() ?? '',
                optional($p->expected_date)->toDateString() ?? '',
                (string) ($p->supplier?->name ?? ''),
                (string) ($p->store?->name ?? ''),
                (string) $p->status,
                (string) ($p->subtotal ?? '0'),
                (string) ($p->tax_total ?? '0'),
                (string) ($p->discount_total ?? '0'),
                (string) ($p->grand_total ?? '0'),
                (string) ($p->amount_paid ?? '0'),
                (string) ($p->balance_due ?? '0'),
                (string) ($p->currency_code ?? ''),
                optional($p->created_at)->toDateTimeString() ?? '',
            ];
            return apply_filters('purchases.export.row', $row, $p);
        })->all();

        $count = count($rows);

        do_action('purchases.before_export', $format, $count);

        $response = $this->writer->stream($filename, $header, function (callable $write) use ($rows) {
            $write($rows);
        });

        do_action('purchases.after_export', $format, $count);
        event(new PurchasesExported($format, $count));

        return $response;
    }
}
