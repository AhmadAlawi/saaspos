<?php

namespace App\Http\Controllers\Installer;

use App\Actions\Installer\ValidateLicense;
use App\Actions\Installer\WriteEnvFile;
use App\Http\Controllers\Controller;
use App\Support\InstallState;
use Illuminate\Http\Request;

class LicenseController extends Controller
{
    public function show()
    {
        // License enforcement turned off (config/pos.php → license.required):
        // mark the step satisfied and jump straight to the database step, so
        // the customer never sees a license screen.
        if (! config('pos.license.required')) {
            InstallState::set('license_validated', true);

            return redirect()->route('install.database');
        }

        return view('installer.license', [
            'currentStep'      => 3,
            'alreadyValidated' => InstallState::get('license_validated', false),
        ]);
    }

    public function validateLicense(Request $request, ValidateLicense $validate, WriteEnvFile $writeEnv)
    {
        $data = $request->validate([
            'license_key' => ['required', 'string', 'max:255'],
        ]);

        $result = ($validate)($data['license_key'], url('/'));

      

        if (! $result['ok']) {
            return back()->withErrors(['license_key' => $result['error']])->withInput();
        }

        // The key + fingerprint go to .env so the background 30-day re-check
        // (and a future major-version re-validation) can re-POST without the
        // customer re-entering anything. The full validated response is stashed
        // in the installer state file — the DB doesn't exist yet (it's set up
        // at Step 4) — and gets written into the company row at completion.
        ($writeEnv)([
            'LICENSE_KEY'         => $data['license_key'],
            'LICENSE_FINGERPRINT' => $result['fingerprint'] ?? '',
        ]);

        InstallState::setMany([
            'license_validated' => true,
            'license_key_hash'  => hash('sha256', $data['license_key']),
            'license'           => $result['response'] ?? [],
        ]);

        return redirect()->route('install.database');
    }
}
