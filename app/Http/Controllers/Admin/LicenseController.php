<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Licensing\RecheckLicense;
use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Settings → License. Read-only view of the license validated at install and
 * cached on the company row (docs/features/installer.md §6), plus an on-demand
 * "Re-check now" that re-verifies against the license server. The scheduled
 * 30-day re-check runs the same action. Gated by `settings.view` / `.update`.
 *
 * The whole screen is switched OFF by default (config/pos.php →
 * license.recheck): the installer already validated the purchase code once, and
 * re-verifying it in the panel only produced a permanent "invalid" banner on
 * fresh installs. Routes stay registered so `route()` calls elsewhere never
 * blow up — the actions 404 instead.
 *
 * The license KEY is never shown in full — only the last 4 characters, per §11.
 */
class LicenseController extends Controller
{
    use RespondsJsonOrRedirect;

    public function index(): View
    {
        abort_unless(config('pos.license.recheck'), 404);
        $this->authorize('settings.view');

        $license = app_license();

        return view('admin.settings.license', [
            'license'   => $license,
            'keyLast4'  => $this->maskedKey(),
        ]);
    }

    /** Force a re-verification now (the same action the scheduler runs nightly). */
    public function recheck(Request $request, RecheckLicense $recheck): JsonResponse|RedirectResponse
    {
        abort_unless(config('pos.license.recheck'), 404);
        $this->authorize('settings.update');

        $result = ($recheck)(force: true);

        $message = match (true) {
            ! empty($result['skipped']) => __('settings.license.recheck.skipped'),
            ! ($result['ok'] ?? false)  => __('settings.license.recheck.unreachable'),
            ($result['status'] ?? null) === 'invalid' => __('settings.license.recheck.invalid'),
            default                     => __('settings.license.recheck.ok'),
        };

        return $this->jsonOrRedirect($request, $message, route('admin.settings.license.index'));
    }

    /** Last 4 chars of the configured license key, or null if none. */
    private function maskedKey(): ?string
    {
        $key = (string) env('LICENSE_KEY', '');

        return $key === '' ? null : substr($key, -4);
    }
}
