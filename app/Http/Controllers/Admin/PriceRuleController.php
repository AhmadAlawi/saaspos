<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PriceRuleRequest;
use App\Models\Category;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Scheduled price rules — "20% off all Shoes, Aug 10-12" — as opposed to
 * the manual per-sale discount a cashier applies at checkout. See
 * {@see \App\Actions\Products\ResolveProductPrice} for how a running rule
 * actually changes what a product charges.
 */
class PriceRuleController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('products.manage_price_rules');

        $rows = PriceRule::query()
            ->with(['category:id,name', 'product:id,name', 'store:id,name'])
            ->orderByDesc('starts_at')
            ->paginate(25);

        return view('admin.price-rules.index', ['rows' => $rows, 'now' => now()]);
    }

    public function create(): View
    {
        $this->authorize('products.manage_price_rules');

        return view('admin.price-rules.form', [
            'rule'       => new PriceRule(['scope' => PriceRule::SCOPE_CATEGORY, 'discount_type' => PriceRule::TYPE_PERCENT, 'is_active' => true]),
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'stores'     => Store::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(PriceRuleRequest $request): RedirectResponse
    {
        $this->authorize('products.manage_price_rules');

        $data = $request->persistedAttributes();
        $data['created_by'] = $request->user()?->id;
        $rule = PriceRule::create($data);

        return redirect()->route('admin.price-rules.index')
            ->with('success', __('price_rules.flash.created', ['name' => $rule->name]));
    }

    public function edit(PriceRule $priceRule): View
    {
        $this->authorize('products.manage_price_rules');

        return view('admin.price-rules.form', [
            'rule'       => $priceRule,
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'stores'     => Store::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'productLabel' => $priceRule->product?->name,
        ]);
    }

    public function update(PriceRuleRequest $request, PriceRule $priceRule): RedirectResponse
    {
        $this->authorize('products.manage_price_rules');

        $priceRule->update($request->persistedAttributes());

        return redirect()->route('admin.price-rules.index')
            ->with('success', __('price_rules.flash.updated', ['name' => $priceRule->name]));
    }

    public function destroy(PriceRule $priceRule): RedirectResponse
    {
        $this->authorize('products.manage_price_rules');
        $name = $priceRule->name;
        $priceRule->delete();

        return redirect()->route('admin.price-rules.index')
            ->with('success', __('price_rules.flash.deleted', ['name' => $name]));
    }

    /**
     * JSON product search for the create/edit form's product picker
     * (scope=product) — same shape the cashier scan bar consumes.
     */
    public function searchProducts(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('products.manage_price_rules');

        $q = trim((string) $request->query('q', ''));
        $rows = Product::query()
            ->where('is_active', true)
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('sku', $q)
                    ->orWhere('barcode', $q);
            }))
            ->orderBy('name')
            ->limit(25)
            ->get(['id', 'name', 'sku']);

        return response()->json($rows);
    }
}
