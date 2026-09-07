<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Indirection between a business-event key (e.g. `sales_revenue`, `cogs`,
 * `accounts_payable_suppliers`) and the actual {@see Account} an auto-posted
 * journal entry should hit. A null `store_id` is the global default; a row
 * with a `store_id` overrides it for that store. Resolved by
 * {@see \App\Services\Accounting\AccountMappingResolver}.
 */
class AccountMapping extends Model
{
    protected $fillable = ['key', 'account_id', 'store_id'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
