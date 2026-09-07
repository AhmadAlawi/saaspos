<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Products\ImportProductsFromCsv;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Excel\SpreadsheetWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Two-step CSV / XLSX → products import wizard.
 *
 *   1. GET  /admin/products/import          — show the upload form
 *   2. POST /admin/products/import/preview  — upload + return header + sample
 *   3. POST /admin/products/import/commit   — apply with confirmed mapping
 *
 * Staging: the upload is moved to `storage/app/imports/products/` and
 * the relative path is round-tripped through hidden form inputs to step
 * 3. Files are cleaned up after a successful commit; the user can also
 * cancel out at any time (the staged file ages out via storage policy).
 */
class ProductImportController extends Controller
{
    /** Where uploads stage while the wizard runs. */
    private const STAGING_DISK = 'local';
    private const STAGING_DIR  = 'imports/products';

    public function index(): View
    {
        $this->authorize('create', Product::class);

        return view('admin.products.import.index');
    }

    /**
     * Stream a ready-to-fill sample template (CSV or XLSX) so the customer
     * knows exactly which columns to provide and what the values look like.
     *
     * Headers use the friendly names the wizard already auto-maps, and the
     * three example rows cover the three v1.0 industries: a retail bottle,
     * a supermarket weighed item, and a pharmacy batch/expiry medicine.
     * Category / Brand / Unit / Tax Group are matched by NAME, so the
     * customer replaces the sample values with names that exist in their
     * own catalog.
     */
    public function template(Request $request, SpreadsheetWriter $writer): StreamedResponse
    {
        $this->authorize('create', Product::class);

        $format = $request->query('format', 'csv');
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            $format = 'csv';
        }

        $headers = [
            'SKU', 'Name', 'Barcode', 'Category', 'Brand', 'Unit', 'Tax Group',
            'Cost Price', 'Selling Price', 'Sale Price', 'MRP', 'Description', 'Short Description',
            'Track Stock', 'Sold By Weight', 'Track Batches', 'Track Expiry',
            'Reorder Level', 'Reorder Quantity', 'HSN Code', 'Pharmacy Schedule',
            'Generic Name', 'Manufacturer', 'Active', 'Featured', 'Image',
        ];

        $rows = [
            // Retail — simple stocked item. `Image` names the photo file the
            // customer will drop on the Bulk images screen later. Sale Price
            // demonstrates an active promo — 45 charged instead of the 50
            // regular selling price, while the 55 MRP still strikes through.
            ['COKE-500', 'Coca-Cola 500ml Bottle', '5449000000996', 'Beverages', 'Coca-Cola', 'Piece', '',
             '30', '50', '45', '55', 'Chilled soft drink, 500ml PET bottle', '500ml soft drink',
             'Yes', 'No', 'No', 'No', '24', '120', '22021010', '', '', '', 'Yes', 'No', 'coke-500.jpg'],
            // Supermarket — sold by weight. No promo — Sale Price left blank.
            ['APPLE-RED', 'Red Apples (loose)', '', 'Produce', '', 'kg', '',
             '80', '120', '', '', 'Fresh red apples, sold by weight', '',
             'Yes', 'Yes', 'No', 'No', '5', '20', '', '', '', '', 'Yes', 'No', 'red-apples.jpg'],
            // Pharmacy — batch + expiry tracked. Image column left blank — it's
            // optional; a product can be imported now and get its photo later.
            ['PARA-500', 'Paracetamol 500mg Tablet', '8901234567890', 'Medicines', '', 'Strip', '',
             '10', '18', '', '20', '', 'Pain & fever relief',
             'Yes', 'No', 'Yes', 'Yes', '10', '50', '30049099', 'H', 'Paracetamol', 'ABC Pharma', 'Yes', 'No', ''],
        ];

        return $writer->stream(
            'product-import-template.'.$format,
            $headers,
            fn (callable $write) => $write($rows),
        );
    }

    public function preview(Request $request, ImportProductsFromCsv $import): View|RedirectResponse
    {
        $this->authorize('create', Product::class);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:5120'],
        ]);

        // Pick the staging extension from the uploaded filename, not
        // from `store()`'s mime-guessed extension. text/csv often guesses
        // to .txt depending on the PHP mime DB version, which then trips
        // the reader's CSV-vs-XLSX dispatch.
        $clientExt = strtolower($request->file('file')->getClientOriginalExtension() ?: 'csv');
        if (! in_array($clientExt, ['csv', 'xlsx'], true)) {
            $clientExt = 'csv'; // .txt uploads — treat as CSV
        }
        $stored = $request->file('file')->storeAs(
            self::STAGING_DIR,
            Str::random(40).'.'.$clientExt,
            self::STAGING_DISK,
        );
        $abs    = Storage::disk(self::STAGING_DISK)->path($stored);

        try {
            $preview = $import->preview($abs);
        } catch (\Throwable $e) {
            Storage::disk(self::STAGING_DISK)->delete($stored);
            return redirect()
                ->route('admin.products.import')
                ->with('error', __('products.import.errors.unreadable', ['error' => $e->getMessage()]));
        }

        return view('admin.products.import.review', [
            'preview'      => $preview,
            'stagedPath'   => $stored,
            'targetFields' => ImportProductsFromCsv::TARGET_FIELDS,
        ]);
    }

    public function commit(Request $request, ImportProductsFromCsv $import): RedirectResponse|View
    {
        $this->authorize('create', Product::class);

        $data = $request->validate([
            'staged_path' => ['required', 'string'],
            'mapping'     => ['required', 'array'],
            'mapping.*'   => ['nullable', 'string'],
        ]);

        // Defence-in-depth: the staged path must live inside the
        // designated import folder. Without this, a tampered hidden
        // input could point at any file on the storage disk.
        if (! str_starts_with($data['staged_path'], self::STAGING_DIR.'/')) {
            abort(422, 'Invalid staged path.');
        }
        if (! Storage::disk(self::STAGING_DISK)->exists($data['staged_path'])) {
            return redirect()
                ->route('admin.products.import')
                ->with('error', __('products.import.errors.session_expired'));
        }

        $abs = Storage::disk(self::STAGING_DISK)->path($data['staged_path']);

        try {
            $summary = $import->commit($abs, $data['mapping'], $request->user()?->id);
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.products.import')
                ->with('error', $e->getMessage());
        }

        // Clean the staged file once we've definitively consumed it.
        Storage::disk(self::STAGING_DISK)->delete($data['staged_path']);

        return view('admin.products.import.done', [
            'summary' => $summary,
        ]);
    }
}
