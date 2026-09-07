<?php

namespace App\Http\Controllers\Pricing;

use App\Actions\Products\ResolveProductPrice;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public, no-login barcode price-check page — served from its own
 * domain (pricing.infinityglobal.com.jo) precisely so a customer never
 * sees the POS/admin hostname. A shopper scans a barcode with their
 * phone's camera and gets back the product name + selling price only;
 * nothing else (cost, stock, SKU) is ever exposed here.
 */
class PriceCheckController extends Controller
{
    public function show(): View
    {
        $company = Company::current() ?? new Company();

        return view('pricing.check', [
            'shopName' => $company->name ?: 'Store',
            'logoUrl'  => $company->app_logo_url ?? $company->logo_url,
        ]);
    }

    /** @return JsonResponse {found: bool, name?: string, price?: string} */
    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64'],
        ]);
        $code = trim($data['code']);

        $store = Store::query()->orderBy('id')->first();

        // Variant barcode/SKU first — it's the more specific match.
        $variant = ProductVariant::query()
            ->where(fn ($q) => $q->where('barcode', $code)->orWhere('sku', $code))
            ->whereHas('product', fn ($q) => $q->where('is_active', true))
            ->with('product')
            ->first();

        if ($variant) {
            $prices = (new ResolveProductPrice())($variant->product, $store?->id, $variant);

            return response()->json([
                'found' => true,
                'name'  => $variant->product->name.($variant->label ? ' — '.$variant->label : ''),
                'price' => format_money($prices['selling_price']),
            ]);
        }

        $product = Product::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('barcode', $code)
                ->orWhere('sku', $code)
                ->orWhereHas('barcodes', fn ($q2) => $q2->where('barcode', $code)))
            ->first();

        if ($product) {
            $prices = (new ResolveProductPrice())($product, $store?->id);

            return response()->json([
                'found' => true,
                'name'  => $product->name,
                'price' => format_money($prices['selling_price']),
            ]);
        }

        return response()->json(['found' => false]);
    }
}
