<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $permissionsByKey = DB::table('permissions')->pluck('id', 'key')->all();
        $allPermissionIds = array_values($permissionsByKey);

        // ── 1. Admin: every permission. ──────────────────────────────────────
        $this->upsertRole('Admin', 'Full access to everything.', $allPermissionIds, $now);

        // ── 2. Manager: everything except a few dangerous bits. ──────────────
        $managerExcluded = [
            'settings.update_dangerous',
            'users.create_super_admin',
            'updater.run',
            'backup.restore',
            'accounting.unlock_period',
            'accounting.year_end_close',
            'accounting.opening_balances',
        ];
        $this->upsertRole(
            'Manager',
            'All operations except dangerous administrative tasks.',
            $this->keysToIds(array_diff(array_keys($permissionsByKey), $managerExcluded), $permissionsByKey),
            $now,
        );

        // ── 3. Cashier: minimal POS use. ─────────────────────────────────────
        $cashierKeys = [
            'sales.create',
            'sales.view_own',
            'sales.discount',
            'sales.print_receipt',
            'sales.held.create',
            'sales.held.resume_others',
            'returns.create_above_threshold',
            'customers.view',
            'customers.create',
            'shifts.open',
            'shifts.close_own',
            'products.view',
        ];
        $this->upsertRole(
            'Cashier',
            'Day-to-day cashier: sell, return, basic customer & shift management.',
            $this->keysToIds($cashierKeys, $permissionsByKey),
            $now,
        );

        // ── 4. Stock Keeper: inventory and purchasing. ───────────────────────
        $stockKeeperKeys = [
            'products.view',
            'products.update',
            'products.import',
            'products.export',
            'products.adjust_stock',
            'products.transfer_stock',
            // Tidying spent batches is stock work, not catalogue work — and the
            // action only ever archives an EMPTY batch.
            'products.delete_batch',
            'taxonomies.manage',
            'purchases.view',
            'purchases.create',
            'purchases.receive',
            'suppliers.view',
            'suppliers.create',
            'reports.view_inventory',
            'reports.view_suppliers',
        ];
        $this->upsertRole(
            'Stock Keeper',
            'Stock and purchasing operations.',
            $this->keysToIds($stockKeeperKeys, $permissionsByKey),
            $now,
        );

        // ── 5. Accountant: financial visibility + reporting. ─────────────────
        //   (Accounting + expenses modules are deferred; this role currently
        //    covers AP visibility and financial/tax reporting.)
        $accountantKeys = [
            'suppliers.view',
            'purchases.view',
            'expenses.view',
            'expenses.create',
            'expenses.update',
            'expenses.export',
            'reports.view_sales',
            'reports.view_inventory',
            'reports.view_customers',
            'reports.view_suppliers',
            'reports.view_financial',
            'reports.view_tax',
            'reports.export',
            'reports.schedule',
            'accounting.view',
            'accounting.manual_entry',
            'accounting.reverse_entry',
        ];
        $this->upsertRole(
            'Accountant',
            'Financial and tax reporting, with supplier and purchase visibility.',
            $this->keysToIds($accountantKeys, $permissionsByKey),
            $now,
        );
    }

    private function upsertRole(string $name, string $description, array $permissionIds, \DateTimeInterface $now): void
    {
        DB::table('roles')->updateOrInsert(
            ['name' => $name],
            [
                'description' => $description,
                'is_system'   => true,
                'updated_at'  => $now,
                'created_at'  => $now,
            ],
        );

        $roleId = DB::table('roles')->where('name', $name)->value('id');

        // Reset pivot to declared set — idempotent re-runs always reflect this seeder.
        DB::table('role_permission')->where('role_id', $roleId)->delete();

        if ($permissionIds === []) {
            return;
        }

        DB::table('role_permission')->insert(array_map(
            fn ($pid) => ['role_id' => $roleId, 'permission_id' => $pid],
            array_values(array_unique($permissionIds)),
        ));
    }

    private function keysToIds(iterable $keys, array $permissionsByKey): array
    {
        $ids = [];
        foreach ($keys as $key) {
            if (isset($permissionsByKey[$key])) {
                $ids[] = $permissionsByKey[$key];
            }
        }

        return $ids;
    }
}
