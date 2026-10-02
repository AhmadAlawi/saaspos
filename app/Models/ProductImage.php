<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One captured photo in a product's gallery — additive, distinct from
 * `Product.image_path` (the single admin-controlled catalog image).
 *
 * Backed by the pre-existing polymorphic `product_images` table
 * (`imageable_type`/`imageable_id`, from `create_catalog.php` — real
 * schema, just never given a model/feature until now). Only ever
 * pointed at a `Product` today, but kept `morphTo` since that's the
 * table's own shape, not something this feature introduced.
 */
class ProductImage extends Model
{
    protected $fillable = [
        'imageable_type',
        'imageable_id',
        'path',
        'sort_order',
        'uploaded_by',
    ];

    /** @return MorphTo<Model, $this> */
    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return Attribute<?string, never> */
    protected function url(): Attribute
    {
        return Attribute::get(fn () => $this->path
            ? '/storage/'.ltrim($this->path, '/')
            : null);
    }
}
