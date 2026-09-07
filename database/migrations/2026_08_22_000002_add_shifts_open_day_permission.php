<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the `shifts.open_day` permission for existing installs. New
 * installs pick it up via PermissionsSeeder. Granted to any role that
 * already carries `shifts.close_others` — opening and closing the
 * trading day are the same trust tier (a bigger call than an
 * individual employee's own shift), so this rides that existing level
 * rather than introducing a new one.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'key'        => 'shifts.open_day',
            'group'      => 'Shifts',
            'label'      => 'Open the trading day',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $newPermId  = (int) DB::table('permissions')->where('key', 'shifts.open_day')->value('id');
        $closeOthersPermId = (int) DB::table('permissions')->where('key', 'shifts.close_others')->value('id');
        if (! $newPermId || ! $closeOthersPermId) {
            return;
        }

        $roleIds = DB::table('role_permission')->where('permission_id', $closeOthersPermId)->pluck('role_id');
        foreach ($roleIds as $roleId) {
            DB::table('role_permission')->insertOrIgnore([
                'role_id'       => $roleId,
                'permission_id' => $newPermId,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('key', 'shifts.open_day')->delete();
    }
};
