<?php

namespace App\Actions\Expenses;

use App\Models\Expense;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Record a business expense for a store.
 *
 * v1.0 records expenses straight as `approved` (no approval queue). The
 * journal posting, recurring schedule, and cash-drawer pay-out linkage
 * are deferred to their own slices.
 *
 * Hooks:
 *   - action `expense.before_create` → ($data, $storeId)
 *   - action `expense.after_create`  → ($expense)
 *
 * @param array<string, mixed> $data
 */
class CreateExpense
{
    public function __construct(private readonly GenerateExpenseNumber $generateNumber) {}

    public function __invoke(array $data, ?User $user, int $storeId): Expense
    {
        do_action('expense.before_create', $data, $storeId);

        return DB::transaction(function () use ($data, $user, $storeId) {
            // Locked for the transaction — same fix as CompleteSale.php's
            // sale-number race: serializes concurrent expense creations
            // for the same store so GenerateExpenseNumber's unlocked
            // MAX(number) read can't collide.
            Store::query()->where('id', $storeId)->lockForUpdate()->firstOrFail();

            $expense = new Expense();
            $expense->fill($data);
            $expense->forceFill([
                'store_id'    => $storeId,
                'number'      => ($this->generateNumber)($storeId),
                'status'      => Expense::STATUS_APPROVED,
                'approved_by' => $user?->id,
                'approved_at' => now(),
                'created_by'  => $user?->id,
            ]);
            $expense->save();

            $fresh = $expense->fresh();
            do_action('expense.after_create', $fresh);

            return $fresh;
        });
    }
}
