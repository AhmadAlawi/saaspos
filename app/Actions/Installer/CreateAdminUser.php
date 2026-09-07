<?php

namespace App\Actions\Installer;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser
{
    /**
     * @param array{name: string, email: string, password: string} $data
     */
    public function __invoke(array $data): int
    {
        $now = now();

        $userId = DB::table('users')->insertGetId([
            'name'              => $data['name'],
            'email'             => $data['email'],
            'password'          => Hash::make($data['password']),
            'email_verified_at' => $now,
            'is_super_admin'    => true,
            'is_active'         => true,
            'locale'            => 'en',
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);

        return $userId;
    }
}
