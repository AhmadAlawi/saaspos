<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Admin → Settings → Apple Wallet. Holds the credentials
 * {@see \App\Services\Wallet\PassKitBuilder} needs to sign a .pkpass:
 * Apple Team ID, Pass Type ID, and the Pass Type ID certificate (a
 * `.p12` file — Apple issues this from the Developer portal, requires
 * a paid Apple Developer Program membership). Super-admin only, same
 * reasoning as {@see CameraSettingsController}: a real external signing
 * credential, not a per-permission-gated business setting.
 *
 * The certificate FILE lives on the `local` disk (never `public` — see
 * `config/filesystems.php`), at a fixed path. Only one company/pass
 * type is supported, so there's no per-row filename to manage.
 */
class AppleWalletSettingsController extends Controller
{
    public const CERT_PATH = 'apple-wallet/pass-cert.p12';

    public function edit(Request $request): View
    {
        abort_unless($request->user()?->is_super_admin, 403);

        $company = Company::current() ?? new Company();

        return view('admin.settings.apple-wallet', [
            'company'       => $company,
            'hasCert'       => Storage::disk('local')->exists(self::CERT_PATH),
            'hasPassFastKey' => filled($company->passfast_api_key),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless($request->user()?->is_super_admin, 403);

        $data = $request->validate([
            'apple_wallet_enabled' => ['sometimes', 'boolean'],
            'apple_team_id'        => ['nullable', 'string', 'max:32'],
            'apple_pass_type_id'   => ['nullable', 'string', 'max:191'],
            // Blank keeps the existing password — never force a re-type
            // just to flip the enabled toggle or fix a typo'd team id.
            'apple_cert_password'  => ['nullable', 'string', 'max:255'],
            'cert'                 => ['nullable', 'file', 'mimes:p12,pfx', 'max:2048'],
            // PassFast — the alternative to the certificate above; see
            // PassFastClient's doc-comment. Blank api key keeps the
            // existing one, same reasoning as the cert password.
            'passfast_api_key'      => ['nullable', 'string', 'max:255'],
            'passfast_template_id'  => ['nullable', 'string', 'max:191'],
            'passfast_app_id'       => ['nullable', 'string', 'max:191'],
        ]);

        $company = Company::current();
        abort_unless($company !== null, 404);

        $company->fill([
            'apple_wallet_enabled' => $request->boolean('apple_wallet_enabled'),
            'apple_team_id'        => $data['apple_team_id'] ?? null,
            'apple_pass_type_id'   => $data['apple_pass_type_id'] ?? null,
            'passfast_template_id' => $data['passfast_template_id'] ?? null,
            'passfast_app_id'      => $data['passfast_app_id'] ?? null,
        ]);
        if (filled($data['apple_cert_password'] ?? null)) {
            $company->apple_cert_password = $data['apple_cert_password'];
        }
        if (filled($data['passfast_api_key'] ?? null)) {
            $company->passfast_api_key = $data['passfast_api_key'];
        }
        $company->save();

        if ($request->hasFile('cert')) {
            // A new upload replaces the old file outright — there's only
            // ever one, no history to keep.
            $request->file('cert')->storeAs(dirname(self::CERT_PATH), basename(self::CERT_PATH), 'local');
        }

        return response()->json(['message' => __('settings.apple_wallet.flash.updated')]);
    }
}
