<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Creates one demo login per role listed in config('pos.demo.credentials') —
 * generalizes {@see DemoCashierUserSeeder}'s pattern (which stays as-is for
 * backward compat) to cover every role a demo visitor should be able to try,
 * not just Cashier. Entries with no email/password configured are skipped,
 * so the demo operator only lights up the roles they've actually set env
 * vars for.
 *
 * Guarded by demo mode and idempotent — never touches a real install,
 * re-running just resets each demo user's password.
 */
class DemoRoleUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('pos.demo.enabled')) {
            return;
        }

        $storeId = DB::table('stores')->where('is_active', true)->orderBy('id')->value('id')
            ?? DB::table('stores')->orderBy('id')->value('id');
        if (! $storeId) {
            return; // stores not seeded yet — can't wire users up
        }

        // config role key -> actual `roles.name` (RolesSeeder is the source
        // of truth for these strings).
        $roleNames = [
            'admin'        => 'Admin',
            'manager'      => 'Manager',
            'cashier'      => 'Cashier',
            'stock_keeper' => 'Stock Keeper',
            'accountant'   => 'Accountant',
        ];

        foreach (config('pos.demo.credentials', []) as $cred) {
            $roleKey = $cred['role'] ?? null;
            $email = $cred['email'] ?? null;
            $password = $cred['password'] ?? null;
            $roleName = $roleNames[$roleKey] ?? null;

            if (empty($email) || empty($password) || $roleName === null) {
                continue;
            }

            $roleId = DB::table('roles')->where('name', $roleName)->value('id');
            if (! $roleId) {
                continue;
            }

            $now = now();
            $existing = DB::table('users')->where('email', $email)->value('id');

            if ($existing) {
                DB::table('users')->where('id', $existing)->update([
                    'password'         => Hash::make($password),
                    'is_active'        => true,
                    'default_store_id' => $storeId,
                    'updated_at'       => $now,
                ]);
                $userId = $existing;
            } else {
                $userId = DB::table('users')->insertGetId([
                    'name'              => 'Demo '.$roleName,
                    'email'             => $email,
                    'password'          => Hash::make($password),
                    'email_verified_at' => $now,
                    'is_super_admin'    => false,
                    'is_active'         => true,
                    'locale'            => 'en',
                    'default_store_id'  => $storeId,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]);
            }

            DB::table('store_user')->updateOrInsert(
                ['store_id' => $storeId, 'user_id' => $userId],
                ['role_id' => $roleId, 'updated_at' => $now, 'created_at' => $now],
            );
        }
    }
}
