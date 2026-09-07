<?php

namespace App\Actions\Users;

use App\Events\UsersExported;
use App\Models\User;
use App\Services\Excel\SpreadsheetWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Build a downloadable spreadsheet of all users.
 *
 * Extension points:
 *   - filter `users.export.header`  → adjust the header row
 *   - filter `users.export.row`     → adjust each data row (row array + User)
 *   - action `users.before_export`  → side effects before stream
 *   - action `users.after_export`   → fires after rows are buffered
 *   - event  UsersExported          → decoupled listeners
 */
class ExportUsers
{
    public function __construct(private SpreadsheetWriter $writer) {}

    public function __invoke(string $format = 'csv'): StreamedResponse
    {
        $format   = strtolower($format);
        $filename = 'users-'.now()->format('Y-m-d').".{$format}";

        $users = User::query()->orderBy('name')->get();

        $header = apply_filters('users.export.header', [
            'ID',
            'Name',
            'Email',
            'Phone',
            'Active',
            'Created at',
        ]);

        $rows = $users->map(function (User $u) {
            $row = [
                (string) $u->id,
                (string) $u->name,
                (string) ($u->email ?? ''),
                (string) ($u->phone ?? ''),
                $u->is_active ? 'Yes' : 'No',
                optional($u->created_at)->toDateTimeString() ?? '',
            ];
            return apply_filters('users.export.row', $row, $u);
        })->all();

        $count = count($rows);

        do_action('users.before_export', $format, $count);

        $response = $this->writer->stream($filename, $header, function (callable $write) use ($rows) {
            $write($rows);
        });

        do_action('users.after_export', $format, $count);
        event(new UsersExported($format, $count));

        return $response;
    }
}
