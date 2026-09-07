<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed application lookup data. Every seeder here is idempotent — safe to re-run.
     *
     * Company / store / admin-user rows are created by the installer, NOT here
     * (see CLAUDE.md §15 and docs/features/installer.md).
     *
     * ChartOfAccountsSeeder is conditional: it skips when no company row exists yet,
     * so running this set before the installer is harmless.
     *
     * DemoData seeders (Retail/Pharmacy/Supermarket) are deliberately NOT listed here.
     * They run from the installer when the customer opts in.
     */
    public function run(): void
    {
        $this->call([
            PermissionsSeeder::class,
            RolesSeeder::class,
            CurrenciesSeeder::class,
            LanguagesSeeder::class,
            UnitsSeeder::class,
            PaymentMethodsSeeder::class,
            ReturnReasonsSeeder::class,
            StockAdjustmentReasonsSeeder::class,
            ChartOfAccountsSeeder::class,
            ExpenseCategoriesSeeder::class,
        ]);
    }
}
