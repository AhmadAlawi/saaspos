<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the `sales.view_amounts` permission for existing installs — gates
 * the revenue-reporting sections of the shift totals table (sales
 * total, tax, discounts, refunds, per-method payment breakdown) plus
 * the cashier's recent-sales drawer. The cash-drawer reconciliation
 * numbers (expected/counted/variance) stay unconditional everywhere —
 * counting the till is operationally required to close a shift, not a
 * reporting privilege.
 *
 * Deliberately NOT granted broadly (e.g. to every role a dangerous
 * permission implies "admin tier") — the business wants this limited
 * to specific named people, not a role tier. Granted directly to the
 * `Admin` role, which today is held by exactly the super admin account
 * and one named manager — new installs pick a narrower default via
 * PermissionsSeeder and should grant this per-person through Admin >
 * Roles, same as any other role.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'key'          => 'sales.view_amounts',
            'group'        => 'Sales',
            'label'        => 'View sale, shift, and daily-total money figures',
            'is_dangerous' => false,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        $newPermId = (int) DB::table('permissions')->where('key', 'sales.view_amounts')->value('id');
        $adminRoleId = (int) DB::table('roles')->where('name', 'Admin')->value('id');
        if (! $newPermId || ! $adminRoleId) {
            return;
        }

        DB::table('role_permission')->insertOrIgnore([
            'role_id'       => $adminRoleId,
            'permission_id' => $newPermId,
        ]);
    }

    public function down(): void
    {
        DB::table('permissions')->where('key', 'sales.view_amounts')->delete();
    }
};
