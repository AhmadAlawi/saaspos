<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedReport extends Model
{
    protected $fillable = [
        'user_id', 'store_id', 'report_key', 'name', 'description', 'parameters', 'is_shared',
    ];

    protected $casts = [
        'parameters' => 'array',
        'is_shared'  => 'bool',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * A user sees their own saved reports plus any shared with a store they
     * can reach. Super admins see every shared report.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(function (Builder $w) use ($user) {
            $w->where('user_id', $user->id);

            if ($user->is_super_admin) {
                $w->orWhere('is_shared', true);

                return;
            }

            $storeIds = $user->accessibleStoreIds();
            $w->orWhere(function (Builder $s) use ($storeIds) {
                $s->where('is_shared', true)->whereIn('store_id', $storeIds);
            });
        });
    }
}
