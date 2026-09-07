<?php

namespace App\Actions\Installer;

use App\Support\InstallState;
use Illuminate\Support\Facades\DB;

class LockInstaller
{
    public function __invoke(): void
    {
        $version = '1.0.0';

        DB::table('install_state')->insertOrIgnore([
            'installed_at'      => now(),
            'installer_version' => $version,
            'current_version'   => $version,
        ]);

        InstallState::lock($version, $version);
    }
}
