<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the `products.manage_price_rules` permission for existing installs.
 * New installs pick it up via the PermissionsSeeder. Granted to any role
 * that already carries `products.update_cost` — the existing "trusted
 * with pricing" signal in this app — so plain cashier roles stay blocked
 * from scheduling discounts by default.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'key'        => 'products.manage_price_rules',
            'group'      => 'Products',
            'label'      => 'Create and manage scheduled price rules (time-boxed product/category discounts)',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $newPermId = (int) DB::table('permissions')->where('key', 'products.manage_price_rules')->value('id');
        $costPerm  = (int) DB::table('permissions')->where('key', 'products.update_cost')->value('id');
        if (! $newPermId || ! $costPerm) {
            return;
        }

        $roleIds = DB::table('role_permission')->where('permission_id', $costPerm)->pluck('role_id');
        foreach ($roleIds as $roleId) {
            DB::table('role_permission')->insertOrIgnore([
                'role_id'       => $roleId,
                'permission_id' => $newPermId,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('key', 'products.manage_price_rules')->delete();
    }
};
