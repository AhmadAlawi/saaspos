<?php

namespace App\Actions\Categories;

use App\Events\CategoriesExported;
use App\Models\Category;
use App\Services\Excel\SpreadsheetWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Build a downloadable spreadsheet of every category — flat list, one
 * row per category, no nesting. Used by the "Export" button on the
 * categories index.
 *
 * Extension points:
 *   - filter `categories.export.header`  → adjust the header row
 *   - filter `categories.export.row`     → adjust each data row
 *                                          (receives the row array + Category)
 *   - action `categories.before_export`  → side effects before stream
 *   - action `categories.after_export`   → fires after rows are buffered
 *   - event  CategoriesExported          → decoupled listeners (audit log, stats)
 *
 * Supported formats: `csv`, `xlsx` — selected by the file extension
 * on the generated filename (see SpreadsheetWriter::WRITERS).
 */
class ExportCategories
{
    public function __construct(private SpreadsheetWriter $writer) {}

    public function __invoke(string $format = 'csv'): StreamedResponse
    {
        $format   = strtolower($format);
        $filename = 'categories-'.now()->format('Y-m-d').".{$format}";

        // Eager-load tax group so cells show the human name, not the
        // id. Categories are small (<10k); when this pattern lands on
        // Products / Sales, switch to chunkById() + yield rows in
        // batches so memory stays flat.
        $categories = Category::query()
            ->with('taxGroup:id,name')
            ->ordered()
            ->get();

        // parent_id → name lookup so the "Parent" cell reads cleanly.
        $nameById = $categories->pluck('name', 'id');

        $header = apply_filters('categories.export.header', [
            'ID',
            'Name',
            'Parent',
            'Tax rule',
            'Color',
            'Active',
            'Created at',
            'Updated at',
        ]);

        $rows = $categories->map(function (Category $c) use ($nameById) {
            $row = [
                (string) $c->id,
                (string) $c->name,
                $c->parent_id ? (string) ($nameById[$c->parent_id] ?? '') : '',
                optional($c->taxGroup)->name ?? '',
                (string) ($c->color ?? ''),
                $c->is_active ? 'Yes' : 'No',
                optional($c->created_at)->toDateTimeString() ?? '',
                optional($c->updated_at)->toDateTimeString() ?? '',
            ];
            return apply_filters('categories.export.row', $row, $c);
        })->all();

        $count = count($rows);

        do_action('categories.before_export', $format, $count);

        $response = $this->writer->stream($filename, $header, function (callable $write) use ($rows) {
            $write($rows);
        });

        do_action('categories.after_export', $format, $count);
        event(new CategoriesExported($format, $count));

        return $response;
    }
}
