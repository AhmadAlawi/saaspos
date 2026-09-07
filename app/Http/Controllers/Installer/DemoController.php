<?php

namespace App\Http\Controllers\Installer;

use App\Actions\Installer\LinkPublicStorage;
use App\Actions\Installer\LockInstaller;
use App\Actions\Installer\PersistLicense;
use App\Actions\Installer\SeedDemoData;
use App\Actions\Installer\WriteEnvFile;
use App\Http\Controllers\Controller;
use App\Support\InstallState;
use Illuminate\Http\Request;

class DemoController extends Controller
{
    public function show()
    {
        return view('installer.demo', [
            'currentStep' => 6,
        ]);
    }

    public function complete(
        Request $request,
        LockInstaller $lock,
        PersistLicense $persistLicense,
        SeedDemoData $seedDemo,
        WriteEnvFile $writeEnv,
        LinkPublicStorage $linkStorage,
    ) {
        $data = $request->validate([
            'demo_mode' => ['required', 'string', 'in:full,minimal,none'],
        ]);

        // Seeding a full demo set runs many inserts — lift the SAPI limits the
        // same way the migrate step does, and don't let a dropped connection
        // abort a partial seed.
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');
        ignore_user_abort(true);

        // Move the license validated at Step 3 (stashed in the installer state
        // file because the DB didn't exist yet) into the now-created company
        // row, so the admin License page + re-check can read it.
        ($persistLicense)((array) InstallState::get('license', []));

        // Seed the chosen demo dataset. A failure here is non-fatal — demo data
        // is optional and the install still completes.
        $seedResult = ($seedDemo)($data['demo_mode']);

        // Link public/storage → storage/app/public so the very first logo the
        // owner uploads actually renders. Non-fatal: a host that forbids
        // symlink() still finishes installing, and we tell them what to do.
        $link = ($linkStorage)();

        InstallState::setMany([
            'demo_mode'    => $data['demo_mode'],
            'demo_seeded'  => $seedResult['ok'],
            'storage_link' => $link['status'],
        ]);

        // Lock the environment down for production now that the install
        // succeeded. Debug stayed on through the steps so failures were
        // diagnosable; from here the live site must never expose stack traces
        // (docs/features/installer.md §4 + §11).
        ($writeEnv)([
            'APP_ENV'   => 'production',
            'APP_DEBUG' => 'false',
        ]);

        ($lock)();

        $redirect = redirect('/login')->with('success', __('installer.complete.welcome'));

        return $link['ok']
            ? $redirect
            : $redirect->with('error', __('installer.complete.storage_link_failed'));
    }
}
