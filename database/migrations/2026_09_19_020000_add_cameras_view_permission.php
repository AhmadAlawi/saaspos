<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds `cameras.view` — the live-camera-grid page
 * ({@see \App\Http\Controllers\Admin\CameraLiveController}) is a normal
 * per-permission feature (assignable to any role), unlike the NVR
 * connection/channel-assignment settings under Settings → Cameras,
 * which stay super-admin-only since they hold real device credentials.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'key'          => 'cameras.view',
            'group'        => 'Hardware',
            'label'        => 'View live camera feeds',
            'is_dangerous' => false,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        $newPermId = (int) DB::table('permissions')->where('key', 'cameras.view')->value('id');
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
        DB::table('permissions')->where('key', 'cameras.view')->delete();
    }
};
