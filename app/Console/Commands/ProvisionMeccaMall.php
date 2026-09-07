<?php

namespace App\Console\Commands;

use App\Support\InstallState;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * One-time bootstrap for the Mecca Mall branch instance. Bypasses the
 * installer wizard (writes InstallState directly) and seeds real
 * production-derived data — company, store, 3 terminals, roles/permissions
 * via the normal seeders, the superadmin (existing production credentials),
 * 9 named employees, and the full product catalog — instead of the demo
 * seeders. Idempotent: guarded by InstallState::isLocked(), a no-op on
 * every run after the first success, so it's safe to leave wired into the
 * entrypoint and re-run on every deploy.
 *
 * Product stock at this new branch starts at 0 for every product — a
 * fresh location has no physical inventory yet; quantities get entered
 * via a real stock take or purchase receipt once the store opens.
 */
class ProvisionMeccaMall extends Command
{
    protected $signature = 'mecca:provision';

    protected $description = 'One-time provisioning of the Mecca Mall branch (company, store, terminals, users, catalog).';

    public function handle(): int
    {
        if (! InstallState::isLocked()) {
            Artisan::call('db:seed', ['--force' => true]);
            $this->info(Artisan::output());

            DB::transaction(function () {
                $storeId = $this->createCompanyAndStore();
                $this->createTerminals($storeId);
                $this->createUsers($storeId);
                $this->importCatalog($storeId);
            });

            InstallState::setMany([
                'language'           => 'en',
                'license_validated'  => true,
                'db_configured'      => true,
                'migrations_run'     => true,
                'admin_created'      => true,
                'demo_seeded'        => true,
            ]);
            InstallState::lock(config('app.version', '1.0.4'), '1.0.4');

            $this->info('Mecca Mall provisioning complete.');
        } else {
            $this->info('Already provisioned — skipping core setup.');
        }

        // ChartOfAccountsSeeder no-ops once the company row exists AND the
        // accounts table is non-empty — the first db:seed call above ran
        // before the company was created, so it always skipped that pass.
        // Safe to call unconditionally on every boot.
        Artisan::call('db:seed', ['--class' => 'ChartOfAccountsSeeder', '--force' => true]);
        $this->info(Artisan::output());

        return self::SUCCESS;
    }

    private function fixture(string $name): array
    {
        return json_decode(file_get_contents(base_path("database/fixtures/{$name}.json")), true);
    }

    private function fetchToPublicDisk(string $path): void
    {
        if ($path === '' || Storage::disk('public')->exists($path)) {
            return;
        }

        $bytes = @file_get_contents('https://pos.infinityglobal.com.jo/storage/'.$path);
        if ($bytes !== false) {
            Storage::disk('public')->put($path, $bytes);
        }
    }

    private function createCompanyAndStore(): int
    {
        $data = $this->fixture('mecca_company_store');

        foreach (['logo_path', 'app_logo_path', 'app_logo_dark_path', 'favicon_path'] as $key) {
            if (! empty($data['company'][$key])) {
                $this->fetchToPublicDisk($data['company'][$key]);
            }
        }

        DB::table('company')->insert($data['company'] + ['created_at' => now(), 'updated_at' => now()]);

        return DB::table('stores')->insertGetId(
            $data['store'] + ['created_at' => now(), 'updated_at' => now()]
        );
    }

    private function createTerminals(int $storeId): void
    {
        foreach (['mca-pos-1', 'mca-pos-2', 'mca-pos-3'] as $name) {
            DB::table('terminals')->insert([
                'store_id'   => $storeId,
                'code'       => strtoupper($name),
                'name'       => $name,
                'type'       => 'register',
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function createUsers(int $storeId): void
    {
        $data = $this->fixture('mecca_users');

        $adminId = DB::table('users')->insertGetId([
            'name'              => $data['superadmin']['name'],
            'email'             => $data['superadmin']['email'],
            'email_verified_at' => now(),
            'password'          => $data['superadmin']['password_hash'],
            'is_super_admin'    => true,
            'is_active'         => true,
            'default_store_id'  => $storeId,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $adminRoleId = DB::table('roles')->where('name', 'Admin')->value('id');
        DB::table('store_user')->insert([
            'store_id'   => $storeId,
            'user_id'    => $adminId,
            'role_id'    => $adminRoleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roleIds = DB::table('roles')->pluck('id', 'name');

        foreach ($data['employees'] as $employee) {
            $userId = DB::table('users')->insertGetId([
                'name'              => $employee['name'],
                'email'             => $employee['email'],
                'email_verified_at' => now(),
                'password'          => Hash::make($employee['password']),
                'pin'               => Hash::make($employee['pin']),
                'is_super_admin'    => false,
                'is_active'         => true,
                'default_store_id'  => $storeId,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            DB::table('store_user')->insert([
                'store_id'   => $storeId,
                'user_id'    => $userId,
                'role_id'    => $roleIds[$employee['role']],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Re-inserts categories / units / tax groups+components / products
     * with FRESH auto-increment ids rather than the original production
     * ones — units/roles/permissions already exist here via the day-1
     * seeders, so ids would collide; everything is remapped by natural
     * key (code / slug) instead of copying raw ids across databases.
     */
    private function importCatalog(int $storeId): void
    {
        $data = $this->fixture('mecca_catalog');

        // ── tax components → tax groups → tax_group_components ──
        $componentMap = [];
        foreach ($data['tax_components'] as $row) {
            $oldId = $row['id'];
            unset($row['id']);
            $componentMap[$oldId] = DB::table('tax_components')->insertGetId($row);
        }

        $groupMap = [];
        foreach ($data['tax_groups'] as $row) {
            $oldId = $row['id'];
            unset($row['id']);
            $groupMap[$oldId] = DB::table('tax_groups')->insertGetId($row);
        }

        foreach ($data['tax_group_components'] as $row) {
            if (! isset($groupMap[$row['tax_group_id']], $componentMap[$row['tax_component_id']])) {
                continue;
            }
            DB::table('tax_group_components')->insert([
                'tax_group_id'     => $groupMap[$row['tax_group_id']],
                'tax_component_id' => $componentMap[$row['tax_component_id']],
                'sort_order'       => $row['sort_order'],
                'valid_from'       => $row['valid_from'],
                'valid_to'         => $row['valid_to'],
            ]);
        }

        // ── units — the day-1 seeder already created the standard set;
        // only reconcile codes it doesn't have (e.g. a custom "pcs"). ──
        $unitMap = [];
        $existingUnitsByCode = DB::table('units')->pluck('id', 'code');
        $pendingBaseUnit = [];
        foreach ($data['units'] as $row) {
            if ($row['deleted_at'] !== null) {
                continue;
            }
            $oldId = $row['id'];
            unset($row['deleted_at']);
            if ($existingUnitsByCode->has($row['code'])) {
                $unitMap[$oldId] = $existingUnitsByCode[$row['code']];
                continue;
            }
            $baseUnitOldId = $row['base_unit_id'];
            unset($row['id'], $row['base_unit_id']);
            $newId = DB::table('units')->insertGetId($row);
            $unitMap[$oldId] = $newId;
            if ($baseUnitOldId !== null) {
                $pendingBaseUnit[$newId] = $baseUnitOldId;
            }
        }
        foreach ($pendingBaseUnit as $newId => $baseOldId) {
            DB::table('units')->where('id', $newId)->update([
                'base_unit_id' => $unitMap[$baseOldId] ?? null,
            ]);
        }

        // ── categories (none exist yet — no day-1 seeder for these) ──
        $categoryMap = [];
        $pendingParent = [];
        foreach ($data['categories'] as $row) {
            if ($row['deleted_at'] !== null) {
                continue;
            }
            $oldId = $row['id'];
            $parentOldId = $row['parent_id'];
            $taxGroupOldId = $row['tax_group_id'];
            unset($row['id'], $row['parent_id']);
            $row['tax_group_id'] = $taxGroupOldId !== null ? ($groupMap[$taxGroupOldId] ?? null) : null;
            $newId = DB::table('categories')->insertGetId($row);
            $categoryMap[$oldId] = $newId;
            if ($parentOldId !== null) {
                $pendingParent[$newId] = $parentOldId;
            }
        }
        foreach ($pendingParent as $newId => $parentOldId) {
            DB::table('categories')->where('id', $newId)->update([
                'parent_id' => $categoryMap[$parentOldId] ?? null,
            ]);
        }

        // ── products, then zeroed stock rows for the new store ──
        $skipped = 0;
        foreach (array_chunk($data['products'], 200) as $chunk) {
            $stockRows = [];
            foreach ($chunk as $row) {
                if (! isset($unitMap[$row['unit_id']])) {
                    $skipped++;
                    continue;
                }
                $oldId = $row['id'];
                unset($row['id']);
                $row['category_id']  = $row['category_id']  !== null ? ($categoryMap[$row['category_id']] ?? null) : null;
                $row['unit_id']      = $unitMap[$row['unit_id']];
                $row['tax_group_id'] = $row['tax_group_id'] !== null ? ($groupMap[$row['tax_group_id']] ?? null) : null;
                $newId = DB::table('products')->insertGetId($row);

                $stockRows[] = [
                    'store_id'              => $storeId,
                    'product_id'            => $newId,
                    'quantity'              => 0,
                    'reserved_quantity'     => 0,
                    'weighted_average_cost' => $row['cost_price'],
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ];
            }
            if ($stockRows) {
                DB::table('product_stock_levels')->insert($stockRows);
            }
        }

        if ($skipped > 0) {
            $this->warn("Skipped {$skipped} product(s) with an unresolvable unit.");
        }
    }
}
