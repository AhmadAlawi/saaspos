<?php

namespace App\Actions\Customers;

use App\Events\CustomersExported;
use App\Models\Customer;
use App\Services\Excel\SpreadsheetWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Build a downloadable spreadsheet of all customers.
 *
 * Extension points:
 *   - filter `customers.export.header`  → adjust the header row
 *   - filter `customers.export.row`     → adjust each data row (row array + Customer)
 *   - action `customers.before_export`  → side effects before stream
 *   - action `customers.after_export`   → fires after rows are buffered
 *   - event  CustomersExported          → decoupled listeners
 */
class ExportCustomers
{
    public function __construct(private SpreadsheetWriter $writer) {}

    public function __invoke(string $format = 'csv'): StreamedResponse
    {
        $format   = strtolower($format);
        $filename = 'customers-'.now()->format('Y-m-d').".{$format}";

        $customers = Customer::query()->with('group')->orderBy('name')->get();

        $header = apply_filters('customers.export.header', [
            'ID',
            'Code',
            'Name',
            'Business',
            'Business Name',
            'Email',
            'Phone',
            'Group',
            'Credit Limit',
            'Outstanding Balance',
            'Active',
            'Created at',
        ]);

        $rows = $customers->map(function (Customer $c) {
            $row = [
                (string) $c->id,
                (string) ($c->code ?? ''),
                (string) $c->name,
                $c->is_business ? 'Yes' : 'No',
                (string) ($c->business_name ?? ''),
                (string) ($c->email ?? ''),
                \App\Support\PhoneFormatter::pretty($c->phone),
                (string) ($c->group?->name ?? ''),
                (string) ($c->credit_limit ?? '0'),
                (string) ($c->outstanding_balance ?? '0'),
                $c->is_active ? 'Yes' : 'No',
                optional($c->created_at)->toDateTimeString() ?? '',
            ];
            return apply_filters('customers.export.row', $row, $c);
        })->all();

        $count = count($rows);

        do_action('customers.before_export', $format, $count);

        $response = $this->writer->stream($filename, $header, function (callable $write) use ($rows) {
            $write($rows);
        });

        do_action('customers.after_export', $format, $count);
        event(new CustomersExported($format, $count));

        return $response;
    }
}
