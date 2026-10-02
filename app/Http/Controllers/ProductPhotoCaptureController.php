<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Barcodes\ScannedProductResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Mobile-web floor tool: scan a product's barcode with a phone-connected
 * barcode reader, then take a photo of it. Scanning the same barcode
 * again ADDS another photo — this is a pure gallery, never touching the
 * single admin-controlled `products.image_path`. No app-store app; this
 * is a normal authenticated page an employee bookmarks / "Add to Home
 * Screen"s.
 *
 * Gated on the narrow `products.photos.capture` permission (not
 * `products.update`) so an employee can be granted ONLY this.
 */
class ProductPhotoCaptureController extends Controller
{
    private const MAX_KB = 8192; // 8 MB — phone camera photos run larger than the desktop bulk-upload cap.

    public function show(Request $request): View
    {
        $this->authorizeCapture($request);

        return view('products.photo-capture');
    }

    /** Barcode → product, same shape as StockAdjustmentController::scan(), plus the existing photo gallery. */
    public function scan(Request $request, ScannedProductResolver $resolver): JsonResponse
    {
        $this->authorizeCapture($request);

        $barcode = trim((string) $request->query('barcode', ''));
        if ($barcode === '') {
            return response()->json(['message' => __('products.photo_capture.scan.empty')], 422);
        }

        $row = $resolver->resolve($barcode);
        if ($row === null) {
            return response()->json([
                'message' => __('products.photo_capture.scan.not_found', ['barcode' => $barcode]),
            ], 404);
        }

        $product = Product::query()->find($row['product_id']);
        if (! $product) {
            return response()->json([
                'message' => __('products.photo_capture.scan.not_found', ['barcode' => $barcode]),
            ], 404);
        }

        return response()->json([
            'product_id' => $product->id,
            'name'       => $product->name,
            'sku'        => $product->sku,
            'photos'     => $product->photos->map(fn (ProductImage $p) => ['id' => $p->id, 'url' => $p->url])->values(),
        ]);
    }

    /** Append one photo to the product's gallery — never touches image_path. */
    public function store(Request $request, Product $product): JsonResponse
    {
        $this->authorizeCapture($request);

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_KB],
        ]);

        $path = $request->file('photo')->store('products', 'public');

        $photo = $product->photos()->create([
            'path'        => $path,
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json([
            'photo' => ['id' => $photo->id, 'url' => $photo->url],
            'count' => $product->photos()->count(),
        ], 201);
    }

    /** Delete one captured photo — fixes a bad shot on the spot. */
    public function destroy(Request $request, Product $product, ProductImage $photo): JsonResponse
    {
        $this->authorizeCapture($request);
        abort_unless($photo->imageable_type === $product->getMorphClass() && $photo->imageable_id === $product->id, 404);

        if (Storage::disk('public')->exists($photo->path)) {
            Storage::disk('public')->delete($photo->path);
        }
        $photo->delete();

        return response()->json(['count' => $product->photos()->count()]);
    }

    private function authorizeCapture(Request $request): void
    {
        abort_unless($request->user()?->hasPermission('products.photos.capture'), 403);
    }
}
