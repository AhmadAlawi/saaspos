<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An extra/alternate barcode for a product, beyond its primary `products.barcode`. */
class ProductBarcode extends Model
{
    use Auditable;

    protected $fillable = ['product_id', 'barcode'];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
