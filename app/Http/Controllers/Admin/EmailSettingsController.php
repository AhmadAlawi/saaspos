<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Settings\SendTestEmail;
use App\Actions\Settings\UpdateCompanyProfile;
use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmailSettingsRequest;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Email / SMTP settings — the outbound mail transport. Stored on the
 * company row; applied at runtime by `ApplyCompanySettings` middleware
 * so `Mail::send()` everywhere picks them up. Gated by `settings.view`
 * / `settings.update`. Also exposes a `sendTest()` action so the user
 * can verify credentials from the settings page.
 */
class EmailSettingsController extends Controller
{
    use RespondsJsonOrRedirect;

    public function edit(): View
    {
        $this->authorize('settings.view');

        $company = Company::current() ?? new Company();

        // On the public demo, mask the SMTP host + username so the demo's real
        // mail account isn't exposed. Mutating the in-memory model only (never
        // saved); the password is already write-only, and update() is blocked.
        if (pos_is_demo()) {
            $company->mail_host     = mask_secret((string) $company->mail_host);
            $company->mail_username = mask_secret((string) $company->mail_username);
        }

        return view('admin.settings.email', [
            'company'     => $company,
            'drivers'     => EmailSettingsRequest::DRIVERS,
            'encryptions' => EmailSettingsRequest::ENCRYPTIONS,
        ]);
    }

    public function update(EmailSettingsRequest $request, UpdateCompanyProfile $update): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.update');

        if (pos_is_demo()) {
            return $this->jsonOrError(
                $request,
                __('settings.demo.locked'),
                route('admin.settings.email.edit'),
            );
        }

        $company = Company::current();
        abort_unless($company !== null, 404);

        $update($company, $request->mailData($company->mail_password));

        return $this->jsonOrRedirect(
            $request,
            __('settings.email.flash.updated'),
            route('admin.settings.email.edit'),
        );
    }

    public function sendTest(Request $request, SendTestEmail $send): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.update');

        if (pos_is_demo()) {
            return $this->jsonOrError(
                $request,
                __('settings.demo.locked'),
                route('admin.settings.email.edit'),
            );
        }

        $data = Validator::make($request->all(), [
            'to' => ['required', 'email:rfc'],
        ])->validate();

        $error = $send($data['to']);

        if ($error === null) {
            return $this->jsonOrRedirect(
                $request,
                __('settings.email.flash.test_sent', ['to' => $data['to']]),
                route('admin.settings.email.edit'),
            );
        }

        return $this->jsonOrError(
            $request,
            __('settings.email.flash.test_failed', ['error' => $error]),
            route('admin.settings.email.edit'),
        );
    }
}
