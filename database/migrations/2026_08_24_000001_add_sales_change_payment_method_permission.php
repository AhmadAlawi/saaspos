<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the `sales.change_payment_method` permission for existing
 * installs. New installs pick it up via PermissionsSeeder. Granted to
 * any role that already carries `sales.void` — voiding a posted sale
 * and retroactively correcting its payment method are the same trust
 * tier (both rewrite completed financial history), so this rides that
 * existing level rather than introducing a new one.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'key'          => 'sales.change_payment_method',
            'group'        => 'Sales',
            'label'        => 'Change a completed sale\'s payment method',
            'is_dangerous' => true,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        $newPermId = (int) DB::table('permissions')->where('key', 'sales.change_payment_method')->value('id');
        $voidPermId = (int) DB::table('permissions')->where('key', 'sales.void')->value('id');
        if (! $newPermId || ! $voidPermId) {
            return;
        }

        $roleIds = DB::table('role_permission')->where('permission_id', $voidPermId)->pluck('role_id');
        foreach ($roleIds as $roleId) {
            DB::table('role_permission')->insertOrIgnore([
                'role_id'       => $roleId,
                'permission_id' => $newPermId,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('key', 'sales.change_payment_method')->delete();
    }
};
