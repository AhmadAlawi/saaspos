<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the `sales.oversell` permission for existing installs. New installs
 * pick it up via the PermissionsSeeder. Granted to any role that already
 * carries `sales.void` — a manager-level, trusted operation — so those
 * roles can sell below stock when the "allow negative stock" policy is on,
 * while plain cashier roles stay blocked by default.
 *
 * See docs/features/inventory.md (negative stock) and
 * docs/features/sales-checkout.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'key'        => 'sales.oversell',
            'group'      => 'Sales',
            'label'      => 'Sell below available stock (overrides the negative-stock block)',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $newPermId = (int) DB::table('permissions')->where('key', 'sales.oversell')->value('id');
        $voidPerm  = (int) DB::table('permissions')->where('key', 'sales.void')->value('id');
        if (! $newPermId || ! $voidPerm) {
            return;
        }

        $roleIds = DB::table('role_permission')->where('permission_id', $voidPerm)->pluck('role_id');
        foreach ($roleIds as $roleId) {
            DB::table('role_permission')->insertOrIgnore([
                'role_id'       => $roleId,
                'permission_id' => $newPermId,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('key', 'sales.oversell')->delete();
    }
};
