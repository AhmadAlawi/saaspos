<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One NVR per store — every branch has its own Hikvision device on its
 * own network, not one shared box. Always fetched via
 * {@see CameraSettings::forStore()}; a store with no row here just has
 * no camera integration configured yet.
 */
class CameraSettings extends Model
{
    protected $fillable = [
        'store_id',
        'host',
        'port',
        'rtsp_port',
        'use_https',
        'username',
        'password',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'use_https' => 'boolean',
            'is_active' => 'boolean',
            'password'  => 'encrypted',
        ];
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public static function forStore(int $storeId): self
    {
        return static::query()->where('store_id', $storeId)->first()
            ?? new self(['store_id' => $storeId, 'port' => 80, 'use_https' => false, 'is_active' => false]);
    }
}
