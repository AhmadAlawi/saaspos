<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A node in the chart-of-accounts hierarchy (Assets → Current Assets → …).
 * Groups carry the top-level `type` every account under them inherits for
 * reporting (asset / liability / equity / income / expense).
 */
class AccountGroup extends Model
{
    public const TYPE_ASSET     = 'asset';
    public const TYPE_LIABILITY = 'liability';
    public const TYPE_EQUITY    = 'equity';
    public const TYPE_INCOME    = 'income';
    public const TYPE_EXPENSE   = 'expense';

    protected $fillable = ['parent_id', 'name', 'type', 'sort_order', 'is_system'];

    protected function casts(): array
    {
        return [
            'is_system'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /** Whole subtree, eager-loaded for the chart-of-accounts tree view. */
    public function childrenRecursive(): HasMany
    {
        return $this->children()->with(['accountsOrdered', 'childrenRecursive']);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /** Accounts under this group in code order (for the tree view). */
    public function accountsOrdered(): HasMany
    {
        return $this->hasMany(Account::class)->orderBy('code');
    }
}
