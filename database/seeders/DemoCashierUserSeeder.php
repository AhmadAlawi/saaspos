<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the demo CASHIER login so the credentials shown on the login page
 * (config('pos.demo.credentials') → role "cashier") actually work for testing.
 *
 * The email + password are read from that same config, so the seeded password
 * is always exactly what the login page displays. Guarded by demo mode and
 * idempotent — it never touches a real install, and re-running it just resets
 * the demo cashier's password. The user gets the Cashier role on the main
 * store (mirrors AttachAdminToStore's store_user wiring).
 */
class DemoCashierUserSeeder extends Seeder
{
    public function run(): void
    {
        // Only ever create this account on a demo install.
        if (! config('pos.demo.enabled')) {
            return;
        }

        $cred = collect(config('pos.demo.credentials', []))->firstWhere('role', 'cashier');
        $email    = $cred['email'] ?? null;
        $password = $cred['password'] ?? null;
        if (empty($email) || empty($password)) {
            return; // no demo cashier credentials configured — nothing to seed
        }

        $cashierRoleId = DB::table('roles')->where('name', 'Cashier')->value('id');
        $storeId = DB::table('stores')->where('is_active', true)->orderBy('id')->value('id')
            ?? DB::table('stores')->orderBy('id')->value('id');
        if (! $cashierRoleId || ! $storeId) {
            return; // roles/stores not seeded yet — can't wire the user up
        }

        $now      = now();
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
                'name'              => 'Demo Cashier',
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

        // Grant the Cashier role on the main store (per-store pivot).
        DB::table('store_user')->updateOrInsert(
            ['store_id' => $storeId, 'user_id' => $userId],
            ['role_id' => $cashierRoleId, 'updated_at' => $now, 'created_at' => $now],
        );
    }
}
