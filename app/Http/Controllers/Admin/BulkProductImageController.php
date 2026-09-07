<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Bulk product images — attach photos to many products in one pass, matched by
 * the filename each product declared in its CSV `Image` column (stored in
 * products.image_ref).
 *
 * Why this exists: a customer importing thousands of products can't visit every
 * product page to upload a photo. They put a filename in the import's `Image`
 * column, then drop the whole folder of images here and the files fan out to
 * the right products automatically.
 *
 * The flow is two calls, kept deliberately thin so it survives shared hosting:
 *
 *   1. POST /match  — send just the filenames (JSON, no bytes). Returns which
 *      map to a product, so the screen can show "2,840 matched · 160 unmatched"
 *      BEFORE any upload. Cheap: one indexed `WHERE image_ref IN (…)` query.
 *
 *   2. POST /store  — the browser uploads matched files in SMALL BATCHES
 *      (~15 per request), each request well under `post_max_size`, nothing
 *      long-running server-side. No queue needed.
 *
 * Match is case-insensitive on the basename. A filename claimed by more than one
 * product is a conflict (we can't pick one), reported rather than guessed.
 */
class BulkProductImageController extends Controller
{
    /** Upload constraints — mirror the single-image editor, a touch larger. */
    private const ALLOWED_MIMES = ['jpg', 'jpeg', 'png', 'webp'];
    private const MAX_KB        = 4096;   // 4 MB per image
    private const MAX_BATCH     = 50;     // files accepted in one /store call
    private const MAX_MATCH     = 5000;   // filenames accepted in one /match call

    public function index(): View
    {
        $this->authorize('create', Product::class);

        // How many products are still waiting for a photo they've declared —
        // a ref on record but no stored image. Drives the screen's hint.
        $pending = Product::query()
            ->whereNotNull('image_ref')
            ->whereNull('image_path')
            ->count();

        return view('admin.products.bulk-images.index', [
            'pendingCount' => $pending,
            'maxKb'        => self::MAX_KB,
            'maxBatch'     => self::MAX_BATCH,
            'acceptMimes'  => self::ALLOWED_MIMES,
        ]);
    }

    /**
     * Match a list of filenames to products by image_ref. No files, no writes —
     * this powers the pre-upload "matched / unmatched" preview.
     *
     * Response:
     *   matched:   [{ filename, product_id, name, sku, has_image }]
     *   unmatched: [{ filename, reason: 'no_match' | 'duplicate' }]
     */
    public function match(Request $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $data = $request->validate([
            'filenames'   => ['required', 'array', 'min:1', 'max:'.self::MAX_MATCH],
            'filenames.*' => ['required', 'string', 'max:255'],
        ]);

        // De-dupe + index by lowercased basename. Two different folder paths
        // that end in the same filename are the same match target.
        $wanted = [];
        foreach ($data['filenames'] as $raw) {
            $name = $this->basename($raw);
            if ($name === '') {
                continue;
            }
            $wanted[mb_strtolower($name)] = $name; // keep first original casing for display
        }

        if ($wanted === []) {
            return response()->json(['matched' => [], 'unmatched' => []]);
        }

        // One indexed lookup. Group by lowered ref so we can spot a filename
        // claimed by more than one product (ambiguous → conflict).
        $rows = Product::query()
            ->whereIn(DB::raw('LOWER(image_ref)'), array_keys($wanted))
            ->get(['id', 'name', 'sku', 'image_ref', 'image_path']);

        $byRef = [];
        foreach ($rows as $p) {
            $byRef[mb_strtolower((string) $p->image_ref)][] = $p;
        }

        $matched   = [];
        $unmatched = [];
        foreach ($wanted as $lower => $display) {
            $hits = $byRef[$lower] ?? [];
            if (count($hits) === 0) {
                $unmatched[] = ['filename' => $display, 'reason' => 'no_match'];
            } elseif (count($hits) > 1) {
                $unmatched[] = ['filename' => $display, 'reason' => 'duplicate'];
            } else {
                $p = $hits[0];
                $matched[] = [
                    'filename'   => $display,
                    'product_id' => (int) $p->id,
                    'name'       => (string) $p->name,
                    'sku'        => (string) $p->sku,
                    'has_image'  => $p->image_path !== null,
                ];
            }
        }

        return response()->json([
            'matched'   => $matched,
            'unmatched' => $unmatched,
        ]);
    }

    /**
     * Store one batch of images. Files arrive keyed by product id
     * (`files[<product_id>]`), which the client took from a prior /match call.
     *
     * Each file is validated, stored on the public disk, and set as the
     * product's image_path (replacing + deleting any previous file). Per-file
     * results come back so the UI can mark each row done or failed without one
     * bad file sinking the whole batch.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $request->validate([
            'files'   => ['required', 'array', 'min:1', 'max:'.self::MAX_BATCH],
            'files.*' => ['required', 'file', 'mimes:'.implode(',', self::ALLOWED_MIMES), 'max:'.self::MAX_KB],
        ]);

        /** @var array<int|string, \Illuminate\Http\UploadedFile> $files */
        $files = $request->file('files', []);

        // Load every targeted product once. Keys are product ids (strings from
        // the multipart field names); ignore any that don't resolve.
        $ids      = array_map('intval', array_keys($files));
        $products = Product::query()->whereIn('id', $ids)->get()->keyBy('id');

        $results = [];
        $stored  = 0;

        foreach ($files as $key => $file) {
            $productId = (int) $key;
            $product   = $products->get($productId);

            if (! $product) {
                $results[] = ['product_id' => $productId, 'ok' => false, 'error' => 'not_found'];
                continue;
            }

            try {
                $path = $file->store('products', 'public');
            } catch (\Throwable) {
                $results[] = ['product_id' => $productId, 'ok' => false, 'error' => 'store_failed'];
                continue;
            }

            // Swap in the new image, delete the old file (best-effort).
            $old = $product->image_path;
            $product->forceFill(['image_path' => $path])->save();
            if ($old && $old !== $path && Storage::disk('public')->exists($old)) {
                Storage::disk('public')->delete($old);
            }

            $stored++;
            $results[] = [
                'product_id' => $productId,
                'ok'         => true,
                'image_url'  => $product->image_url,
            ];
        }

        return response()->json([
            'stored'  => $stored,
            'results' => $results,
        ]);
    }

    /** Bare, lower-safe filename from a raw path or URL fragment. */
    private function basename(string $raw): string
    {
        $raw = preg_replace('/[?#].*$/', '', trim($raw)) ?? $raw;

        return basename(str_replace('\\', '/', $raw));
    }
}
