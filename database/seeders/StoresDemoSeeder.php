<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo stores — a small multi-branch company so the Stores module,
 * the top-bar switcher, and (later) per-store stock/pricing have
 * realistic data to show. Run with /seed?class=StoresDemoSeeder.
 *
 * Localization is system-wide (not per-store), so every demo store
 * inherits the company's base currency + the existing store's time
 * zone rather than carrying its own.
 *
 * Idempotent:
 *   - matches existing rows by `code` → updateOrInsert
 *   - never touches `is_default` on existing rows (the company keeps
 *     whatever default it already has); only guarantees one exists
 *   - attaches the admin user to each store (insertOrIgnore) so the
 *     switcher lists them even for non-super-admin accounts
 */
class StoresDemoSeeder extends Seeder
{
    public function run(): void
    {
        $now      = now();
        $currency = $this->resolveCurrency();
        $timezone = $this->resolveTimezone();

        if (! $currency) {
            $this->command?->warn('StoresDemoSeeder skipped — no currency available. Run CurrenciesSeeder first.');
            return;
        }

        foreach ($this->storeDefinitions() as $s) {
            DB::table('stores')->updateOrInsert(
                ['code' => $s['code']],
                [
                    'name'                  => $s['name'],
                    'address_line1'         => $s['address_line1'],
                    'city'                  => $s['city'],
                    'state'                 => $s['state'],
                    'postal_code'           => $s['postal_code'],
                    'country_code'          => 'IN',
                    'phone'                 => $s['phone'] ?? null,
                    'email'                 => $s['email'] ?? null,
                    'timezone'              => $timezone,
                    'currency_code'         => $currency,
                    'locale'                => 'en',
                    'rounding_mode'         => 'half_up',
                    'enforce_shifts'        => true,
                    'tax_inclusive_pricing' => false,
                    'cash_variance_tolerance' => 0,
                    'pay_out_threshold'     => 0,
                    'is_active'             => $s['is_active'] ?? true,
                    'updated_at'            => $now,
                    'created_at'            => $now,
                ],
            );
        }

        // Guarantee exactly one default store: if none is flagged yet
        // (e.g. a store seeded outside the installer), promote the earliest.
        if (! DB::table('stores')->where('is_default', true)->exists()) {
            $firstId = DB::table('stores')->whereNull('deleted_at')->orderBy('id')->value('id');
            if ($firstId) {
                DB::table('stores')->where('id', $firstId)->update(['is_default' => true]);
            }
        }

        $this->attachAdmin($now);

        $count = DB::table('stores')->whereNull('deleted_at')->count();
        $this->command?->info("Seeded stores ({$count} rows).");
    }

    /** @return list<array<string, mixed>> */
    private function storeDefinitions(): array
    {
        return [
            [
                'code' => 'DT01', 'name' => 'Downtown Branch',
                'address_line1' => 'Shop 14, Station Road', 'city' => 'Bhuj',
                'state' => 'Gujarat', 'postal_code' => '370001',
                'phone' => '+91 2832 250100', 'email' => 'downtown@demo.test',
            ],
            [
                'code' => 'AHM1', 'name' => 'Ahmedabad Store',
                'address_line1' => '2nd Floor, CG Road', 'city' => 'Ahmedabad',
                'state' => 'Gujarat', 'postal_code' => '380009',
                'phone' => '+91 79 40001234', 'email' => 'ahmedabad@demo.test',
            ],
            [
                'code' => 'WH01', 'name' => 'Central Warehouse',
                'address_line1' => 'Plot 7, Kandla SEZ Road', 'city' => 'Gandhidham',
                'state' => 'Gujarat', 'postal_code' => '370201',
                'phone' => '+91 2836 220500', 'email' => 'warehouse@demo.test',
            ],
            [
                // Inactive on purpose — shows the read-only / inactive state.
                'code' => 'OUTLET', 'name' => 'Old City Outlet',
                'address_line1' => 'Soni Bazaar', 'city' => 'Bhuj',
                'state' => 'Gujarat', 'postal_code' => '370001',
                'phone' => '+91 2832 251999', 'email' => 'oldcity@demo.test',
                'is_active' => false,
            ],
        ];
    }

    private function resolveCurrency(): ?string
    {
        return DB::table('company')->value('base_currency_code')
            ?? DB::table('currencies')->where('is_active', true)->value('code')
            ?? DB::table('currencies')->value('code');
    }

    private function resolveTimezone(): string
    {
        return DB::table('stores')->whereNull('deleted_at')->orderBy('id')->value('timezone')
            ?? 'Asia/Kolkata';
    }

    /** Give the admin (first user) access to every store via the Admin role. */
    private function attachAdmin(\Illuminate\Support\Carbon $now): void
    {
        $userId = DB::table('users')->orderBy('id')->value('id');
        $roleId = DB::table('roles')->where('name', 'Admin')->value('id')
            ?? DB::table('roles')->orderBy('id')->value('id');

        if (! $userId || ! $roleId) {
            return;
        }

        $storeIds = DB::table('stores')->whereNull('deleted_at')->pluck('id');
        foreach ($storeIds as $storeId) {
            DB::table('store_user')->insertOrIgnore([
                'store_id'   => $storeId,
                'user_id'    => $userId,
                'role_id'    => $roleId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
