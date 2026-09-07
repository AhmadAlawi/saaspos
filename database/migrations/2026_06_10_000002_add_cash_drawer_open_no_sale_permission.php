<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the `cash_drawer.open_no_sale` permission for existing installs.
 * New installs pick it up via the PermissionsSeeder. Granted to any
 * role that already carries `cash_drawer.pay_in` so cashier roles
 * keep the same operator-level capability.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'key'        => 'cash_drawer.open_no_sale',
            'group'      => 'Shifts',
            'label'      => 'Open cash drawer without a sale',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $newPermId  = (int) DB::table('permissions')->where('key', 'cash_drawer.open_no_sale')->value('id');
        $payInPerm  = (int) DB::table('permissions')->where('key', 'cash_drawer.pay_in')->value('id');
        if (! $newPermId || ! $payInPerm) {
            return;
        }

        $roleIds = DB::table('role_permission')->where('permission_id', $payInPerm)->pluck('role_id');
        foreach ($roleIds as $roleId) {
            DB::table('role_permission')->insertOrIgnore([
                'role_id'       => $roleId,
                'permission_id' => $newPermId,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('key', 'cash_drawer.open_no_sale')->delete();
    }
};
