<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the `settings.manage_receipt_templates` permission for existing
 * installs. New installs pick it up via the PermissionsSeeder. Granted
 * to any role that already carries `settings.update` — the same
 * permission that gates the existing receipt-settings screen
 * ({@see \App\Http\Controllers\Admin\ReceiptSettingsController::update()}) —
 * so this rides the same trust level rather than introducing a new one.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'key'        => 'settings.manage_receipt_templates',
            'group'      => 'Settings',
            'label'      => 'Create and manage receipt/invoice templates',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $newPermId = (int) DB::table('permissions')->where('key', 'settings.manage_receipt_templates')->value('id');
        $updatePerm = (int) DB::table('permissions')->where('key', 'settings.update')->value('id');
        if (! $newPermId || ! $updatePerm) {
            return;
        }

        $roleIds = DB::table('role_permission')->where('permission_id', $updatePerm)->pluck('role_id');
        foreach ($roleIds as $roleId) {
            DB::table('role_permission')->insertOrIgnore([
                'role_id'       => $roleId,
                'permission_id' => $newPermId,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('key', 'settings.manage_receipt_templates')->delete();
    }
};
