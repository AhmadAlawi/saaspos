<?php

namespace App\Actions\Customers;

use App\Events\CustomerGroupsExported;
use App\Models\CustomerGroup;
use App\Services\Excel\SpreadsheetWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Build a downloadable spreadsheet of all customer groups.
 *
 * Extension points:
 *   - filter `customer_groups.export.header`  → adjust the header row
 *   - filter `customer_groups.export.row`     → adjust each data row
 *   - action `customer_groups.before_export`  → side effects before stream
 *   - action `customer_groups.after_export`   → fires after rows are buffered
 *   - event  CustomerGroupsExported           → decoupled listeners
 */
class ExportCustomerGroups
{
    public function __construct(private SpreadsheetWriter $writer) {}

    public function __invoke(string $format = 'csv'): StreamedResponse
    {
        $format   = strtolower($format);
        $filename = 'customer-groups-'.now()->format('Y-m-d').".{$format}";

        $groups = CustomerGroup::query()->orderBy('name')->get();

        $header = apply_filters('customer_groups.export.header', [
            'ID',
            'Name',
            'Default Discount %',
            'Active',
            'Created at',
        ]);

        $rows = $groups->map(function (CustomerGroup $g) {
            $row = [
                (string) $g->id,
                (string) $g->name,
                (string) ($g->default_discount_percent ?? '0'),
                $g->is_active ? 'Yes' : 'No',
                optional($g->created_at)->toDateTimeString() ?? '',
            ];
            return apply_filters('customer_groups.export.row', $row, $g);
        })->all();

        $count = count($rows);

        do_action('customer_groups.before_export', $format, $count);

        $response = $this->writer->stream($filename, $header, function (callable $write) use ($rows) {
            $write($rows);
        });

        do_action('customer_groups.after_export', $format, $count);
        event(new CustomerGroupsExported($format, $count));

        return $response;
    }
}
