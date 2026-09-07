<?php

namespace App\Actions\Roles;

use App\Events\RolesExported;
use App\Models\Role;
use App\Services\Excel\SpreadsheetWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Build a downloadable spreadsheet of all roles.
 *
 * Extension points:
 *   - filter `roles.export.header`  → adjust the header row
 *   - filter `roles.export.row`     → adjust each data row (row array + Role)
 *   - action `roles.before_export`  → side effects before stream
 *   - action `roles.after_export`   → fires after rows are buffered
 *   - event  RolesExported          → decoupled listeners
 */
class ExportRoles
{
    public function __construct(private SpreadsheetWriter $writer) {}

    public function __invoke(string $format = 'csv'): StreamedResponse
    {
        $format   = strtolower($format);
        $filename = 'roles-'.now()->format('Y-m-d').".{$format}";

        $roles = Role::query()->orderBy('name')->get();

        $header = apply_filters('roles.export.header', [
            'ID',
            'Name',
            'Description',
            'System Role',
            'Created at',
        ]);

        $rows = $roles->map(function (Role $r) {
            $row = [
                (string) $r->id,
                (string) $r->name,
                (string) ($r->description ?? ''),
                $r->is_system ? 'Yes' : 'No',
                optional($r->created_at)->toDateTimeString() ?? '',
            ];
            return apply_filters('roles.export.row', $row, $r);
        })->all();

        $count = count($rows);

        do_action('roles.before_export', $format, $count);

        $response = $this->writer->stream($filename, $header, function (callable $write) use ($rows) {
            $write($rows);
        });

        do_action('roles.after_export', $format, $count);
        event(new RolesExported($format, $count));

        return $response;
    }
}
