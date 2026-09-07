<?php

namespace Database\Seeders;

use App\Actions\Expenses\CreateExpense;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Demo business expenses — ~25 records spread across ~90 days and every
 * seeded category, so the Expenses list, its summary cards, and the P&L
 * "Expenses" section have realistic data out of the box.
 *
 * Recurring outgoings (rent / utilities / salaries) repeat monthly; the rest
 * are one-offs with mixed amounts, tax, and payment methods. A few carry a
 * supplier + reference. Every row is created through {@see CreateExpense} so
 * the number is generated and the accounting entry is auto-posted (same as a
 * user recording one by hand).
 *
 * Idempotent: skips if any expense's description starts with `[DEMO]`.
 *
 *   /seed?class=ExpensesDemoSeeder
 *
 * Depends on a store, expense categories ({@see ExpenseCategoriesSeeder}), and
 * at least one user. Payment method + supplier are optional. Skips with a warn
 * if the essentials are missing.
 */
class ExpensesDemoSeeder extends Seeder
{
    public function run(CreateExpense $create): void
    {
        if (Expense::query()->where('description', 'like', '[DEMO]%')->exists()) {
            $this->command?->line('Expense demo data already present.');
            return;
        }

        $user  = User::query()->orderBy('id')->first();
        $store = Store::query()->where('is_active', true)->orderBy('id')->first();
        $cats  = ExpenseCategory::query()->pluck('id', 'name');   // name => id

        if (! $user || ! $store || $cats->isEmpty()) {
            $this->command?->warn('Skipping — need a user, an active store, and expense categories (run ExpenseCategoriesSeeder).');
            return;
        }

        $methods   = PaymentMethod::query()->where('is_active', true)->get();
        $cash      = $methods->firstWhere('type', 'cash') ?? $methods->first();
        $bank      = $methods->firstWhere('type', 'card') ?? $cash;
        $suppliers = Supplier::query()->limit(6)->get();

        // [cat, amount, tax%, daysAgo, method('cash'|'bank'), supplier?, ref?, note]
        $templates = [
            // Rent — monthly, paid from the bank.
            ['Rent',                  20000, 0,  4,  'bank', false, 'RENT-07', 'Shop rent — July'],
            ['Rent',                  20000, 0,  34, 'bank', false, 'RENT-06', 'Shop rent — June'],
            ['Rent',                  20000, 0,  64, 'bank', false, 'RENT-05', 'Shop rent — May'],

            // Salaries — monthly.
            ['Salaries & Wages',      48000, 0,  2,  'bank', false, null,      'Staff salaries — July'],
            ['Salaries & Wages',      46500, 0,  32, 'bank', false, null,      'Staff salaries — June'],
            ['Salaries & Wages',      46500, 0,  62, 'bank', false, null,      'Staff salaries — May'],

            // Utilities — monthly, mixed.
            ['Utilities',              4350, 0,  8,  'bank', false, 'ELEC-071','Electricity bill'],
            ['Utilities',              3980, 0,  38, 'bank', false, 'ELEC-061','Electricity bill'],
            ['Utilities',              1250, 0,  12, 'cash', false, null,      'Water charges'],

            // Supplies — a few, some taxed, some via supplier.
            ['Supplies',               2450, 18, 6,  'cash', true,  'PO-3391', 'Packaging & carry bags'],
            ['Supplies',                890, 18, 18, 'cash', true,  'PO-3402', 'Cleaning supplies'],
            ['Supplies',               1620, 12, 41, 'cash', false, null,      'Stationery & printer ink'],
            ['Supplies',                540, 5,  70, 'cash', false, null,      'Miscellaneous store supplies'],

            // Marketing.
            ['Marketing',              8500, 18, 10, 'bank', true,  'MKT-118', 'Social media ads'],
            ['Marketing',              5200, 18, 40, 'bank', false, 'MKT-102', 'Local flyer printing'],
            ['Marketing',             12000, 18, 72, 'bank', true,  'MKT-090', 'Festival banner campaign'],

            // Maintenance & Repairs.
            ['Maintenance & Repairs',  3200, 18, 20, 'cash', true,  'SVC-556', 'AC servicing'],
            ['Maintenance & Repairs',  6800, 18, 55, 'cash', true,  'SVC-540', 'Refrigeration unit repair'],

            // Transport.
            ['Transport',               450, 0,  3,  'cash', false, null,      'Delivery fuel'],
            ['Transport',               980, 0,  16, 'cash', false, null,      'Goods pickup — tempo hire'],
            ['Transport',              1240, 0,  47, 'cash', false, null,      'Courier & freight'],

            // Bank charges.
            ['Bank Charges',            120, 0,  30, 'bank', false, null,      'Monthly account fees'],
            ['Bank Charges',            260, 0,  60, 'bank', false, null,      'Card settlement charges'],

            // Miscellaneous.
            ['Miscellaneous',           600, 0,  25, 'cash', false, null,      'Staff refreshments'],
            ['Miscellaneous',          1500, 0,  52, 'cash', false, null,      'Local licence renewal'],
        ];

        $created = 0;

        foreach ($templates as $i => $t) {
            [$catName, $amount, $taxPct, $days, $methodPref, $useSupplier, $ref, $note] = $t;

            $catId = $cats[$catName] ?? null;
            if (! $catId) {
                continue; // category not seeded — skip this one
            }

            $method   = $methodPref === 'bank' ? $bank : $cash;
            $supplier = $useSupplier && $suppliers->isNotEmpty() ? $suppliers[$i % $suppliers->count()] : null;
            $tax      = round($amount * $taxPct / 100, 4);

            $create([
                'category_id'       => $catId,
                'expense_date'      => Carbon::now()->subDays($days)->toDateString(),
                'amount'            => (string) $amount,
                'tax_amount'        => (string) $tax,
                'payment_method_id' => $method?->id,
                'supplier_id'       => $supplier?->id,
                'reference'         => $ref,
                'description'       => '[DEMO] '.$note,
            ], $user, $store->id);

            $created++;
        }

        $this->command?->info("Expenses demo: created {$created} records.");
    }
}
