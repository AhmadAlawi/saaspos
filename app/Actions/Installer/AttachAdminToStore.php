<?php

namespace App\Actions\Installer;

use Illuminate\Support\Facades\DB;

class AttachAdminToStore
{
    public function __invoke(int $userId, int $storeId): void
    {
        $adminRoleId = DB::table('roles')->where('name', 'Admin')->value('id');

        DB::table('store_user')->insertOrIgnore([
            'store_id'   => $storeId,
            'user_id'    => $userId,
            'role_id'    => $adminRoleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->where('id', $userId)->update(['default_store_id' => $storeId]);
    }
}
