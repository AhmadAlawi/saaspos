<?php

namespace App\Actions\Products;

use App\Models\PriceRule;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * Returns the effective `cost_price` / `selling_price` / `mrp` for a
 * product (or one of its variants) at a given store, honoring per-store
 * overrides.
 *
 * Resolution order, per column, most-specific wins:
 *
 *   Variant line (a `$variant` is passed):
 *     1. store×variant override — product_store_prices (store_id, variant_id)
 *     2. variant base price     — product_variants.cost/selling
 *     3. product base price     — products.cost/selling (cost fallback only;
 *        variant products keep selling on the variant)
 *
 *   Product line (no `$variant`):
 *     1. store override — product_prices (store_id, variant_id IS NULL)
 *     2. product base   — products.cost/selling/mrp
 *
 * Columns resolve independently — a store can override just selling
 * price (a local promo) while inheriting cost + mrp.
 *
 * Returns an associative array with keys `cost_price`, `selling_price`,
 * `mrp`. The MRP value may be null.
 *
 * Usage (sales / checkout):
 *
 *   $prices = (new ResolveProductPrice)($product, $storeId, $variant);
 *   $line->unit_price = $prices['selling_price'];
 *
 * Hook points:
 *   - filter `product.prices.resolved` → adjust the final array
 *     (loyalty discounts, time-based promos, etc. layer on here).
 */
class ResolveProductPrice
{
    /**
     * @return array{cost_price: string, selling_price: string, sale_price: ?string, mrp: ?string, charge_price: string}
     */
    public function __invoke(Product $product, int|string|null $storeId = null, ?ProductVariant $variant = null): array
    {
        // Base layer — variant prices take precedence over the parent's,
        // falling through to the product for any column the variant
        // leaves null.
        if ($variant) {
            $mrp = $variant->mrp ?? $product->mrp;
            $salePrice = $variant->sale_price ?? $product->sale_price;
            $effective = [
                'cost_price'    => (string) ($variant->cost_price    ?? $product->cost_price),
                'selling_price' => (string) ($variant->selling_price ?? $product->selling_price),
                'sale_price'    => $salePrice !== null ? (string) $salePrice : null,
                'mrp'           => $mrp !== null ? (string) $mrp : null,
            ];
        } else {
            $effective = [
                'cost_price'    => (string) $product->cost_price,
                'selling_price' => (string) $product->selling_price,
                'sale_price'    => $product->sale_price !== null ? (string) $product->sale_price : null,
                'mrp'           => $product->mrp !== null ? (string) $product->mrp : null,
            ];
        }

        if ($storeId) {
            $query = DB::table('product_store_prices')
                ->where('product_id', $product->id)
                ->where('store_id', $storeId);

            $query = $variant
                ? $query->where('variant_id', $variant->id)
                : $query->whereNull('variant_id');

            $override = $query->first(['cost_price', 'selling_price', 'mrp']);

            if ($override) {
                foreach (['cost_price', 'selling_price', 'mrp'] as $col) {
                    if ($override->{$col} !== null) {
                        $effective[$col] = (string) $override->{$col};
                    }
                }
            }
            // No per-store override for sale_price yet — product_store_prices
            // doesn't carry that column. A store-level promo override can
            // land here later; for now the base sale_price applies store-wide.
        }

        // What actually gets charged — the whole point of this tier.
        $effective['charge_price'] = $effective['sale_price'] ?? $effective['selling_price'];
        $effective['price_rule']   = null;

        // A running scheduled price rule (Merchandising → Price rules)
        // overrides everything above — it's a deliberate, time-boxed
        // promotion, so it wins over both the normal selling price and
        // any static `sale_price` override. Most specific scope wins:
        // a rule targeting this exact product beats one targeting its
        // category, which beats a storewide "all products" rule.
        $rule = $this->activeRule($product, $storeId);
        if ($rule) {
            $effective['charge_price'] = $this->applyRule($effective['selling_price'], $rule);
            $effective['price_rule']   = [
                'id'             => $rule->id,
                'name'           => $rule->name,
                'discount_type'  => $rule->discount_type,
                'discount_value' => (string) $rule->discount_value,
            ];
        }

        return apply_filters('product.prices.resolved', $effective, $product, $storeId, $variant);
    }

    /**
     * The best currently-running rule for this product, checked most
     * specific scope first. "Best" among ties at the same scope is
     * whichever discounts the most — an admin accidentally overlapping
     * two rules should never short-change a customer.
     */
    private function activeRule(Product $product, int|string|null $storeId): ?PriceRule
    {
        foreach ([PriceRule::SCOPE_PRODUCT, PriceRule::SCOPE_CATEGORY, PriceRule::SCOPE_ALL] as $scope) {
            if ($scope === PriceRule::SCOPE_PRODUCT) {
                $query = PriceRule::query()->where('scope', $scope)->where('product_id', $product->id);
            } elseif ($scope === PriceRule::SCOPE_CATEGORY) {
                if (! $product->category_id) {
                    continue;
                }
                $query = PriceRule::query()->where('scope', $scope)->where('category_id', $product->category_id);
            } else {
                $query = PriceRule::query()->where('scope', $scope);
            }

            $query->running()->where(function ($q) use ($storeId) {
                $q->whereNull('store_id')->orWhere('store_id', $storeId);
            });

            $candidates = $query->get();
            if ($candidates->isEmpty()) {
                continue;
            }

            return $candidates->reduce(function (?PriceRule $best, PriceRule $rule) {
                if (! $best) {
                    return $rule;
                }

                return bccomp((string) $rule->discount_value, (string) $best->discount_value, 4) > 0 ? $rule : $best;
            });
        }

        return null;
    }

    private function applyRule(string $sellingPrice, PriceRule $rule): string
    {
        $value = (string) $rule->discount_value;

        $price = $rule->discount_type === PriceRule::TYPE_PERCENT
            ? bcmul($sellingPrice, bcdiv(bcsub('100', $value, 8), '100', 8), 4)
            : bcsub($sellingPrice, $value, 4);

        return bccomp($price, '0', 4) > 0 ? $price : '0.0000';
    }
}
