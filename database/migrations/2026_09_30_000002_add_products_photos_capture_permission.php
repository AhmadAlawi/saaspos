<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds `products.photos.capture` — the mobile floor photo-capture tool
 * (scan a barcode, take a photo) is a deliberately narrow permission,
 * separate from `products.update`, so an employee can be granted ONLY
 * this without full product-editing rights. Same shape as
 * `2026_09_19_020000_add_cameras_view_permission.php`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'key'          => 'products.photos.capture',
            'group'        => 'Products',
            'label'        => 'Capture product photos (mobile)',
            'is_dangerous' => false,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        $newPermId = (int) DB::table('permissions')->where('key', 'products.photos.capture')->value('id');
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
        DB::table('permissions')->where('key', 'products.photos.capture')->delete();
    }
};
