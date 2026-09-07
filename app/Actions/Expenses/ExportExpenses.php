<?php

namespace App\Actions\Expenses;

use App\Models\Expense;
use App\Services\Excel\SpreadsheetWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloadable spreadsheet of expenses (CSV / XLSX) for the active store.
 *
 * Extension points:
 *   - filter `expenses.export.header` → adjust the header row
 *   - filter `expenses.export.row`    → adjust each data row (row array + Expense)
 */
class ExportExpenses
{
    public function __construct(private SpreadsheetWriter $writer) {}

    public function __invoke(string $format = 'csv', ?int $storeId = null): StreamedResponse
    {
        $format   = strtolower($format);
        $filename = 'expenses-'.now()->format('Y-m-d').".{$format}";

        $expenses = Expense::query()
            ->with(['category:id,name', 'paymentMethod:id,name', 'supplier:id,name'])
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->ordered()
            ->get();

        $header = apply_filters('expenses.export.header', [
            'Number',
            'Date',
            'Category',
            'Amount',
            'Tax',
            'Total',
            'Payment Method',
            'Supplier',
            'Reference',
            'Description',
            'Status',
            'Created at',
        ]);

        $rows = $expenses->map(function (Expense $e) {
            $row = [
                (string) $e->number,
                optional($e->expense_date)->toDateString() ?? '',
                (string) ($e->category?->name ?? ''),
                (string) $e->amount,
                (string) $e->tax_amount,
                (string) $e->total,
                (string) ($e->paymentMethod?->name ?? ''),
                (string) ($e->supplier?->name ?? ''),
                (string) ($e->reference ?? ''),
                (string) ($e->description ?? ''),
                (string) $e->status,
                optional($e->created_at)->toDateTimeString() ?? '',
            ];

            return apply_filters('expenses.export.row', $row, $e);
        })->all();

        return $this->writer->stream($filename, $header, function (callable $write) use ($rows) {
            $write($rows);
        });
    }
}
